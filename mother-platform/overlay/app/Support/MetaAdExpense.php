<?php
namespace App\Support;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Owner 24 Nov 2026: each day's Meta ad spend becomes ONE expense entry (purpose "Meta Ads") in the ledger, so it comes off the profit.
 *  One row per Dhaka day, reference META-ADS-YYYYMMDD (never duplicated). Today's row grows while the day runs; the last 3 days are re-read so late Meta corrections are picked up.
 *  Settings: meta_exp_on (1), meta_exp_account (money account id; default Brac Bank Limited), meta_exp_start (first day to post; default the day this was switched on), meta_ad_account. */
class MetaAdExpense {
  const PURPOSE = 'Meta Ads';
  public static function on(): bool { return Procure::set('meta_exp_on', '1') === '1'; }
  private static function acct(): ?int {
    $id = (int)Procure::set('meta_exp_account', '0');
    if ($id && DB::table('money_accounts')->where('id', $id)->exists()) return $id;
    $b = DB::table('money_accounts')->whereRaw("lower(name) = 'brac bank limited'")->value('id');
    return $b ? (int)$b : null;
  }
  private static function tokens(): array {
    $toks = []; try { if ($t = Sms::secret('chat_fb_token')) $toks['s'] = $t; foreach (DB::table('chat_pages')->whereNotNull('token')->limit(15)->get(['page_id', 'token']) as $p) { try { $toks['p' . $p->page_id] = \Illuminate\Support\Facades\Crypt::decryptString($p->token); } catch (\Throwable $e) {} } } catch (\Throwable $e) {}
    $pref = (string)Cache::get('lb.meta.tok', ''); if ($pref !== '' && isset($toks[$pref])) $toks = [$pref => $toks[$pref]] + $toks;
    return $toks;
  }
  /** [date => spend] for the last N Dhaka days, or null when Meta could not be read. */
  public static function fetch(int $days = 3): ?array {
    $acc = preg_replace('/\D/', '', (string)Procure::set('meta_ad_account', '2303729536822063')); if ($acc === '') return null;
    $to = Carbon::now('Asia/Dhaka'); $from = $to->copy()->subDays($days - 1);
    $n = 0;
    foreach (self::tokens() as $id => $t) {
      if (++$n > 4) break;
      try {
        $r = Http::timeout(15)->withToken($t)->get('https://graph.facebook.com/v21.0/act_' . $acc . '/insights', ['fields' => 'spend,date_start', 'time_increment' => 1, 'time_range' => json_encode(['since' => $from->toDateString(), 'until' => $to->toDateString()]), 'limit' => 20]);
        if (!$r->ok()) continue;
        Cache::put('lb.meta.tok', $id, 86400);
        $out = []; foreach ((array)$r->json('data') as $row) $out[(string)$row['date_start']] = round((float)($row['spend'] ?? 0), 2);
        return $out;
      } catch (\Throwable $e) {}
    }
    return null;
  }
  public static function tick(): void {
    if (!self::on() || !Cache::add('metaexp.tick', 1, 540)) return;
    $acc = self::acct();
    if (!$acc) { self::alert('No money account set for Meta ad expense', 'Settings meta_exp_account is empty and Brac Bank Limited was not found.'); return; }
    $start = Procure::set('meta_exp_start', ''); if ($start === '') { $start = Carbon::now('Asia/Dhaka')->toDateString(); Procure::put('meta_exp_start', $start); }
    $spend = self::fetch(3);
    if ($spend === null) { self::alert('Meta ad spend could not be read', 'The ad-spend expense for today was not updated: no saved Facebook token could read the ad account.'); return; }
    $pid = RecurringExpense::purpose(self::PURPOSE);
    foreach ($spend as $d => $amt) {
      if ($d < $start || $amt <= 0) continue;
      $ref = 'META-ADS-' . str_replace('-', '', $d);
      $row = DB::table('account_entries')->where('reference', $ref)->first();
      $note = 'Meta ads spend · ' . $d . ' (auto)';
      if ($row) { if (abs((float)$row->amount - $amt) >= 0.01) { DB::table('account_entries')->where('id', $row->id)->update(['amount' => $amt, 'comment' => $note, 'updated_at' => now()]); self::log($d, (float)$row->amount, $amt); } continue; }
      DB::table('account_entries')->insertOrIgnore(['reference' => $ref, 'direction' => 'expense', 'purpose_id' => $pid, 'account_id' => $acc, 'amount' => $amt, 'comment' => $note, 'entry_date' => $d, 'user_name' => 'System', 'created_at' => now(), 'updated_at' => now()]);
      self::log($d, 0, $amt);
    }
  }
  private static function log(string $d, float $old, float $new): void {
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'System', 'action' => 'expense.meta_ads_auto', 'subject' => 'Meta ads ' . $d . ': ৳' . number_format($old, 2) . ' → ৳' . number_format($new, 2), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  private static function alert(string $t, string $m): void {
    try { if (Cache::add('metaexp.alert', 1, 21600)) DeployHealth::raise('metaexp.fail', 'medium', 'Accounts', $t, $m, 'Token expired or setting missing.', 'Reconnect the Facebook page token or set the money account.'); } catch (\Throwable $e) {}
  }
}
