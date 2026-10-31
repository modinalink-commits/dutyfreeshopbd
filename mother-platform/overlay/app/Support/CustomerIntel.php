<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Customer category, order history, reliability and fraud/risk summary for the admin order screens. */
class CustomerIntel {
  const DELIVERED = ['Delivered','Partial Delivered'];
  const RETURNED  = ['Return Pending','Returned','Exchange Received'];
  const CANCELLED = ['Cancel'];
  /** @param iterable $orders rows with phone, total, id; returns [orderId => summary] */
  public static function forOrders(iterable $orders): array {
    $orders = collect($orders); $phones = $orders->pluck('phone')->filter()->unique()->values();
    if ($phones->isEmpty()) return [];
    $d = "'".implode("','", array_map(fn($s)=>str_replace("'","''",$s), self::DELIVERED))."'";
    $r = "'".implode("','", self::RETURNED)."'"; $c = "'Cancel'";
    $stats = DB::table('orders')->whereIn('phone', $phones)->select('phone',
      DB::raw('count(*) total'),
      DB::raw("sum(case when status in ($d) then 1 else 0 end) delivered"),
      DB::raw("sum(case when status in ($r) then 1 else 0 end) returned"),
      DB::raw("sum(case when status in ($c) then 1 else 0 end) cancelled"),
      DB::raw("sum(case when status in ($d) then total else 0 end) spent"),
      DB::raw("sum(case when created_at > now() - interval '24 hours' then 1 else 0 end) r24"))
      ->groupBy('phone')->get()->keyBy('phone');
    $cust = DB::table('customers')->whereIn('phone', $phones)->orderBy('id')->get()->unique('phone')->keyBy('phone');
    $out = [];
    foreach ($orders as $o) {
      $s = $stats[$o->phone] ?? null; $cu = $cust[$o->phone] ?? null;
      $tot = (int)($s->total ?? 1); $del = (int)($s->delivered ?? 0); $ret = (int)($s->returned ?? 0); $can = (int)($s->cancelled ?? 0);
      $legacy = (int)($cu->legacy_orders ?? 0); $spent = (float)($s->spent ?? 0) + (float)($cu->legacy_spent ?? 0);
      $pend = max(0, $tot - $del - $ret - $can); $all = $tot + $legacy;
      $fin = $del + $ret + $can; $rate = $fin > 0 ? round($del / $fin * 100) : null;
      $blocked = (bool)($cu->blocked ?? false);
      // reliability: delivery record smoothed toward a neutral prior so one order doesn't swing it
      $rel = (int)round(($del + 0.7 * 3) / ($fin + 3) * 100) - ($blocked ? 30 : 0);
      $rel = max(0, min(100, $rel));
      // fraud score: higher = riskier
      $f = min(60, $ret * 12 + $can * 8);
      if ($blocked) $f += 40;
      if ((int)($s->r24 ?? 0) >= 3) $f += 15;
      if ($all <= 1 && (float)$o->total >= 30000) $f += 10;
      if ($fin >= 3 && $rate !== null && $rate < 50) $f += 15;
      $f = max(0, min(100, $f));
      $risk = $f >= 60 ? 'High' : ($f >= 30 ? 'Medium' : 'Low');
      if ($blocked || ($fin >= 3 && $rate < 50 && ($ret + $can) >= 3)) $cat = 'Watchlist';
      elseif ($del >= 10 && $rate !== null && $rate >= 85) $cat = 'VIP';
      elseif ($spent >= 50000) $cat = 'High Value';
      elseif ($all <= 1) $cat = 'New';
      else $cat = 'Regular';
      $out[$o->id] = ['cat'=>$cat, 'cid'=>$cu ? 'C-'.str_pad($cu->id, 5, '0', STR_PAD_LEFT) : '—', 'cuid'=>$cu->id ?? null,
        'total'=>$all, 'delivered'=>$del, 'returned'=>$ret, 'cancelled'=>$can, 'pending'=>$pend, 'legacy'=>$legacy, 'rate'=>$rate,
        'rel'=>$rel, 'fraud'=>$f, 'risk'=>$risk, 'spent'=>$spent,
        'verdict'=>$risk === 'Low' ? 'safe' : ($risk === 'Medium' ? 'call' : 'hold')];
    }
    return $out;
  }
  public static function wa(string $phone): string { return '88'.ltrim(preg_replace('/\D/', '', $phone), '8'); }
}
