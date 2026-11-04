<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product Publish, Stock-Out & Stock-In Rules.
 *  - Stock = 0 no longer unpublishes anything (the old trigger unpublished Cigarette and Vape products at zero stock).
 *  - Stock-in (stock 0 -> positive, from any path: purchase, return, exchange, authorised adjustment) publishes the product, unless products.manual_unpublish_lock = true.
 *    A product with no image or no price is never auto-published (it would be an empty page).
 *  - products.manual_unpublish_lock: set when an admin unpublishes by hand, cleared when an admin publishes by hand.
 *  - One-time lock back-fill: every product that is unpublished today is locked, EXCEPT a stock-controlled product at zero stock that has no manual Unpublished entry in its status log
 *    (those were hidden by the old rule, so the next stock-in publishes them again). Nothing is published or unpublished by this migration.
 * Runs outside a transaction; every step is isolated and any error text goes to the audit log.
 */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    if (!Schema::hasTable('products')) return;
    $errs = [];
    try { if (!Schema::hasColumn('products', 'manual_unpublish_lock')) Schema::table('products', function (Blueprint $t) { $t->boolean('manual_unpublish_lock')->default(false); }); } catch (\Throwable $e) { $errs[] = 'column: '.mb_substr($e->getMessage(), 0, 160); }
    if (!Schema::hasColumn('products', 'manual_unpublish_lock')) return;
    if (DB::getDriverName() === 'pgsql') {
      try {
        DB::unprepared("CREATE OR REPLACE FUNCTION products_stock_publish() RETURNS trigger AS \$\$
          BEGIN
            IF coalesce(OLD.stock, 0) <= 0 AND coalesce(NEW.stock, 0) > 0 AND NEW.published = false AND coalesce(NEW.manual_unpublish_lock, false) = false
               AND coalesce(NEW.price, 0) > 0 AND coalesce(NEW.image, '') <> '' THEN
              NEW.published := true;
            END IF;
            RETURN NEW;
          END; \$\$ LANGUAGE plpgsql");
        DB::unprepared("DROP TRIGGER IF EXISTS products_stock_publish_t ON products");
        DB::unprepared("CREATE TRIGGER products_stock_publish_t BEFORE UPDATE OF stock ON products FOR EACH ROW WHEN (OLD.stock IS DISTINCT FROM NEW.stock) EXECUTE FUNCTION products_stock_publish()");
      } catch (\Throwable $e) { $errs[] = 'trigger: '.mb_substr($e->getMessage(), 0, 200); }
    }
    $key = 'publish_stock_separation_20261104';
    if (!DB::table('settings')->where('key', $key)->exists()) {
      $locked = null; $free = null;
      try {
        $ctl = DB::select("SELECT c.id FROM categories c WHERE c.inventory_mode = 'STOCK_CONTROLLED' OR c.parent_id IN (SELECT id FROM categories WHERE inventory_mode = 'STOCK_CONTROLLED')");
        $ctlIds = array_map(fn($x) => (int)$x->id, $ctl);
        $hasLog = Schema::hasTable('product_status_logs');
        $q = DB::table('products as p')->where('p.published', false);
        $manual = $hasLog ? "exists (select 1 from product_status_logs l where l.product_id = p.id and l.action = 'Unpublished')" : 'false';
        if ($ctlIds) { $in = implode(',', $ctlIds); $q->whereRaw("(p.category_id is null or p.category_id not in ($in) or coalesce(p.stock,0) > 0 or $manual)"); }
        $locked = $q->update(['manual_unpublish_lock' => true]);
        $free = DB::table('products as p')->where('p.published', false)->where('manual_unpublish_lock', false)->count();
      } catch (\Throwable $e) { $errs[] = 'backfill: '.mb_substr($e->getMessage(), 0, 200); }
      try { DB::table('settings')->updateOrInsert(['key' => $key], ['value' => (string)($locked ?? 'err'), 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { $errs[] = 'marker: '.mb_substr($e->getMessage(), 0, 160); }
      try {
        DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Publish rule changed', 'subject' => 'Stock-out no longer unpublishes products; stock-in publishes them unless manually locked. '.($locked ?? '?').' unpublished products locked, '.($free ?? '?').' stock-controlled products at zero stock will publish again on stock-in.'.($errs ? ' | notes: '.implode(' | ', $errs) : ''),
          'meta' => json_encode(['locked' => $locked, 'auto_publish_on_stock_in' => $free], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
      } catch (\Throwable $e) {}
    }
    foreach (['stock.modes', 'stock.parents', 'store.home8'] as $k) { try { \Illuminate\Support\Facades\Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {}
};
