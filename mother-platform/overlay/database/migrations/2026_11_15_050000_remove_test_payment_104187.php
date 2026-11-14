<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner approval 2026-11-15: the 100 BDT test payment TESTREQ-CLAUDE-1 on cancelled test order 103981 (invoice 104187) is removed.
 * Only that exact row is touched (order 103981 + reference TESTREQ-CLAUDE-1). A copy is kept in test_payment_backup first. Runs once; does nothing if the row is already gone.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_payments')) return;
    $rows = DB::table('order_payments')->where('order_id', 103981)->where(function ($q) {
      $q->where('txn_id', 'TESTREQ-CLAUDE-1')->orWhere('note', 'like', '%TESTREQ-CLAUDE-1%');
    })->get();
    if ($rows->isEmpty()) return;
    $ids = $rows->pluck('id')->all();
    if (!Schema::hasTable('test_payment_backup')) DB::statement('CREATE TABLE test_payment_backup AS SELECT * FROM order_payments WHERE false');
    $in = implode(',', array_map('intval', $ids));
    DB::statement("INSERT INTO test_payment_backup SELECT * FROM order_payments WHERE id IN ($in)");
    DB::transaction(function () use ($ids) {
      DB::table('order_payments')->whereIn('id', $ids)->delete();
      \App\Http\Controllers\OrderPaymentController::recalc(103981);
    });
    try {
      DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'payment.test_removed', 'subject' => 'Test payment TESTREQ-CLAUDE-1 removed from order 103981 (invoice 104187)', 'meta' => json_encode(['previous' => $rows->map(fn($r) => ['id' => $r->id, 'amount' => $r->amount, 'status' => $r->status, 'method' => $r->method])->all(), 'new' => null, 'backup_table' => 'test_payment_backup', 'approved_by' => 'Mohammad Faruk'], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {}
  }
  public function down(): void {
    if (!Schema::hasTable('test_payment_backup')) return;
    DB::statement('INSERT INTO order_payments SELECT * FROM test_payment_backup WHERE id NOT IN (SELECT id FROM order_payments)');
    DB::table('test_payment_backup')->delete();
    try { \App\Http\Controllers\OrderPaymentController::recalc(103981); } catch (\Throwable $e) {}
  }
};
