<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner request 2026-11-12: payment of invoice 104142 (order 103941) was verified by mistake by staff. Put it back to pending; only Mohammad Faruk may verify. Runs once, only if still verified by that mistake. */
return new class extends Migration {
  public function up(): void {
    $o = DB::table('orders')->where('id', 103941)->where('invoice', 'like', '%104142')->first();
    if (!$o) return;
    $rows = DB::table('order_payments')->where('order_id', 103941)->where('status', 'verified')->get();
    foreach ($rows as $p) {
      DB::table('order_payments')->where('id', $p->id)->update(['status' => 'pending', 'verified_by' => null, 'verified_at' => null, 'updated_at' => now()]);
    }
    if ($rows->count()) {
      DB::table('orders')->where('id', 103941)->update(['pay_status' => 'Verification Pending', 'pay_by' => null, 'pay_at' => null, 'updated_at' => now()]);
      \App\Http\Controllers\OrderPaymentController::recalc(103941);
      try { \App\Support\OrderActivity::log(103941, 'payment_unverified', 'verified', 'pending', 'Payment set back to Pending Verification (verified by mistake) on owner request', ['by' => 'Mohammad Faruk']); } catch (\Throwable $e) {}
    }
  }
  public function down(): void {}
};
