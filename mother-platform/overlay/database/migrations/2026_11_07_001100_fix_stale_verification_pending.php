<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  /** Orders stuck on "Verification Pending" although no pending payment is left (the payment was rejected). */
  public function up(): void {
    DB::statement("UPDATE orders o SET pay_status = CASE WHEN EXISTS (SELECT 1 FROM order_payments p WHERE p.order_id = o.id AND p.status = 'rejected') THEN 'Rejected' ELSE NULL END
      WHERE o.pay_status = 'Verification Pending' AND EXISTS (SELECT 1 FROM order_payments p WHERE p.order_id = o.id)
      AND NOT EXISTS (SELECT 1 FROM order_payments p WHERE p.order_id = o.id AND p.status = 'pending')");
    \Illuminate\Support\Facades\Cache::forget('sb.counts.v2');
  }
  public function down(): void {}
};
