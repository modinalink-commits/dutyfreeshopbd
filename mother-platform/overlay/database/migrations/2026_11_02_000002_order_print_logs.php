<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Print history for invoices and labels. The log table is the source of truth; the order columns are cached counters for fast lists and filters. Additive only. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_print_logs')) Schema::create('order_print_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('invoice', 60)->nullable();
      $t->string('print_type', 10); $t->string('batch_id', 64)->nullable(); $t->unsignedBigInteger('printed_by')->nullable(); $t->string('printed_by_name', 120)->nullable();
      $t->timestamp('printed_at')->useCurrent()->index(); $t->string('ip_address', 64)->nullable(); $t->string('user_agent', 300)->nullable(); $t->string('device', 120)->nullable();
      $t->timestamps(); $t->unique(['order_id', 'print_type', 'batch_id']); $t->index(['order_id', 'print_type']);
    });
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'invoice_print_count')) $t->unsignedInteger('invoice_print_count')->default(0);
      if (!Schema::hasColumn('orders', 'label_print_count')) $t->unsignedInteger('label_print_count')->default(0);
      if (!Schema::hasColumn('orders', 'last_invoice_printed_at')) $t->timestamp('last_invoice_printed_at')->nullable();
      if (!Schema::hasColumn('orders', 'last_label_printed_at')) $t->timestamp('last_label_printed_at')->nullable();
    });
  }
  public function down(): void {}
};
