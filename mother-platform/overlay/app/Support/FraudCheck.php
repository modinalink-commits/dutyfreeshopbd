<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Customer courier reliability: Steadfast + RedX + Pathao history combined into success/return rate and a risk level. Keys live encrypted in integration_secrets (never shown). */
class FraudCheck
{
  const LEVELS = [
    'very_low'  => ['Very Low Risk', 'Excellent Customer', 'অত্যন্ত নিরাপদ', '#166534', '#DCFCE7', 10],
    'low'       => ['Low Risk', 'Good Customer', 'নিরাপদ', '#4D7C0F', '#ECFCCB', 25],
    'medium'    => ['Medium Risk', 'Medium Risk', 'মাঝারি ঝুঁকি', '#C2410C', '#FFEDD5', 50],
    'high'      => ['High Risk', 'High Risk', 'উচ্চ ঝুঁকি', '#9A3412', '#FED7AA', 75],
    'very_high' => ['Very High Risk', 'Very High Risk', 'অত্যন্ত উচ্চ ঝুঁকি', '#B91C1C', '#FEE2E2', 90],
    'unknown'   => ['Unknown', 'No Courier History', 'কুরিয়ার হিস্ট্রি পাওয়া যায়নি', '#4B5563', '#F3F4F6', null],
  ];
  const KEYS = ['fc_steadfast_key', 'fc_steadfast_secret', 'fc_redx_token', 'fc_pathao_token', 'fc_bdcourier_key', 'fc_smartpos_key'];
  /** Fraud-check token, or the RedX courier API token when none was entered separately. */
  public static function redxToken(): string { $t = trim((string)(Sms::secret('fc_redx_token') ?: Sms::secret('redx_token'))); return preg_replace('/^bearer\s+/i', '', $t); }
  public static function phone(string $p): string { $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '880')) $d = '0' . substr($d, 3); return substr($d, -11); }
  public static function configured(): array { return ['steadfast' => (bool)(Sms::secret('fc_steadfast_key') && Sms::secret('fc_steadfast_secret')), 'redx' => self::redxToken() !== '', 'pathao' => (bool)Sms::secret('fc_pathao_token'), 'bdcourier' => trim((string)Sms::secret('fc_bdcourier_key')) !== '', 'smartpos' => trim((string)Sms::secret('fc_smartpos_key')) !== '']; }

  public static function level(int $total, float $rate): array {
    if ($total <= 0) return ['unknown', null];
    $k = $rate >= 90 ? 'very_low' : ($rate >= 80 ? 'low' : ($rate >= 65 ? 'medium' : ($rate >= 50 ? 'high' : 'very_high')));
    return [$k, self::LEVELS[$k][5]];
  }

  /** Own store history for this phone (shown separately, not mixed into courier numbers unless fc_internal = 1) */
  public static function own(string $phone): array {
    $r = DB::table('orders')->whereRaw('right(phone,11) = ?', [$phone])->selectRaw("count(*) filter (where status = 'Delivered') as d, count(*) filter (where status in ('Returned','Cancelled','Canceled','Return')) as r")->first();
    return ['delivered' => (int)($r->d ?? 0), 'returned' => (int)($r->r ?? 0)];
  }

  /** Full check. Cached 6h unless $force. */
  public static function check(string $phone, bool $force = false): array {
    $phone = self::phone($phone); if (!preg_match('/^01\d{9}$/', $phone)) return ['ok' => false, 'error' => 'Invalid mobile number'];
    if (!$force) { $c = DB::table('fraud_checks')->where('phone', $phone)->where('created_at', '>', now()->subHours(6))->orderByDesc('id')->first(); if ($c && $c->data) return json_decode($c->data, true) + ['cached' => true]; }
    $http = []; $cfg = self::configured(); $ptok = (string)(Sms::secret('fc_pathao_token') ?: (Pathao::configured() ? Pathao::token() : '')); $cfg['pathao'] = $ptok !== ''; $psrc = 'off'; $safe = fn($v) => $v ? (int)$v : 0; $res = ['steadfast' => [0, 0, 0, 'off'], 'redx' => [0, 0, 0, 'off'], 'pathao' => [0, 0, 0, 'off']];
    $bdFail = null; $spOk = false; $src = 'direct'; $bdOnly = $cfg['bdcourier'] || $cfg['smartpos']; // an all-courier service (BD Courier or SmartPOS) active = the only fraud source (direct Steadfast / RedX / Pathao calls are off)
    $spSkipped = $cfg['smartpos'] && $cfg['bdcourier'] && Cache::get('fraud.sp.skip'); // SmartPOS (main) just failed and BD Courier (backup) answered: go straight to BD Courier for 2 minutes, then try SmartPOS again
    if (!$bdOnly && array_filter(array_diff_key($cfg, ['bdcourier' => 1, 'smartpos' => 1]))) {
      try {
        $rs = Http::pool(fn($pool) => [
          $pool->as('s')->timeout(10)->withHeaders(['Api-Key' => (string)Sms::secret('fc_steadfast_key'), 'Secret-Key' => (string)Sms::secret('fc_steadfast_secret'), 'Content-Type' => 'application/json'])->get('https://portal.packzy.com/api/v1/fraud_check/score/' . $phone),
          $pool->as('r')->timeout(10)->withHeaders(['API-ACCESS-TOKEN' => 'Bearer ' . self::redxToken(), 'Accept' => 'application/json'])->get('https://openapi.redx.com.bd/v1.0.0-beta/customer/success_rate/' . $phone),
          $pool->as('p')->timeout(10)->withHeaders(['Content-Type' => 'application/json', 'accept' => 'application/json', 'Authorization' => 'Bearer ' . $ptok])->post('https://merchant.pathao.com/api/v1/user/success', ['phone' => $phone]),
        ]);
        foreach (['s' => 'steadfast', 'r' => 'redx', 'p' => 'pathao'] as $hk => $hn) $http[$hn] = $rs[$hk] instanceof \Illuminate\Http\Client\Response ? $rs[$hk]->status() : 0;
        $j = fn($k) => ($rs[$k] instanceof \Illuminate\Http\Client\Response && $rs[$k]->successful()) ? json_decode($rs[$k]->body()) : null;
        if ($cfg['steadfast']) { $x = $j('s'); $d = $safe($x->delivered_count ?? 0); $c = $safe($x->cancelled_count ?? 0); $res['steadfast'] = [$d + $c, $d, $c, $x ? 'ok' : 'error']; }
        if ($cfg['redx']) { $x = $j('r'); $d = $safe($x->deliveredParcels ?? 0); $t = $safe($x->totalParcels ?? 0); $res['redx'] = [$t, $d, max($t - $d, 0), $x ? 'ok' : 'error']; }
        if ($cfg['pathao']) { $x = $j('p'); $d = $safe($x->data->customer->successful_delivery ?? 0); $t = $safe($x->data->customer->total_delivery ?? 0); $res['pathao'] = [$t, $d, max($t - $d, 0), $x ? 'ok' : 'error']; }
        if ($cfg['pathao'] && $res['pathao'][3] === 'ok') $psrc = 'network';
      } catch (\Throwable $e) {}
    }
    // SmartPOS Free Fraud Checker (fraudchecker.smartposbd.com) is the MAIN source (premium plan, 1000 checks a day); BD Courier is the automatic backup below.
    $spFail = null;
    if ($cfg['smartpos'] && !$spSkipped) {
      $sp = self::smartpos($phone); $http['smartpos'] = $sp[3];
      if ($sp[0]) { $spOk = true; $src = 'smartpos'; foreach ($sp[1] as $bk => $v) { if (!isset($res[$bk])) $res[$bk] = [0, 0, 0, 'off']; if ($v[0] > ($res[$bk][0] ?? 0) || $res[$bk][3] !== 'ok') $res[$bk] = $v; if ($bk === 'pathao' && $v[0] > 0) $psrc = 'network'; } }
      else { $spFail = $sp[2]; if ($cfg['bdcourier']) Cache::put('fraud.sp.skip', 1, 120); }
      try { if ($spFail !== null) { $n = (int)Cache::get('fraud.sp.fails', 0) + 1; Cache::put('fraud.sp.fails', $n, 900);
          if ($n >= 3) { Cache::put('fraud.sp.alert', 1, 86400 * 3); DeployHealth::raise('fraud_sp', $cfg['bdcourier'] ? 'medium' : 'high', 'Fraud check', 'SmartPOS fraud check is failing', 'The main fraud check could not read SmartPOS (' . trim($spFail) . ').' . ($cfg['bdcourier'] ? ' BD Courier is answering instead.' : ' Customers are NOT being treated as clean while this lasts.'), 'Daily quota finished, key problem or outage', 'Open Services and Balances, look at the SmartPOS row, and renew the key or quota.'); } }
        else { Cache::forget('fraud.sp.fails'); if (Cache::pull('fraud.sp.alert')) DeployHealth::resolve('fraud_sp', 'System (auto)', true); } } catch (\Throwable $e) {}
    }
    // BD Courier (bdcourier.com), the backup when SmartPOS is not saved, failed or is skipped: one call returns Pathao, Steadfast, RedX, ParcelDex, CourierFast, PaperFly and CarryBee. Where we already have a direct number, the larger total wins (no double counting); the other couriers are added.
    if ($cfg['bdcourier'] && !$spOk) { $src = 'bdcourier';
      try {
        // One slow answer must not count as a failure: a connection error or timeout is tried a second time before giving up.
        $bdCall = fn() => Http::timeout(12)->acceptJson()->withToken(trim((string)Sms::secret('fc_bdcourier_key')))->post('https://api.bdcourier.com/courier-check', ['phone' => $phone]);
        try { $br = $bdCall(); } catch (\Illuminate\Http\Client\ConnectionException $e) { usleep(400000); $br = $bdCall(); }
        $http['bdcourier'] = $br->status();
        $bj = $br->successful() ? $br->json() : null;
        if (!(is_array($bj) && ($bj['status'] ?? '') === 'success' && is_array($bj['data'] ?? null))) $bdFail = 'HTTP ' . $br->status() . ' ' . mb_substr(trim(strip_tags((string)(is_array($br->json()) ? ($br->json()['message'] ?? '') : ''))), 0, 80);
        if (is_array($bj) && ($bj['status'] ?? '') === 'success' && is_array($bj['data'] ?? null)) foreach ($bj['data'] as $bk => $bv) {
          if (!is_array($bv) || !isset($bv['total_parcel'])) continue; $bk = strtolower((string)$bk); if ($bk === 'summary') continue; $bt = $safe($bv['total_parcel']); $bd = $safe($bv['success_parcel'] ?? 0); $bc = $safe($bv['cancelled_parcel'] ?? 0);
          if (!isset($res[$bk])) $res[$bk] = [0, 0, 0, 'off'];
          if ($bt > ($res[$bk][0] ?? 0) || $res[$bk][3] !== 'ok') { $res[$bk] = [$bt, $bd, $bc, 'ok']; if ($bk === 'pathao' && $bt > 0) $psrc = 'network'; }
        }
      } catch (\Throwable $e) { $http['bdcourier'] = 0; $bdFail = 'no connection to BD Courier'; }
    }
    // A failed fraud check must never look like "no courier history" (that reads as a clean customer): report the failure, do not store it, and raise an alert.
    if (!$spOk && ($bdFail !== null || ($cfg['smartpos'] && !$cfg['bdcourier']))) {
      $fail = $bdFail ?? ('SmartPOS: ' . $spFail);
      if ($bdFail !== null) {
      // The alert is for a real outage, not one bad call: it is raised at the third failure in 15 minutes and closes itself at the next good answer.
      try { $bdN = (int)Cache::get('fraud.bd.fails', 0) + 1; Cache::put('fraud.bd.fails', $bdN, 900);
        if ($bdN >= 3) { Cache::put('fraud.bd.alert', 1, 86400 * 3); DeployHealth::raise('fraud_bd', 'high', 'Fraud check', 'BD Courier fraud check is failing', 'The fraud check could not read BD Courier (' . trim($bdFail) . ').' . ($cfg['smartpos'] ? ' SmartPOS is not answering either.' : '') . ' Customers are NOT being treated as clean while this lasts.', 'BD Courier limit reached, key problem or outage', 'Open Services and Balances, look at the BD Courier row, and recharge or renew if the plan ended.'); } } catch (\Throwable $e) {}
      }
      return ['ok' => false, 'error' => 'Fraud check failed (' . trim($fail) . ($bdFail !== null && $spFail !== null ? '; SmartPOS: ' . trim($spFail) : '') . '). Not treated as a clean customer; try again.', 'bd_failed' => true, 'http' => $http, 'phone' => $phone];
    }
    if ($cfg['bdcourier'] && !$spOk && $bdFail === null) { try { Cache::forget('fraud.bd.fails');
      if (Cache::pull('fraud.bd.alert') || Cache::add('fraud.bd.swept', 1, 3600)) DeployHealth::resolve('fraud_bd', 'System (auto)', true);
    } catch (\Throwable $e) {} }
    // Pathao fallback: when Pathao's network score is unavailable, use this store's own Pathao parcels for the number (official API, our synced parcel results).
    if (!$bdOnly && ($res['pathao'][3] !== 'ok' || $res['pathao'][0] === 0)) {
      $pp = DB::table('orders')->whereNotNull('consignment_id')->whereRaw('right(phone,11) = ?', [$phone])->selectRaw("count(*) filter (where courier_stage = 'Delivered') as d, count(*) filter (where courier_stage in ('Returned','Cancelled','Failed Delivery')) as r")->first();
      $pd = (int)($pp->d ?? 0); $pr = (int)($pp->r ?? 0);
      if ($pd + $pr > 0 || $cfg['pathao']) { $res['pathao'] = [$pd + $pr, $pd, $pr, 'ok']; $psrc = 'own_parcels'; }
    }
    $own = self::own($phone); $inc = Sms::secret('fc_internal') === '1';
    $del = array_sum(array_column($res, 1)) + ($inc ? $own['delivered'] : 0); $ret = array_sum(array_column($res, 2)) + ($inc ? $own['returned'] : 0); $tot = $del + $ret;
    $rate = $tot > 0 ? round($del / $tot * 100, 1) : 0.0; $rr = $tot > 0 ? round($ret / $tot * 100, 1) : 0.0; [$lv, $score] = self::level($tot, $rate);
    $out = ['ok' => true, 'phone' => $phone, 'total_parcel' => $tot, 'total_delivery' => $del, 'total_return' => $ret, 'success_rate' => $rate, 'return_rate' => $rr, 'risk_score' => $score, 'risk_level' => $lv,
      'label' => self::LEVELS[$lv][0], 'title' => self::LEVELS[$lv][1], 'bn' => self::LEVELS[$lv][2], 'color' => self::LEVELS[$lv][3], 'bg' => self::LEVELS[$lv][4], 'own' => $own, 'own_included' => $inc, 'configured' => $cfg,
      'couriers' => array_map(fn($a) => ['total' => $a[0], 'delivered' => $a[1], 'returned' => $a[2], 'status' => $a[3]], $res), 'pathao_source' => $psrc, 'source' => $src, 'http' => $http, 'at' => now()->toDateTimeString()];
    try { DB::table('fraud_checks')->insert(['phone' => $phone, 'risk_level' => $lv, 'risk_score' => $score, 'total' => $tot, 'delivered' => $del, 'returned' => $ret, 'success_rate' => $rate, 'data' => json_encode($out), 'checked_by' => auth()->user()->name ?? 'system', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return $out;
  }
  /** SmartPOS Free Fraud Checker (fraudchecker.smartposbd.com): one call returns Steadfast, Pathao, RedX, CarryBee and Paperfly. Returns [ok, [courier => [total, delivered, returned, 'ok']], error text, http status]. */
  public static function smartpos(string $phone): array {
    $k = trim((string)Sms::secret('fc_smartpos_key')); if ($k === '') return [false, [], 'key not set', 0];
    try {
      $call = fn() => Http::timeout(12)->acceptJson()->withHeaders(['X-API-KEY' => $k, 'X-Domain' => 'dutyfreebd.com'])->post('https://fraudchecker.smartposbd.com/api/v1/fraud-check', ['phone' => $phone]);
      try { $r = $call(); } catch (\Illuminate\Http\Client\ConnectionException $e) { usleep(400000); $r = $call(); }
      if ($r->status() === 429) { sleep(1); $r = $call(); }
      $j = $r->json();
      if (!$r->successful() || !is_array($j) || !is_array($j['response'] ?? null)) return [false, [], 'HTTP ' . $r->status() . ' ' . mb_substr(trim(strip_tags((string)(is_array($j) ? ($j['message'] ?? $j['error'] ?? '') : ''))), 0, 80), $r->status()];
      $out = [];
      foreach ($j['response'] as $name => $v) {
        $d = is_array($v) ? ($v['data'] ?? null) : null; if (!is_array($d) || !isset($d['total']) || ($v['status'] ?? true) === false) continue;
        $t = (int)$d['total']; $ok = (int)($d['success'] ?? 0); $c = (int)($d['cancel'] ?? max($t - $ok, 0));
        $out[strtolower((string)$name)] = [$t, $ok, $c, 'ok'];
      }
      return [true, $out, '', $r->status()];
    } catch (\Throwable $e) { return [false, [], 'no connection to SmartPOS', 0]; }
  }
  /** Backward compatible simple stats. */
  public static function getCustomerOrderStats(string $phone): array { $r = self::check($phone); return ['total_delivery' => $r['total_delivery'] ?? 0, 'total_return' => $r['total_return'] ?? 0] + (($r['ok'] ?? false) ? $r : []); }
}
