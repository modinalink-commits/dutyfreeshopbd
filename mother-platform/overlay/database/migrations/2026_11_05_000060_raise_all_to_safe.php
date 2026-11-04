<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\BundlePricing;
use App\Support\Costing;

/** Owner's instruction (2026-11-04): every product is taken to the SAFE line = actual FIFO batch cost x 1.20 (whole taka, rounded up). Only prices below that line are raised; nothing is ever lowered.
 *  The crossed-out (compare) price follows the standing 10-12% rule. Old values are kept in near_min_raise_backup. Bundles (Buy 2-5) are recalculated from the new price. Runs once.
 *  Runs outside a transaction, every product is isolated, errors are written to the audit log. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    $errs = [];
    try {
      if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'avg_cost')) return;
      $key = 'raised_to_safe_all_20261104'; if (DB::table('settings')->where('key', $key)->exists()) return;
      $hasBk = Schema::hasTable('near_min_raise_backup'); $hasCmp = Schema::hasColumn('products', 'compare_price'); $bkCols = $hasBk ? Schema::getColumnListing('near_min_raise_backup') : [];
      $n = 0; $ids = []; $tot = 0.0;
      $f = Costing::fifoSql('p');
      $rows = DB::table('products as p')->selectRaw("p.id, p.sku, p.name, p.price, ".($hasCmp ? 'p.compare_price' : 'null as compare_price').", $f as base")->whereRaw("$f > 0 and p.price > 0 and p.price < ($f) * 1.19995")->orderBy('p.id')->get();
      foreach ($rows as $p) {
        try {
          $cost = (float)$p->base; $old = (float)$p->price; $safe = (float)ceil(round($cost * 1.20 * 100, 6) / 100 - 1e-9); if ($safe <= $old + 0.004) continue; $np = $safe;
          $upd = ['price' => $np, 'updated_at' => now()]; $bk = ['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'avg_cost' => $cost, 'old_price' => $old, 'new_price' => $np, 'created_at' => now(), 'updated_at' => now()];
          if ($hasCmp) { $bk['old_compare'] = $p->compare_price; $pct = 10 + ((int)$p->id % 3); $step = $np >= 1000 ? 10 : ($np >= 200 ? 5 : 1); $c = ceil($np * (1 + $pct / 100) / $step - 1e-9) * $step;
            if ($c > $np * 1.12) $c = floor($np * 1.12 / $step) * $step; if ($c < $np * 1.10) $c = round($np * (1 + $pct / 100)); if ($c > $np) { $upd['compare_price'] = (float)$c; $bk['new_compare'] = (float)$c; } }
          DB::table('products')->where('id', $p->id)->update($upd); $ids[] = (int)$p->id; $n++; $tot += $np - $old;
          if ($hasBk) { try { DB::table('near_min_raise_backup')->insert(array_intersect_key($bk, array_flip($bkCols))); } catch (\Throwable $e) { if (count($errs) < 5) $errs[] = 'backup '.$p->id.': '.mb_substr($e->getMessage(), 0, 120); } }
        } catch (\Throwable $e) { if (count($errs) < 5) $errs[] = 'product '.$p->id.': '.mb_substr($e->getMessage(), 0, 160); }
      }
      try { BundlePricing::flush(); BundlePricing::sync(); } catch (\Throwable $e) { $errs[] = 'bundles: '.mb_substr($e->getMessage(), 0, 160); }
      try { DB::table('settings')->updateOrInsert(['key' => $key], ['value' => (string)$n, 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { $errs[] = 'marker: '.mb_substr($e->getMessage(), 0, 160); }
      try { \Illuminate\Support\Facades\Cache::forget('store.home8'); } catch (\Throwable $e) {}
      DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Selling price changed', 'subject' => "$n products raised to the Safe line (actual FIFO cost x 1.20, owner instruction). Old values in near_min_raise_backup.".($errs ? ' | notes: '.implode(' | ', $errs) : ''),
        'meta' => json_encode(['count' => $n, 'added_per_unit_total' => round($tot, 2)], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Selling price raise failed', 'subject' => 'safe-all: '.mb_substr($e->getMessage(), 0, 380), 'meta' => null, 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
