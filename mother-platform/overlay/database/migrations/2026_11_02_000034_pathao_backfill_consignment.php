<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Orders imported from the old site keep their Pathao parcel code in tracking_code. Copy it to consignment_id so courier sync can track them. Shipment + Pathao only, valid-looking codes only. */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    DB::statement("UPDATE orders SET consignment_id = tracking_code, courier_sent_at = COALESCE(courier_sent_at, created_at), updated_at = now() WHERE status = 'Shipment' AND courier = 'Pathao' AND consignment_id IS NULL AND tracking_code ~ '^[A-Z]{2}[0-9A-Z]{10,14}$'");
  }
  public function down(): void {}
};
