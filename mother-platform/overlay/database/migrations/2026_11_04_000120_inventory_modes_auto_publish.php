<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
/**
 * Category inventory modes. SOURCING (default) sells at zero stock; STOCK_CONTROLLED (Cigarette and Vape) is unpublished at zero stock and published again on stock-in.
 * The publish rule is a database trigger on products.stock so every path (purchase, shipment, return, manual edit) is covered.
 * One-time clean-up: only products that are published at zero stock in a stock-controlled category are unpublished. Hidden products are never published here.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('categories') || !Schema::hasTable('products')) return;
    Schema::table('categories', function (Blueprint $t) { if (!Schema::hasColumn('categories', 'inventory_mode')) $t->string('inventory_mode', 20)->default('SOURCING'); });
    DB::table('categories')->whereNull('parent_id')->whereRaw("lower(trim(name)) = 'cigarette and vape'")->update(['inventory_mode' => 'STOCK_CONTROLLED']);
    DB::table('categories')->whereNull('inventory_mode')->update(['inventory_mode' => 'SOURCING']);
    if (DB::getDriverName() === 'pgsql') {
      DB::unprepared("CREATE OR REPLACE FUNCTION products_stock_publish() RETURNS trigger AS \$\$
        BEGIN
          IF NEW.category_id IS NOT NULL AND EXISTS (SELECT 1 FROM categories c WHERE c.inventory_mode = 'STOCK_CONTROLLED' AND (c.id = NEW.category_id OR c.id = (SELECT parent_id FROM categories WHERE id = NEW.category_id))) THEN
            IF coalesce(NEW.stock, 0) <= 0 THEN NEW.published := false;
            ELSIF coalesce(OLD.stock, 0) <= 0 THEN NEW.published := true;
            END IF;
          END IF;
          RETURN NEW;
        END; \$\$ LANGUAGE plpgsql");
      DB::unprepared("DROP TRIGGER IF EXISTS products_stock_publish_t ON products");
      DB::unprepared("CREATE TRIGGER products_stock_publish_t BEFORE UPDATE OF stock ON products FOR EACH ROW WHEN (OLD.stock IS DISTINCT FROM NEW.stock) EXECUTE FUNCTION products_stock_publish()");
      DB::unprepared("UPDATE products SET published = false, updated_at = now() WHERE published = true AND coalesce(stock, 0) <= 0 AND category_id IN (SELECT c.id FROM categories c WHERE c.inventory_mode = 'STOCK_CONTROLLED' OR c.parent_id IN (SELECT id FROM categories WHERE inventory_mode = 'STOCK_CONTROLLED'))");
    }
    foreach (['stock.modes', 'stock.parents', 'store.home8'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
