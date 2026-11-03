<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** The same online (ShurjoPay) transaction can never become two payment records, whatever happens at the callback. */
return new class extends Migration {
  public function up(): void {
    try { DB::unprepared("CREATE UNIQUE INDEX IF NOT EXISTS order_payments_shurjo_txn_uq ON order_payments (lower(txn_id)) WHERE txn_id IS NOT NULL AND txn_id <> '' AND lower(method) LIKE '%shurjo%' AND status <> 'rejected'"); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
