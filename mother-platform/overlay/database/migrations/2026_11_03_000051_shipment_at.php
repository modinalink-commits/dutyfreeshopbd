<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
/** shipment_at = the moment an order first reached Shipment. It is the clock for the 72-hour Late Delivery rule (never created_at). */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasColumn('orders', 'shipment_at')) Schema::table('orders', function (Blueprint $t) { $t->timestamp('shipment_at')->nullable()->index(); });
      // history first: the first time the order entered Shipment
      DB::unprepared("UPDATE orders o SET shipment_at = s.t FROM (SELECT order_id, min(created_at) t FROM order_status_logs WHERE to_status = 'Shipment' GROUP BY order_id) s WHERE s.order_id = o.id AND o.shipment_at IS NULL");
      // then the courier handover time for parcels that never had a Shipment log
      DB::unprepared("UPDATE orders SET shipment_at = courier_sent_at WHERE shipment_at IS NULL AND courier_sent_at IS NOT NULL AND status IN ('Shipment','Late Delivery','Partial Delivered')");
      DB::unprepared("CREATE OR REPLACE FUNCTION orders_shipment_at() RETURNS trigger AS \$\$ BEGIN IF NEW.status = 'Shipment' AND NEW.shipment_at IS NULL THEN NEW.shipment_at := now(); END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql");
      DB::unprepared("DROP TRIGGER IF EXISTS orders_shipment_at_t ON orders");
      DB::unprepared("CREATE TRIGGER orders_shipment_at_t BEFORE INSERT OR UPDATE OF status ON orders FOR EACH ROW WHEN (NEW.status = 'Shipment' AND NEW.shipment_at IS NULL) EXECUTE FUNCTION orders_shipment_at()");
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
