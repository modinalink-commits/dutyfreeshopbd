<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
/** Shipment outcome: each order item has its own delivery outcome; the parcel status is calculated from it. Nothing existing is changed or back-filled. */
return new class extends Migration {
  public function up(): void {
    Schema::table('order_items', function (Blueprint $t) {
      if (!Schema::hasColumn('order_items', 'outcome')) $t->string('outcome', 20)->nullable();
      if (!Schema::hasColumn('order_items', 'ret_qty')) $t->integer('ret_qty')->default(0);
      if (!Schema::hasColumn('order_items', 'outcome_at')) $t->timestamp('outcome_at')->nullable();
    });
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'return_type')) $t->string('return_type', 10)->nullable()->index();
      if (!Schema::hasColumn('orders', 'return_received_at')) $t->timestamp('return_received_at')->nullable();
    });
  }
  public function down(): void {}
};
