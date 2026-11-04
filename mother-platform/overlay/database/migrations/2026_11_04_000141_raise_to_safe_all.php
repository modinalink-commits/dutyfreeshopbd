<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner's instruction: no product and no Buy 2-5 bundle may sit at Near Minimum or Below Minimum - everything at least Safe (profit on the AVERAGE purchase price >= 20%).
 *  Remaining Below Minimum products are raised to the Safe line (whole taka). Where a bundle (Buy 2-5) would still be under the Safe line, the single price is raised just enough that every bundle keeps its current saving percentage and stays Safe
 *  (a bundle that would have no saving left is removed). The crossed-out (compare) price follows the standing 10-12% rule. Old values are kept in near_min_raise_backup. Runs once. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'avg_cost') || !Schema::hasTable('near_min_raise_backup')) return;
    $hasBuy = Schema::hasColumn('products', 'buy2_price'); $hasCmp = Schema::hasColumn('products', 'compare_price'); $n = 0;
    DB::table('products')->whereRaw('coalesce(nullif(avg_cost,0), cost) > 0')->orderBy('id')->chunkById(500, function ($rows) use (&$n, $hasBuy, $hasCmp) {
      foreach ($rows as $p) {
        $avg = (float)($p->avg_cost > 0 ? $p->avg_cost : $p->cost); $old = (float)$p->price; if ($old <= 0) continue;
        $safe = $avg * 1.20 * 1.000001; $need = $old; $ratio = [];
        if ($old < $avg * 1.19995) $need = max($need, ceil($old * 1.02 - 1e-9), ceil($safe));
        if ($hasBuy) foreach ([2, 3, 4, 5] as $k) { $t = (float)($p->{"buy{$k}_price"} ?? 0); if ($t <= 0 || $t >= $old * $k) continue; $r = $t / ($old * $k); $ratio[$k] = $r; if ($t < $avg * 1.19995 * $k) $need = max($need, ceil($safe / $r)); }
        if ($need <= $old + 0.004) continue;
        $np = (float)$need; $upd = ['price' => $np, 'updated_at' => now()];
        $bk = ['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'avg_cost' => $avg, 'old_price' => $old, 'new_price' => $np, 'created_at' => now(), 'updated_at' => now()];
        if ($hasCmp) { $bk['old_compare'] = $p->compare_price; $pct = 10 + ((int)$p->id % 3); $step = $np >= 1000 ? 10 : ($np >= 200 ? 5 : 1); $c = ceil($np * (1 + $pct / 100) / $step - 1e-9) * $step;
          if ($c > $np * 1.12) $c = floor($np * 1.12 / $step) * $step; if ($c < $np * 1.10) $c = round($np * (1 + $pct / 100)); if ($c > $np) { $upd['compare_price'] = (float)$c; $bk['new_compare'] = (float)$c; } }
        if ($hasBuy) foreach ([2, 3, 4, 5] as $k) { $bk["old_buy{$k}"] = $p->{"buy{$k}_price"}; if (!isset($ratio[$k])) continue;
          $nt = max((float)ceil($ratio[$k] * $np * $k - 1e-9), (float)ceil($safe * $k)); $upd["buy{$k}_price"] = $nt < $np * $k ? $nt : null; }
        DB::table('products')->where('id', $p->id)->update($upd); DB::table('near_min_raise_backup')->insert($bk); $n++;
      }
    });
    if ($n > 0) { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Products and bundles raised to the Safe line', 'subject' => "$n products (old values in near_min_raise_backup)", 'meta' => json_encode(['count' => $n]), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
    foreach (['store.home8'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
