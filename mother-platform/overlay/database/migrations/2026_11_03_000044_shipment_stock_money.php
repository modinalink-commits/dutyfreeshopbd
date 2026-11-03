<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
/**
 * Shipment outcome phase 2.
 * - Stock leaves the shelf once, when an order first reaches "Shipment" (database trigger, so every path is covered: staff, bulk, courier auto-book, schedule).
 *   Orders that were already shipped before this migration are NOT touched, and are never given stock back (their stock was never deducted here).
 * - Partial return: the parcel total becomes the value of the received items only; the original figures are kept in original_*.
 */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      foreach (['original_total', 'original_shipping', 'original_discount'] as $c) if (!Schema::hasColumn('orders', $c)) $t->decimal($c, 12, 2)->nullable();
      foreach (['stock_deducted_at', 'stock_returned_at'] as $c) if (!Schema::hasColumn('orders', $c)) $t->timestamp($c)->nullable();
    });
    Schema::table('order_items', function (Blueprint $t) { if (!Schema::hasColumn('order_items', 'back_qty')) $t->integer('back_qty')->nullable(); });
    if (DB::getDriverName() !== 'pgsql') return;
    DB::unprepared("CREATE OR REPLACE FUNCTION orders_ship_stock() RETURNS trigger AS \$\$
      DECLARE r record; b int;
      BEGIN
        FOR r IN SELECT oi.product_id pid, sum(oi.qty)::int q FROM order_items oi WHERE oi.order_id = NEW.id AND oi.product_id IS NOT NULL GROUP BY oi.product_id LOOP
          SELECT stock INTO b FROM products WHERE id = r.pid FOR UPDATE;
          IF b IS NULL THEN CONTINUE; END IF;
          UPDATE products SET stock = b - r.q WHERE id = r.pid;
          INSERT INTO stock_logs(product_id, source, reference, before_qty, change_qty, after_qty, user_id, user_name, created_at, updated_at)
          VALUES (r.pid, 'order-shipment', NEW.invoice, b, -r.q, b - r.q, NULL, 'System', now(), now());
        END LOOP;
        NEW.stock_deducted_at := now();
        RETURN NEW;
      END; \$\$ LANGUAGE plpgsql");
    DB::unprepared("DROP TRIGGER IF EXISTS orders_ship_stock_t ON orders");
    DB::unprepared("CREATE TRIGGER orders_ship_stock_t BEFORE UPDATE OF status ON orders FOR EACH ROW
      WHEN (NEW.status = 'Shipment' AND OLD.status IS DISTINCT FROM 'Shipment' AND NEW.stock_deducted_at IS NULL AND NEW.duplicate_of_order_id IS NULL)
      EXECUTE FUNCTION orders_ship_stock()");
  }
  public function down(): void {}
};
