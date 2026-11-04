<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner's instruction: for every product the crossed-out "original" (compare) price is 10-12% above today's selling price (10, 11 or 12% by product id, rounded up to a tidy step but never above 12%).
 *  Products already inside 10-12% are left alone. Old compare prices are kept in compare_price_backup. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'compare_price')) return;
    if (!Schema::hasTable('compare_price_backup')) Schema::create('compare_price_backup', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->decimal('price', 12, 2); $t->decimal('old_compare', 12, 2)->nullable(); $t->decimal('new_compare', 12, 2); $t->timestamps(); });
    $n = 0;
    DB::table('products')->where('price', '>', 0)->orderBy('id')->chunkById(500, function ($rows) use (&$n) {
      foreach ($rows as $p) {
        $pr = (float)$p->price; $lo = $pr * 1.10; $hi = $pr * 1.12;
        if ($p->compare_price !== null && (float)$p->compare_price >= $lo - 0.005 && (float)$p->compare_price <= $hi + 0.005) continue;
        $pct = 10 + ((int)$p->id % 3); $target = $pr * (1 + $pct / 100); $step = $pr >= 1000 ? 10 : ($pr >= 200 ? 5 : 1);
        $c = ceil($target / $step - 1e-9) * $step; if ($c > $hi) $c = floor($hi / $step) * $step; if ($c < $lo) $c = round($target);
        $c = (float)$c; if ($c <= $pr) continue;
        DB::table('compare_price_backup')->insert(['product_id' => $p->id, 'price' => $pr, 'old_compare' => $p->compare_price, 'new_compare' => $c, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->where('id', $p->id)->update(['compare_price' => $c, 'updated_at' => now()]); $n++;
      }
    });
    if ($n > 0) { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Original (compare) price set 10-12% above selling price', 'subject' => "$n products (old values in compare_price_backup)", 'meta' => json_encode(['count' => $n]), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
    \Illuminate\Support\Facades\Cache::forget('store.home8');
  }
  public function down(): void {}
};
