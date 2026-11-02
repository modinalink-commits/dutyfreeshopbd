<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_payments')) {
      Schema::create('order_payments', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->string('method', 60); $t->decimal('amount', 12, 2);
        $t->string('txn_id', 120)->nullable()->index(); $t->string('sender', 60)->nullable(); $t->timestamp('paid_at')->nullable(); $t->text('note')->nullable(); $t->string('proof', 255)->nullable();
        $t->string('status', 20)->default('pending')->index(); // pending | verified | rejected
        $t->string('received_by', 120)->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->string('created_by_name', 120)->nullable();
        $t->string('verified_by', 120)->nullable(); $t->timestamp('verified_at')->nullable(); $t->string('reject_reason', 200)->nullable(); $t->timestamps();
      });
    }
    if (!Schema::hasColumn('orders', 'paid_total')) Schema::table('orders', function (Blueprint $t) { $t->decimal('paid_total', 12, 2)->default(0); $t->string('payment_status', 20)->nullable(); });
    // Backfill: orders whose single manual payment was verified become one verified ledger row.
    if (DB::table('order_payments')->count() === 0) {
      DB::unprepared("INSERT INTO order_payments(order_id,method,amount,txn_id,sender,paid_at,note,proof,status,verified_by,verified_at,created_by_name,created_at,updated_at)
        SELECT id,COALESCE(payment_method,'Manual'),COALESCE(pay_amount,total),pay_txn,pay_account,COALESCE(pay_at,created_at),pay_note,pay_proof,
        CASE pay_status WHEN 'Verified' THEN 'verified' WHEN 'Rejected' THEN 'rejected' ELSE 'pending' END, CASE WHEN pay_status='Verified' THEN pay_by END, CASE WHEN pay_status='Verified' THEN pay_at END,'Imported',COALESCE(pay_at,created_at),now()
        FROM orders WHERE pay_status IN ('Verified','Rejected','Verification Pending')");
      DB::unprepared("UPDATE orders o SET paid_total=s.t, payment_status=CASE WHEN s.t>=o.total AND o.total>0 THEN 'Paid' WHEN s.t>0 THEN 'Partially Paid' ELSE 'Unpaid' END
        FROM (SELECT order_id, SUM(amount) t FROM order_payments WHERE status='verified' GROUP BY order_id) s WHERE s.order_id=o.id");
    }
  }
  public function down(): void {}
};
