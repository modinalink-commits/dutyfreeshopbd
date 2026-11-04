<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** One-time: every product priced below purchase price x 1.15 (Protected Company Profit) is raised to the next whole taka at/above that floor.
 *  Buy 2-5 pack prices under the floor are raised too (or cleared when no real saving is left); the crossed-out (compare) price is set so the raised price shows as exactly 10% off.
 *  Old values are kept in price_floor_backup so any product can be put back. Idempotent: once raised, a product no longer qualifies. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'cost')) return;
    if (!Schema::hasTable('price_floor_backup')) Schema::create('price_floor_backup', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('name', 255)->nullable(); $t->decimal('cost', 12, 2); $t->decimal('old_price', 12, 2); $t->decimal('new_price', 12, 2);
      $t->decimal('old_compare', 12, 2)->nullable(); foreach ([2, 3, 4, 5] as $n) $t->decimal("old_buy{$n}", 12, 2)->nullable(); $t->timestamps();
    });
    $hasBuy = Schema::hasColumn('products', 'buy2_price'); $hasCmp = Schema::hasColumn('products', 'compare_price'); $n = 0;
    DB::table('products')->where('cost', '>', 0)->orderBy('id')->chunkById(500, function ($rows) use (&$n, $hasBuy, $hasCmp) {
      foreach ($rows as $p) {
        $fl = ceil(round((float)$p->cost * 115, 6)) / 100; if ((float)$p->price + 0.004 >= $fl) continue;
        $np = (float)ceil($fl - 0.0001); $upd = ['price' => $np, 'updated_at' => now()]; $bk = ['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'cost' => $p->cost, 'old_price' => $p->price, 'new_price' => $np, 'created_at' => now(), 'updated_at' => now()];
        if ($hasCmp) { $bk['old_compare'] = $p->compare_price; $upd['compare_price'] = (float)ceil($np / 0.9 - 0.0001); } // crossed-out price so the raised price shows as a 10% discount
        if ($hasBuy) foreach ([2, 3, 4, 5] as $k) {
          $bk["old_buy{$k}"] = $p->{"buy{$k}_price"}; $t = $p->{"buy{$k}_price"};
          if ($t !== null && (float)$t > 0 && (float)$t + 0.004 < $fl * $k) { $nt = (float)ceil($fl * $k - 0.0001); $upd["buy{$k}_price"] = $nt < $np * $k ? $nt : null; }
          elseif ($t !== null && (float)$t > 0 && (float)$t >= $np * $k) $upd["buy{$k}_price"] = null; // no saving left after the raise
        }
        DB::table('products')->where('id', $p->id)->update($upd); DB::table('price_floor_backup')->insert($bk); $n++;
      }
    });
    if ($n > 0) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Selling prices auto-raised to protected profit floor', 'subject' => "$n products (details in price_floor_backup)", 'meta' => json_encode(['count' => $n]), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    }
    foreach (['store.home8'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
