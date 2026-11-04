<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner's instruction: every product must be Safe or better (profit on the AVERAGE purchase price at least 20%). Products marked Near Minimum (15-20%) get +2% on the selling price (rounded up to a whole taka),
 *  and if that still leaves them under the Safe line they are raised to it (average x 1.20, whole taka). Buy 2-5 pack prices move by the same ratio and never below the Safe line for the pack (dropped when no saving is left);
 *  the crossed-out (compare) price follows the standing 10-12% rule.
 *  Old values are kept in near_min_raise_backup. Runs once. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'avg_cost')) return;
    if (!Schema::hasTable('near_min_raise_backup')) Schema::create('near_min_raise_backup', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('name', 255)->nullable(); $t->decimal('avg_cost', 14, 4); $t->decimal('old_price', 12, 2); $t->decimal('new_price', 12, 2);
      $t->decimal('old_compare', 12, 2)->nullable(); $t->decimal('new_compare', 12, 2)->nullable(); foreach ([2, 3, 4, 5] as $n) $t->decimal("old_buy{$n}", 12, 2)->nullable(); $t->timestamps();
    });
    $hasBuy = Schema::hasColumn('products', 'buy2_price'); $hasCmp = Schema::hasColumn('products', 'compare_price'); $n = 0;
    DB::table('products')->whereRaw('coalesce(nullif(avg_cost,0), cost) > 0')->whereRaw('price + 0.004 >= ceil(round(coalesce(nullif(avg_cost,0), cost) * 115, 6)) / 100')->whereRaw('price < coalesce(nullif(avg_cost,0), cost) * 1.20 * 0.99999')
      ->orderBy('id')->chunkById(500, function ($rows) use (&$n, $hasBuy, $hasCmp) {
        foreach ($rows as $p) {
          $avg = (float)($p->avg_cost > 0 ? $p->avg_cost : $p->cost); $old = (float)$p->price; $safe = (float)ceil($avg * 1.20 * 1.000001); $np = max((float)ceil($old * 1.02 - 1e-9), $safe); if ($np <= $old) $np = $old + 1;
          $upd = ['price' => $np, 'updated_at' => now()]; $bk = ['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'avg_cost' => $avg, 'old_price' => $old, 'new_price' => $np, 'created_at' => now(), 'updated_at' => now()];
          if ($hasCmp) { // standing rule: crossed-out price is 10, 11 or 12% above the selling price (by product id)
            $bk['old_compare'] = $p->compare_price; $pct = 10 + ((int)$p->id % 3); $step = $np >= 1000 ? 10 : ($np >= 200 ? 5 : 1); $c = ceil($np * (1 + $pct / 100) / $step - 1e-9) * $step;
            if ($c > $np * 1.12) $c = floor($np * 1.12 / $step) * $step; if ($c < $np * 1.10) $c = round($np * (1 + $pct / 100)); if ($c > $np) { $upd['compare_price'] = (float)$c; $bk['new_compare'] = (float)$c; }
          }
          if ($hasBuy) foreach ([2, 3, 4, 5] as $k) {
            $bk["old_buy{$k}"] = $p->{"buy{$k}_price"}; $t = $p->{"buy{$k}_price"};
            if ($t !== null && (float)$t > 0) { $nt = max((float)ceil((float)$t * $np / $old - 1e-9), (float)ceil($avg * 1.20 * 1.000001 * $k)); $upd["buy{$k}_price"] = $nt < $np * $k ? $nt : null; }
          }
          DB::table('products')->where('id', $p->id)->update($upd); DB::table('near_min_raise_backup')->insert($bk); $n++;
        }
      });
    if ($n > 0) { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Near Minimum products: selling price raised 2%', 'subject' => "$n products (old values in near_min_raise_backup)", 'meta' => json_encode(['count' => $n]), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
    foreach (['store.home8'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
