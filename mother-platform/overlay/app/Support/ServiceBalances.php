<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/** Services and Balances: reads the balance of every service that has a balance API, keeps typed-in balances and renewal dates for the rest, and raises an Issues Center alert when something is low or about to expire. Nothing here is estimated. */
class ServiceBalances {
  public const EVERY = 1800; // seconds between automatic checks

  public static function ready(): bool { return Schema::hasTable('service_accounts'); }

  /** Read one auto service. Returns ['ok'=>bool,'balance'=>?float,'expires'=>?string,'text'=>string]. */
  public static function fetch(string $code): array {
    try {
      if ($code === 'alpha_sms' || $code === 'bulk_sms') {
        $p = $code === 'alpha_sms' ? 'alpha' : 'bulk';
        if (!SmsGate::hasKey($p)) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'API key not set'];
        $b = SmsGate::refreshBalance($p);
        if (!$b['ok']) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => mb_substr((string)$b['text'], 0, 180)];
        $n = preg_replace('/[^0-9.\-]/', '', (string)$b['text']);
        return $n === '' ? ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'Unreadable answer: ' . mb_substr((string)$b['text'], 0, 120)] : ['ok' => true, 'balance' => (float)$n, 'expires' => null, 'text' => 'Read from provider'];
      }
      if ($code === 'bdcourier') { // BDCourier "My Plan": plan expiry and the checks left today (the allowance resets every day)
        $k = trim((string)Sms::secret('fc_bdcourier_key'));
        if ($k === '') return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'API key not set'];
        $r = Http::timeout(15)->withToken($k)->acceptJson()->get('https://api.bdcourier.com/my-plan');
        $j = $r->json(); $dt = is_array($j) ? ($j['data'] ?? null) : null;
        if ($r->status() === 401 || $r->status() === 403) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'BDCourier did not accept the key (HTTP ' . $r->status() . ')'];
        if (!$r->ok() || !is_array($dt)) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'No readable answer (HTTP ' . $r->status() . ')'];
        if (empty($dt['has_subscription'])) return ['ok' => true, 'balance' => (float)((int)($dt['remaining_free_calls'] ?? 0)), 'expires' => null, 'text' => 'No paid plan: free checks only'];
        $left = (int)($dt['remaining_free_calls'] ?? 0) + (int)($dt['remaining_paid_calls'] ?? 0);
        $exp = !empty($dt['expires_at']) ? substr((string)$dt['expires_at'], 0, 10) : (!empty($dt['next_due_date']) ? (string)$dt['next_due_date'] : null);
        return ['ok' => true, 'balance' => (float)$left, 'expires' => $exp, 'text' => ($dt['plan_name'] ?? 'Plan') . ' (' . ($dt['status'] ?? '?') . '): checks left today ' . $left . ', resets daily'];
      }
      if ($code === 'smartpos') { // SmartPOS Free Fraud Checker: monthly quota
        $k = trim((string)Sms::secret('fc_smartpos_key'));
        if ($k === '') return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'API key not set'];
        $r = Http::timeout(15)->withHeaders(['X-API-KEY' => $k, 'X-Domain' => 'dutyfreebd.com'])->acceptJson()->get('https://fraudchecker.smartposbd.com/api/v1/quota');
        if ($r->status() === 401 || $r->status() === 403) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'SmartPOS did not accept the key (HTTP ' . $r->status() . ')'];
        $j = $r->json(); $dt = is_array($j) ? ($j['data'] ?? null) : null;
        if (!$r->ok() || !is_array($dt) || !isset($dt['remaining'])) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'No readable answer (HTTP ' . $r->status() . ')'];
        return ['ok' => true, 'balance' => (float)(int)$dt['remaining'], 'expires' => !empty($dt['resets_at']) ? substr((string)$dt['resets_at'], 0, 10) : null, 'text' => 'Main fraud check: ' . (int)($dt['used'] ?? 0) . ' of ' . (int)($dt['total'] ?? 0) . ' checks used (' . str_replace('_', ' ', (string)($dt['type'] ?? 'quota')) . '); resets on the date shown'];
      }
      if ($code === 'tok_fc_pathao' || $code === 'tok_fc_redx') { // API tokens: the expiry date is inside the token (JWT "exp"), nothing is guessed
        $tok = $code === 'tok_fc_pathao' ? trim((string)Sms::secret('fc_pathao_token')) : FraudCheck::redxToken();
        $tok = preg_replace('/^bearer\s+/i', '', $tok);
        if ($tok === '') return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'Token not set'];
        $p = explode('.', $tok);
        $j = count($p) === 3 ? json_decode((string)base64_decode(strtr($p[1], '-_', '+/') . str_repeat('=', (4 - strlen($p[1]) % 4) % 4)), true) : null;
        if (!is_array($j)) return ['ok' => true, 'balance' => null, 'expires' => null, 'text' => 'Token is set; it carries no readable expiry date'];
        if (empty($j['exp'])) return ['ok' => true, 'balance' => null, 'expires' => null, 'text' => 'Token is set; it has no expiry date'];
        return ['ok' => true, 'balance' => null, 'expires' => gmdate('Y-m-d', (int)$j['exp']), 'text' => 'Expiry read from the token'];
      }
      if ($code === 'elevenlabs') {
        $k = Sms::secret('vchat_eleven_key') ?: Sms::secret('vc_tts_key');
        if (!$k) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'API key not set'];
        $r = Http::timeout(15)->withHeaders(['xi-api-key' => $k])->get('https://api.elevenlabs.io/v1/user/subscription');
        if ($r->status() === 401 || $r->status() === 403) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'The key may not read the account (needs the "User: read" permission)'];
        $j = $r->json();
        if (!$r->ok() || !is_array($j) || !isset($j['character_limit'])) return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'No readable answer (HTTP ' . $r->status() . ')'];
        $left = max(0, (int)$j['character_limit'] - (int)($j['character_count'] ?? 0));
        $exp = !empty($j['next_character_count_reset_unix']) ? gmdate('Y-m-d', (int)$j['next_character_count_reset_unix']) : null;
        return ['ok' => true, 'balance' => (float)$left, 'expires' => $exp, 'text' => 'Plan ' . ($j['tier'] ?? '') . ' · ' . number_format((int)($j['character_count'] ?? 0)) . ' of ' . number_format((int)$j['character_limit']) . ' used; resets on the date shown'];
      }
    } catch (\Throwable $e) { return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'Check failed: ' . mb_substr($e->getMessage(), 0, 120)]; }
    return ['ok' => false, 'balance' => null, 'expires' => null, 'text' => 'No automatic check for this service'];
  }

  /** Check one row now and store the result. */
  public static function refresh(object $row): array {
    if ($row->kind !== 'auto' || !$row->code) return ['ok' => false, 'text' => 'Manual service'];
    $f = self::fetch($row->code); $u = ['last_ok' => $f['ok'], 'last_text' => mb_substr($f['text'], 0, 200), 'checked_at' => now(), 'updated_at' => now()];
    if ($f['ok']) { $u['balance'] = $f['balance']; if ($f['expires']) $u['expires_on'] = $f['expires']; }
    DB::table('service_accounts')->where('id', $row->id)->update($u);
    if ($f['ok']) self::logPoint((int)$row->id, (float)$f['balance']);
    return $f;
  }

  public static function hasLog(): bool { return Schema::hasTable('service_balance_log'); }

  /** Remember a balance reading. Kept when the balance changed or the last reading is older than 6 hours, so the table stays small. */
  public static function logPoint(int $id, float $balance): void {
    try {
      if (!self::hasLog()) return;
      $last = DB::table('service_balance_log')->where('service_id', $id)->orderByDesc('at')->orderByDesc('id')->first();
      if ($last && abs((float)$last->balance - $balance) < 0.005 && \Carbon\Carbon::parse($last->at, 'UTC')->gt(now()->subHours(6))) return;
      DB::table('service_balance_log')->insert(['service_id' => $id, 'balance' => $balance, 'at' => now()]);
    } catch (\Throwable $e) {}
  }

  /** Real daily use from the readings of the last 14 days: only drops count (a rise is a recharge or a new plan period). Needs at least 12 hours of history.
   *  Returns ['perDay'=>?float, 'days'=>?int (how long the balance lasts at that pace), 'since'=>?string]. Nothing is guessed. */
  public static function usage(object $r): array {
    $none = ['perDay' => null, 'days' => null, 'since' => null];
    try {
      if (!self::hasLog() || $r->balance === null) return $none;
      $pts = DB::table('service_balance_log')->where('service_id', $r->id)->where('at', '>=', now()->subDays(14))->orderBy('at')->orderBy('id')->get();
      if ($pts->count() < 2) return $none;
      $first = \Carbon\Carbon::parse($pts->first()->at, 'UTC'); $last = \Carbon\Carbon::parse($pts->last()->at, 'UTC');
      $hours = $first->diffInMinutes($last) / 60; if ($hours < 12) return $none;
      $drop = 0.0; for ($i = 1; $i < $pts->count(); $i++) { $d = (float)$pts[$i - 1]->balance - (float)$pts[$i]->balance; if ($d > 0) $drop += $d; }
      $perDay = $drop / ($hours / 24);
      if ($perDay <= 0.0001) return ['perDay' => 0.0, 'days' => null, 'since' => $first->copy()->timezone('Asia/Dhaka')->format('d M')];
      return ['perDay' => $perDay, 'days' => (int)floor(max(0, (float)$r->balance) / $perDay), 'since' => $first->copy()->timezone('Asia/Dhaka')->format('d M')];
    } catch (\Throwable $e) { return $none; }
  }

  /** low / soon / expired / failed / ok / unset */
  public static function state(object $r): array {
    $today = now('Asia/Dhaka')->startOfDay(); $days = $r->expires_on ? (int)$today->diffInDays(\Carbon\Carbon::parse($r->expires_on, 'Asia/Dhaka')->startOfDay(), false) : null;
    if ($r->kind === 'auto' && $r->last_ok === false && $r->checked_at) return ['failed', 'Check failed', $days];
    if ($days !== null && $days < 0) return ['expired', 'Expired', $days];
    if ($r->balance !== null && (float)$r->balance <= 0) return ['low', 'Empty', $days];
    if ($r->balance !== null && $r->low_at !== null && (float)$r->balance <= (float)$r->low_at) return ['low', 'Low', $days];
    if ($days !== null && $days <= (int)$r->remind_days) return ['soon', 'Renew soon', $days];
    if ($r->kind === 'auto' && $r->last_ok === true && $r->balance === null && $days === null) return ['ok', 'OK', $days]; // key present and accepted, nothing that runs out
    if ($r->balance === null && $days === null) return ['unset', 'Not set', $days];
    return ['ok', 'OK', $days];
  }

  /** Alert in the Issues Center, once per service per day per kind of problem. */
  public static function alertFor(object $r): void {
    [$s, $label, $days] = self::state($r); $d = date('Ymd');
    $bal = $r->balance !== null ? number_format((float)$r->balance, 2) . ' ' . $r->unit : 'unknown';
    try {
      if ($s === 'low') DeployHealth::raise('svc_low_' . $r->id . '_' . $d, 'high', 'Services', $r->name . ': balance ' . strtolower($label), $r->name . ' has ' . $bal . ($r->low_at !== null ? ' (alert limit ' . number_format((float)$r->low_at, 2) . ')' : '') . '.', 'Balance at or below the limit', 'Recharge ' . $r->name . ', then update the balance on Services and Balances if it is typed in by hand.');
      elseif ($s === 'expired') DeployHealth::raise('svc_exp_' . $r->id . '_' . $d, 'high', 'Services', $r->name . ' has expired', $r->name . ' expired on ' . $r->expires_on . '.', 'Renewal date passed', 'Renew ' . $r->name . ' and update the date on Services and Balances.');
      elseif ($s === 'soon') DeployHealth::raise('svc_soon_' . $r->id . '_' . $d, 'medium', 'Services', $r->name . ' renews in ' . $days . ' day(s)', $r->name . ' is due on ' . $r->expires_on . '.', 'Renewal date is near', 'Renew ' . $r->name . ' before ' . $r->expires_on . '.');
    } catch (\Throwable $e) {}
  }

  /** Worker hook: every 30 minutes refresh the auto services and raise alerts for all. */
  public static function tick(): void {
    if (!self::ready() || !Cache::add('svcbal.tick', 1, self::EVERY)) return;
    try { if (self::hasLog()) DB::table('service_balance_log')->where('at', '<', now()->subDays(60))->delete(); } catch (\Throwable $e) {}
    foreach (DB::table('service_accounts')->where('active', true)->get() as $r) {
      if ($r->kind === 'auto') { try { self::refresh($r); } catch (\Throwable $e) {} $r = DB::table('service_accounts')->where('id', $r->id)->first(); }
      self::alertFor($r);
    }
  }
}
