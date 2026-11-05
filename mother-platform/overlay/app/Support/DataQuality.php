<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data-quality notes shown on report pages whose numbers depend on incomplete data. Honest on purpose: a gap is shown, never hidden.
 * Cached for 10 minutes as plain arrays (the cache does not restore objects).
 */
class DataQuality
{
  /** Which checks matter for which report group. */
  const BY_GROUP = ['EXE' => ['store', 'cost'], 'SAL' => ['store'], 'PRF' => ['store', 'cost'], 'INV' => ['cost'], 'CUR' => ['store'], 'CUS' => ['store']];

  /** @return array list of ['ok'=>bool, 'text'=>string] for a report group code */
  public static function forGroup(?string $group): array
  {
    $need = self::BY_GROUP[$group ?? ''] ?? []; if (!$need) return [];
    $d = self::stats(); $out = [];
    if (in_array('store', $need, true) && $d['orders'] > 0) {
      $miss = $d['no_store']; $pct = round(($d['orders'] - $miss) * 100 / $d['orders'], 1);
      $out[] = $miss === 0 ? ['ok' => true, 'text' => 'Every order has a Store'] : ['ok' => false, 'text' => '⚠ ' . number_format($miss) . ' orders have no Store (' . $pct . '% complete)'];
    }
    if (in_array('cost', $need, true)) {
      $out[] = $d['no_cost'] === 0 ? ['ok' => true, 'text' => 'Every published product has a cost'] : ['ok' => false, 'text' => '⚠ ' . number_format($d['no_cost']) . ' published products have no cost price (profit looks higher than it is)'];
    }
    return $out;
  }

  private static function stats(): array
  {
    return Cache::remember('report_data_quality_v1', 600, function () {
      $o = ['orders' => 0, 'no_store' => 0, 'no_cost' => 0];
      try {
        $r = DB::selectOne('select count(*) n, sum(case when store_id is null or store_id not in (select id from stores) then 1 else 0 end) m from orders');
        $o['orders'] = (int)($r->n ?? 0); $o['no_store'] = (int)($r->m ?? 0);
      } catch (\Throwable $e) {}
      try { $o['no_cost'] = (int)DB::table('products')->where('published', true)->whereRaw('coalesce(cost,0) <= 0 and coalesce(avg_cost,0) <= 0')->count(); } catch (\Throwable $e) {}
      return $o;
    });
  }
}
