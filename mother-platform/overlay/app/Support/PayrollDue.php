<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** After each month ends (from the 1st of the next month) every active employee gets one unpaid payroll row for the completed month, due on the 5th. Fixed salary = salary; commission staff start at 0 and the amount is entered from the month's accounts. Idempotent. */
class PayrollDue {
  public static function purpose(string $payType): string { return ['commission' => 'Commission', 'both' => 'Salary + commission'][$payType] ?? 'Salary'; }
  public static function dueDate(string $mon): string { try { return \Carbon\Carbon::createFromFormat('Y-m-d', $mon.'-01')->addMonthNoOverflow()->day(5)->toDateString(); } catch (\Throwable $e) { return now('Asia/Dhaka')->toDateString(); } }
  /** Salary is for a completed month: it is generated after the month ends and paid by the 5th of the next month. */
  public static function lastMonth(): string { return now('Asia/Dhaka')->startOfMonth()->subMonthNoOverflow()->format('Y-m'); }
  public static function create(?string $mon = null): int {
    $mon = $mon ?: self::lastMonth(); $n = 0;
    foreach (DB::table('employees')->where('active', true)->get() as $e) {
      if (DB::table('payroll_entries')->where('employee_id', $e->id)->where('month', $mon)->exists()) continue;
      $pt = $e->pay_type ?? 'salary';
      DB::table('payroll_entries')->insert(['employee_id' => $e->id, 'month' => $mon, 'amount' => $pt === 'commission' ? 0 : $e->salary,
        'note' => $pt === 'both' ? "Fixed salary + commission: add this month's commission to the amount" : ($pt === 'commission' ? "Commission: enter this month's amount from the accounts" : null),
        'purpose' => self::purpose($pt), 'due_date' => self::dueDate($mon), 'status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]); $n++;
    }
    return $n;
  }
  /** Called from the background worker; creates the current month's dues automatically (at most once an hour). */
  public static function tick(): void {
    try { if (!Schema::hasTable('payroll_entries') || !Cache::add('payroll.due.tick', 1, 3600)) return; self::create(); } catch (\Throwable $e) { report($e); }
  }

  /** Paid salary reduces company profit: one expense entry (purpose "Salary") per payroll row, taken from the chosen account (default Brac Bank). Marking it unpaid again removes the entry. */
  public static function syncLedger(int $id): void {
    $p = DB::table('payroll_entries')->where('id', $id)->first(); if (!$p) return;
    $ref = 'SAL-'.str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    if ($p->status !== 'paid') { DB::table('account_entries')->where('reference', $ref)->delete(); DB::table('payroll_entries')->where('id', $id)->update(['entry_ref' => null]); return; }
    $acc = $p->account_id ?: DB::table('money_accounts')->where('active', true)->whereRaw("lower(name) = 'brac bank limited'")->value('id') ?: DB::table('money_accounts')->where('active', true)->whereRaw("lower(name) like '%brac%'")->orderByRaw("lower(name) like '%limited%' desc")->orderBy('id')->value('id') ?: DB::table('money_accounts')->where('active', true)->where('type', 'bank')->orderBy('id')->value('id');
    if (!$acc) return;
    $e = DB::table('employees')->where('id', $p->employee_id)->first();
    $row = ['reference' => $ref, 'direction' => 'expense', 'purpose_id' => \App\Support\RecurringExpense::purpose('Salary'), 'account_id' => $acc, 'amount' => $p->amount,
      'comment' => 'Salary · '.($e->name ?? '#'.$p->employee_id).' · '.\Carbon\Carbon::createFromFormat('Y-m-d', $p->month.'-01')->format('M Y').' · '.($p->purpose ?: 'Salary'),
      'entry_date' => $p->paid_at ?: now('Asia/Dhaka')->toDateString(), 'user_name' => optional(\Illuminate\Support\Facades\Auth::user())->name ?: 'Payroll', 'updated_at' => now()];
    if (DB::table('account_entries')->where('reference', $ref)->exists()) DB::table('account_entries')->where('reference', $ref)->update($row);
    else DB::table('account_entries')->insert($row + ['created_at' => now()]);
    DB::table('payroll_entries')->where('id', $id)->update(['entry_ref' => $ref, 'account_id' => $acc]);
  }
}
