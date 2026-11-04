<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\BundlePricing;
use App\Support\Costing;
use App\Support\ProfitFloor;

/** Owner's instruction (2026-11-04): the products whose sale price is below the ACTUAL FIFO batch cost x 1.15 are raised to that minimum (whole taka, rounded up).
 *  The crossed-out (compare) price follows the standing 10-12% rule. Old values are kept in near_min_raise_backup. Bundles are recalculated. Runs once.
 *  Runs outside a DB transaction and every step is isolated, so one failing step can never undo the price change; any error text is written to the audit log. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    $errs = [];
    try {
      if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'avg_cost')) return;
      if (DB::table('settings')->where('key', 'raised_to_fifo_min_v2_20261104')->exists()) return;
      $skus = ['DF-1043', 'DF-1023', 'DF-1133', 'DF-1151', 'DF-1029', 'DF-1033', 'DF-1164', 'DF-1087', 'DF-1062', 'DF-1060', 'DF-2757'];
      $hasBk = Schema::hasTable('near_min_raise_backup'); $hasCmp = Schema::hasColumn('products', 'compare_price'); $n = 0; $ids = []; $log = [];
      $bkCols = $hasBk ? Schema::getColumnListing('near_min_raise_backup') : [];
      foreach (DB::table('products')->whereIn('sku', $skus)->orderBy('id')->get() as $p) {
        try {
          $b = DB::table('products as p')->selectRaw(Costing::fifoSql('p').' as base')->where('p.id', $p->id)->first();
          $cost = $b ? (float)$b->base : 0.0; $old = (float)$p->price; if ($cost <= 0 || $old <= 0) continue;
          $min = ProfitFloor::floor($cost); if ($old + 0.004 >= $min) continue; $np = (float)ceil($min - 1e-9);
          $upd = ['price' => $np, 'updated_at' => now()]; $bk = ['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'avg_cost' => $cost, 'old_price' => $old, 'new_price' => $np, 'created_at' => now(), 'updated_at' => now()];
          if ($hasCmp) { $bk['old_compare'] = $p->compare_price; $pct = 10 + ((int)$p->id % 3); $step = $np >= 1000 ? 10 : ($np >= 200 ? 5 : 1); $c = ceil($np * (1 + $pct / 100) / $step - 1e-9) * $step;
            if ($c > $np * 1.12) $c = floor($np * 1.12 / $step) * $step; if ($c < $np * 1.10) $c = round($np * (1 + $pct / 100)); if ($c > $np) { $upd['compare_price'] = (float)$c; $bk['new_compare'] = (float)$c; } }
          DB::table('products')->where('id', $p->id)->update($upd);
          $ids[] = (int)$p->id; $log[] = ($p->sku ?: $p->id).': '.(int)$old.' -> '.(int)$np; $n++;
          if ($hasBk) { try { DB::table('near_min_raise_backup')->insert(array_intersect_key($bk, array_flip($bkCols))); } catch (\Throwable $e) { $errs[] = 'backup '.$p->id.': '.mb_substr($e->getMessage(), 0, 160); } }
        } catch (\Throwable $e) { $errs[] = 'product '.$p->id.': '.mb_substr($e->getMessage(), 0, 200); }
      }
      try { if ($ids) { BundlePricing::flush(); BundlePricing::sync($ids); } } catch (\Throwable $e) { $errs[] = 'bundles: '.mb_substr($e->getMessage(), 0, 160); }
      try { DB::table('settings')->updateOrInsert(['key' => 'raised_to_fifo_min_v2_20261104'], ['value' => (string)$n, 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { $errs[] = 'marker: '.mb_substr($e->getMessage(), 0, 160); }
      try { \Illuminate\Support\Facades\Cache::forget('store.home8'); } catch (\Throwable $e) {}
      DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Selling price changed', 'subject' => "$n products raised to the FIFO cost x 1.15 minimum (owner instruction): ".implode('; ', $log).($errs ? ' | notes: '.implode(' | ', $errs) : ''),
        'meta' => json_encode(['count' => $n], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Selling price raise failed', 'subject' => mb_substr($e->getMessage(), 0, 400), 'meta' => null, 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
