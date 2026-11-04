<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\BundlePricing;
use App\Support\Costing;
use App\Support\ProfitFloor;

/** Owner's instruction (2026-11-04): the 11 products whose sale price was below actual FIFO batch cost x 1.15 are raised to that minimum (whole taka, rounded up).
 *  The crossed-out (compare) price follows the standing 10-12% rule. Old values are kept in near_min_raise_backup. Bundles are recalculated. Runs once. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'avg_cost')) return;
    if (DB::table('settings')->where('key', 'raised_to_fifo_min_20261104')->exists()) return;
    $skus = ['DF-1043', 'DF-1023', 'DF-1133', 'DF-1151', 'DF-1029', 'DF-1033', 'DF-1164', 'DF-1087', 'DF-1062', 'DF-1060', 'DF-2757'];
    $hasBk = Schema::hasTable('near_min_raise_backup'); $hasCmp = Schema::hasColumn('products', 'compare_price'); $n = 0; $ids = []; $log = [];
    $rows = DB::table('products as p')->whereIn('p.sku', $skus)->selectRaw('p.*, '.Costing::fifoSql('p').' as base')->get();
    foreach ($rows as $p) {
      $cost = (float)$p->base; $old = (float)$p->price; if ($cost <= 0 || $old <= 0) continue;
      $min = ProfitFloor::floor($cost); if ($old + 0.004 >= $min) continue; $np = (float)ceil($min - 1e-9);
      $upd = ['price' => $np, 'updated_at' => now()]; $bk = ['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'avg_cost' => $cost, 'old_price' => $old, 'new_price' => $np, 'created_at' => now(), 'updated_at' => now()];
      if ($hasCmp) { $bk['old_compare'] = $p->compare_price; $pct = 10 + ((int)$p->id % 3); $step = $np >= 1000 ? 10 : ($np >= 200 ? 5 : 1); $c = ceil($np * (1 + $pct / 100) / $step - 1e-9) * $step;
        if ($c > $np * 1.12) $c = floor($np * 1.12 / $step) * $step; if ($c < $np * 1.10) $c = round($np * (1 + $pct / 100)); if ($c > $np) { $upd['compare_price'] = (float)$c; $bk['new_compare'] = (float)$c; } }
      DB::table('products')->where('id', $p->id)->update($upd);
      if ($hasBk) { try { $cols = Schema::getColumnListing('near_min_raise_backup'); DB::table('near_min_raise_backup')->insert(array_intersect_key($bk, array_flip($cols))); } catch (\Throwable $e) { report($e); } }
      $ids[] = (int)$p->id; $log[] = ($p->sku ?: $p->id).': '.(int)$old.' -> '.(int)$np; $n++;
    }
    try { if ($ids) { BundlePricing::flush(); BundlePricing::sync($ids); } } catch (\Throwable $e) { report($e); }
    DB::table('settings')->updateOrInsert(['key' => 'raised_to_fifo_min_20261104'], ['value' => (string)$n, 'updated_at' => now(), 'created_at' => now()]);
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Selling price changed', 'subject' => "$n products raised to the FIFO cost x 1.15 minimum (owner instruction): ".implode('; ', $log), 'meta' => json_encode(['count' => $n], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) { report($e); }
    foreach (['store.home8'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
