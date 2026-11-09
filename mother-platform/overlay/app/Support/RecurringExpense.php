<?php
namespace App\Support;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Recurring Fixed Expense Scheduler. One instance per (recurring_id, billing_period): the unique key plus insertOrIgnore make double posting impossible.
 *  Missed days are recovered: tick() posts every period whose due date has passed and has no instance yet (scheduled date kept in due_date, actual time in posted_at).
 *  Cash/bank is touched only when Auto Deduct is ON, an account is chosen and the balance covers the amount; otherwise the expense stays payable (pending/failed). */
class RecurringExpense {
  const FREQ = ['monthly' => 1, 'quarterly' => 3, 'half_yearly' => 6, 'yearly' => 12];
  public static function ready(): bool { try { return Schema::hasTable('recurring_expense_instances'); } catch (\Throwable $e) { return false; } }
  public static function today(): Carbon { return now('Asia/Dhaka')->startOfDay(); }
  public static function step(object $m): int { return $m->frequency === 'custom' ? max(1, (int)$m->interval_months) : (self::FREQ[$m->frequency] ?? 1); }

  /** Occurrences [billing_period, due_date] of a master between two dates (inclusive on due date), respecting start/end. */
  public static function occurrences(object $m, Carbon $from, Carbon $to): array {
    $out = []; $start = Carbon::parse($m->start_date)->startOfDay(); $end = $m->end_date ? Carbon::parse($m->end_date)->endOfDay() : null;
    if ($m->frequency === 'weekly') {
      $d = $start->copy(); $dow = max(1, min(7, (int)$m->pay_day)); while ($d->dayOfWeekIso !== $dow) $d->addDay();
      for ($i = 0; $i < 400 && $d <= $to; $i++, $d->addWeek()) { if ($d >= $from && $d >= $start && (!$end || $d <= $end)) $out[] = [$d->toDateString(), $d->toDateString()]; }
      return $out;
    }
    $step = self::step($m); $p = $start->copy()->startOfMonth();
    for ($i = 0; $i < 600 && $p <= $to->copy()->endOfMonth(); $i++, $p->addMonthsNoOverflow($step)) {
      $due = $p->copy()->day(min((int)$m->pay_day, $p->daysInMonth));
      if ($due >= $start && $due >= $from && $due <= $to && (!$end || $due <= $end)) $out[] = [$p->copy()->startOfMonth()->toDateString(), $due->toDateString()];
    }
    return $out;
  }
  /** Amount in force on a date (effective-dated history; history is never overwritten). */
  public static function amountOn(object $m, string $date): array {
    $h = DB::table('recurring_expense_amounts')->where('recurring_id', $m->id)->where('effective_from', '<=', $date)->orderByDesc('effective_from')->orderByDesc('id')->first();
    return $h ? [(float)$h->amount, (float)$h->principal, (float)$h->interest, (float)$h->fee] : [(float)$m->amount, (float)$m->principal, (float)$m->interest, (float)$m->fee];
  }
  public static function balance(int $accId): float {
    $a = DB::table('money_accounts')->find($accId); if (!$a) return 0.0;
    $in = (float)DB::table('account_entries')->where('account_id', $accId)->where('direction', 'income')->sum('amount'); $ex = (float)DB::table('account_entries')->where('account_id', $accId)->where('direction', 'expense')->sum('amount');
    return (float)$a->opening + $in - $ex;
  }
  public static function purpose(string $category): int {
    $id = DB::table('expense_purposes')->where('name', $category)->value('id');
    return $id ? (int)$id : (int)DB::table('expense_purposes')->insertGetId(['name' => $category, 'is_expense' => true, 'created_at' => now(), 'updated_at' => now()]);
  }
  /** Take the money out of the account (one ledger expense entry for the full amount). Returns [ok, message]. */
  public static function settle(object $i, object $m, int $accId, string $by, bool $force = false): array {
    if ($i->payment_status === 'paid') return [true, 'already paid'];
    if (!DB::table('money_accounts')->where('id', $accId)->exists()) return [false, 'No payment account'];
    if (!$force && self::balance($accId) + 0.005 < (float)$i->amount) return [false, 'Insufficient balance'];
    $ref = 'REC-'.$m->id.'-'.Carbon::parse($i->billing_period)->format('Ymd');
    DB::table('account_entries')->insertOrIgnore(['reference' => $ref, 'direction' => 'expense', 'purpose_id' => self::purpose($m->category), 'account_id' => $accId, 'amount' => $i->amount,
      'comment' => $m->name.' · '.Carbon::parse($i->billing_period)->format('M Y').($m->is_loan ? ' · principal ৳'.number_format($i->principal, 2).' + interest ৳'.number_format($i->interest, 2).' + fee ৳'.number_format($i->fee, 2) : ''),
      'entry_date' => (Carbon::parse($i->due_date)->lte(self::today()) ? Carbon::parse($i->due_date) : self::today())->toDateString(), 'user_name' => $by, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('recurring_expense_instances')->where('id', $i->id)->update(['payment_status' => 'paid', 'account_id' => $accId, 'entry_ref' => $ref, 'paid_at' => now(), 'note' => null, 'updated_at' => now()]);
    if ($m->is_loan && $i->principal > 0) DB::table('recurring_expenses')->where('id', $m->id)->update(['remaining_balance' => DB::raw('greatest(coalesce(remaining_balance,0) - '.(float)$i->principal.', 0)')]);
    return [true, 'paid'];
  }
  /** Create the instance for one period once; post it when Auto Post is ON. */
  public static function post(object $m, string $period, string $due, string $by = 'System (Recurring)'): bool {
    [$amt, $pr, $in, $fee] = self::amountOn($m, $due);
    if ($m->is_loan && $pr + $in + $fee > 0) $amt = round($pr + $in + $fee, 2);
    $new = DB::table('recurring_expense_instances')->insertOrIgnore(['recurring_id' => $m->id, 'billing_period' => $period, 'due_date' => $due, 'amount' => $amt, 'principal' => $pr, 'interest' => $in, 'fee' => $fee,
      'status' => $m->auto_post ? 'posted' : 'due', 'payment_status' => 'pending', 'account_id' => $m->account_id, 'posted_at' => $m->auto_post ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
    if (!$new) return false;
    $i = DB::table('recurring_expense_instances')->where('recurring_id', $m->id)->where('billing_period', $period)->first();
    if ($m->auto_post && $m->auto_deduct && $m->account_id && ($m->expense_type ?? '') !== 'variable') {   // variable bills (e.g. electricity) wait for the real amount to be typed, then Pay [$ok, $msg] = self::settle($i, $m, (int)$m->account_id, $by); if (!$ok) DB::table('recurring_expense_instances')->where('id', $i->id)->update(['payment_status' => 'failed', 'note' => $msg, 'updated_at' => now()]); }
    try { DB::table('audit_logs')->insert(['user_name' => $by, 'action' => 'recurring.post', 'subject' => $m->name.' '.$period, 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return true;
  }
  /** Generate everything that is due (including missed days). Safe to call any number of times. */
  public static function run(): int {
    if (!self::ready()) return 0; $n = 0; $today = self::today();
    foreach (DB::table('recurring_expenses')->where('status', 'active')->where('source', '!=', 'payroll')->get() as $m) {
      $from = Carbon::parse($m->start_date)->max(Carbon::parse($m->created_at)->setTimezone('Asia/Dhaka')->startOfMonth());   // never back-fill before the month the master was created
      foreach (self::occurrences($m, $from, $today) as [$p, $d]) {
        try { if (self::post($m, $p, $d)) $n++; } catch (\Throwable $e) { report($e); }
      }
    }
    return $n;
  }
  public static function tick(): void {
    if (!self::ready()) return;
    if (!Cache::add('recurring.tick', 1, 300)) return;   // at most every 5 minutes
    self::run();
  }
}
