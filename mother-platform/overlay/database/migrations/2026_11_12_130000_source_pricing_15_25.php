<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner 12 Nov 2026: every sourced product (Watch, Sunglass, Gadget & Electronics, Lighter & Accessories) is priced from its source price:
 *  selling (sale) price = source +15%, crossed-out regular (compare) price = source +25%. Old prices kept in source_price_backup. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('watch_source') || !Schema::hasTable('products') || !Schema::hasColumn('products', 'compare_price')) return;
    if (!Schema::hasTable('source_price_backup')) Schema::create('source_price_backup', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->decimal('old_price', 12, 2)->nullable(); $t->decimal('old_compare', 12, 2)->nullable(); $t->decimal('old_markup_pct', 6, 2)->nullable(); $t->decimal('new_price', 12, 2); $t->decimal('new_compare', 12, 2); $t->timestamps(); });
    foreach (['watch_markup_pct' => '15', 'sunglass_markup_pct' => '15', 'mobile_markup_pct' => '15', 'amour_markup_pct' => '15', 'source_regular_pct' => '25'] as $k => $v) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
    DB::statement("INSERT INTO source_price_backup (product_id, old_price, old_compare, old_markup_pct, new_price, new_compare, created_at, updated_at)
      SELECT p.id, p.price, p.compare_price, w.markup_pct, CEIL(sp.price*1.15), CEIL(sp.price*1.25), now(), now()
      FROM watch_source w JOIN store_products sp ON sp.id = w.store_product_id JOIN products p ON p.id = w.product_id
      WHERE sp.price > 0 AND (p.price IS DISTINCT FROM CEIL(sp.price*1.15) OR p.compare_price IS DISTINCT FROM CEIL(sp.price*1.25))");
    $n = (int)DB::table('source_price_backup')->count();
    DB::statement("UPDATE products p SET price = CEIL(sp.price*1.15), compare_price = CEIL(sp.price*1.25), cost = sp.price, updated_at = now()
      FROM watch_source w JOIN store_products sp ON sp.id = w.store_product_id WHERE w.product_id = p.id AND sp.price > 0");
    DB::statement("UPDATE watch_source w SET our_price = CEIL(sp.price*1.15), markup_pct = 15, updated_at = now() FROM store_products sp WHERE sp.id = w.store_product_id AND sp.price > 0");
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Sourced products repriced: sale = source +15%, regular = source +25%', 'subject' => "$n products (old values in source_price_backup)", 'meta' => json_encode(['count' => $n]), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    foreach (['store.home8', 'store.cats', 'store.catslugs', 'store.home11'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
