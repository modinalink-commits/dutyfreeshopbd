<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Dashboard "Courier Performance": the numbers of the Courier Dashboard, for the ACTIVE couriers only. */
class CourierOverview
{
  /** names of couriers switched on in Courier settings (Pathao counts as on until a settings row says otherwise) */
  public static function active(): array {
    $rows = DB::table('couriers')->pluck('active', 'name')->all(); $out = [];
    foreach (['Pathao', 'Carrybee', 'RedX', 'Steadfast'] as $n) { if (array_key_exists($n, $rows) ? (bool)$rows[$n] : $n === 'Pathao') $out[] = $n; }
    return $out;
  }

  public static function get(string $fromUtc, string $toUtc): array {
    return Cache::remember('dash.courier.v1.' . md5($fromUtc . $toUtc), 60, fn() => self::build($fromUtc, $toUtc));
  }

  private static function build(string $fromUtc, string $toUtc): array {
    $act = self::active(); $out = [];
    $rows = DB::table('orders')->whereNotNull('consignment_id')->where('courier_sent_at', '>=', $fromUtc)->where('courier_sent_at', '<=', $toUtc)
      ->selectRaw("coalesce(nullif(courier,''),'Pathao') as c, coalesce(courier_stage,'') as s, count(*) as n, coalesce(sum(collected_amount),0) as col, coalesce(sum(case when coalesce(settlement_status,'')<>'Paid' then coalesce(collected_amount,0)-coalesce(courier_fee,0) else 0 end),0) as pend, coalesce(sum(case when settlement_status='Paid' then coalesce(collected_amount,0)-coalesce(courier_fee,0) else 0 end),0) as rec, coalesce(sum(courier_fee),0) as fee, avg(case when courier_delivered_at is not null then extract(epoch from (courier_delivered_at - courier_sent_at))/86400 end) as avgd")
      ->groupByRaw('1,2')->get()->groupBy('c');
    foreach ($act as $name) {
      $g = $rows->get($name, collect()); $st = fn(array $l) => (int)$g->whereIn('s', $l)->sum('n'); $d = $g->firstWhere('s', 'Delivered');
      $total = (int)$g->sum('n'); $del = (int)($d->n ?? 0); $ret = $st(['Returned']); $done = $del + $ret;
      $avg = $d && $d->avgd !== null ? round((float)$d->avgd, 1) : null;
      $out[] = ['name' => $name, 'total' => $total, 'pickup' => $st(['Pickup Requested', 'Pending']), 'transit' => $st(['In Transit', 'Picked Up']), 'hub' => $st(['At Hub']), 'ofd' => $st(['Out for Delivery']),
        'delivered' => $del, 'returned' => $ret, 'failed' => $st(['Failed Delivery', 'Hold']), 'cancelled' => $st(['Cancelled']), 'success' => $done ? round($del / $done * 100, 1) : null, 'avg' => $avg,
        'cod' => (float)($d->col ?? 0), 'pending' => (float)($d->pend ?? 0), 'received' => (float)($d->rec ?? 0), 'fee' => (float)$g->sum('fee')];
    }
    $oc = DB::table('orders')->whereNotNull('pathao_outcome')->selectRaw('pathao_outcome as o, count(*) as c')->groupBy('pathao_outcome')->pluck('c', 'o')->all();
    return ['couriers' => $out, 'outcomes' => $oc, 'flagged' => (int)DB::table('orders')->whereNotNull('courier_flag')->count()];
  }
}
