<?php
namespace App\Support;

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
  const KEYS = ['fc_steadfast_key', 'fc_steadfast_secret', 'fc_redx_token', 'fc_pathao_token'];
  public static function phone(string $p): string { $d = preg_replace('/\D/', '', $p); if (str_starts_with($d, '880')) $d = '0' . substr($d, 3); return substr($d, -11); }
  public static function configured(): array { return ['steadfast' => (bool)(Sms::secret('fc_steadfast_key') && Sms::secret('fc_steadfast_secret')), 'redx' => (bool)Sms::secret('fc_redx_token'), 'pathao' => (bool)Sms::secret('fc_pathao_token')]; }

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
    $cfg = self::configured(); $safe = fn($v) => $v ? (int)$v : 0; $res = ['steadfast' => [0, 0, 0, 'off'], 'redx' => [0, 0, 0, 'off'], 'pathao' => [0, 0, 0, 'off']];
    if (array_filter($cfg)) {
      try {
        $rs = Http::pool(fn($pool) => [
          $pool->as('s')->timeout(10)->withHeaders(['Api-Key' => (string)Sms::secret('fc_steadfast_key'), 'Secret-Key' => (string)Sms::secret('fc_steadfast_secret'), 'Content-Type' => 'application/json'])->get('https://portal.packzy.com/api/v1/fraud_check/score/' . $phone),
          $pool->as('r')->timeout(10)->withHeaders(['API-ACCESS-TOKEN' => 'Bearer ' . Sms::secret('fc_redx_token'), 'Accept' => 'application/json'])->get('https://openapi.redx.com.bd/v1.0.0-beta/customer/success_rate/' . $phone),
          $pool->as('p')->timeout(10)->withHeaders(['Content-Type' => 'application/json', 'accept' => 'application/json', 'Authorization' => 'Bearer ' . Sms::secret('fc_pathao_token')])->post('https://merchant.pathao.com/api/v1/user/success', ['phone' => $phone]),
        ]);
        $j = fn($k) => ($rs[$k] instanceof \Illuminate\Http\Client\Response && $rs[$k]->successful()) ? json_decode($rs[$k]->body()) : null;
        if ($cfg['steadfast']) { $x = $j('s'); $d = $safe($x->delivered_count ?? 0); $c = $safe($x->cancelled_count ?? 0); $res['steadfast'] = [$d + $c, $d, $c, $x ? 'ok' : 'error']; }
        if ($cfg['redx']) { $x = $j('r'); $d = $safe($x->deliveredParcels ?? 0); $t = $safe($x->totalParcels ?? 0); $res['redx'] = [$t, $d, max($t - $d, 0), $x ? 'ok' : 'error']; }
        if ($cfg['pathao']) { $x = $j('p'); $d = $safe($x->data->customer->successful_delivery ?? 0); $t = $safe($x->data->customer->total_delivery ?? 0); $res['pathao'] = [$t, $d, max($t - $d, 0), $x ? 'ok' : 'error']; }
      } catch (\Throwable $e) {}
    }
    $own = self::own($phone); $inc = Sms::secret('fc_internal') === '1';
    $del = array_sum(array_column($res, 1)) + ($inc ? $own['delivered'] : 0); $ret = array_sum(array_column($res, 2)) + ($inc ? $own['returned'] : 0); $tot = $del + $ret;
    $rate = $tot > 0 ? round($del / $tot * 100, 1) : 0.0; $rr = $tot > 0 ? round($ret / $tot * 100, 1) : 0.0; [$lv, $score] = self::level($tot, $rate);
    $out = ['ok' => true, 'phone' => $phone, 'total_parcel' => $tot, 'total_delivery' => $del, 'total_return' => $ret, 'success_rate' => $rate, 'return_rate' => $rr, 'risk_score' => $score, 'risk_level' => $lv,
      'label' => self::LEVELS[$lv][0], 'title' => self::LEVELS[$lv][1], 'bn' => self::LEVELS[$lv][2], 'color' => self::LEVELS[$lv][3], 'bg' => self::LEVELS[$lv][4], 'own' => $own, 'own_included' => $inc, 'configured' => $cfg,
      'couriers' => array_map(fn($a) => ['total' => $a[0], 'delivered' => $a[1], 'returned' => $a[2], 'status' => $a[3]], $res), 'at' => now()->toDateTimeString()];
    try { DB::table('fraud_checks')->insert(['phone' => $phone, 'risk_level' => $lv, 'risk_score' => $score, 'total' => $tot, 'delivered' => $del, 'returned' => $ret, 'success_rate' => $rate, 'data' => json_encode($out), 'checked_by' => auth()->user()->name ?? 'system', 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return $out;
  }
  /** Backward compatible simple stats. */
  public static function getCustomerOrderStats(string $phone): array { $r = self::check($phone); return ['total_delivery' => $r['total_delivery'] ?? 0, 'total_return' => $r['total_return'] ?? 0] + (($r['ok'] ?? false) ? $r : []); }
}
