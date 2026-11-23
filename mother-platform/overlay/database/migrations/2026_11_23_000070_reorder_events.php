<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('reorder_events')) return;
    Schema::create('reorder_events', function (Blueprint $t) {
      $t->id(); $t->string('phone', 15)->index(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->unsignedBigInteger('order_id');
      $t->unsignedSmallInteger('orders_n')->default(1); $t->decimal('cycle_days', 6, 1)->nullable(); $t->unsignedSmallInteger('due_days'); $t->unsignedSmallInteger('days_since');
      $t->timestamps(); $t->unique(['phone', 'order_id']); $t->index('created_at');
    });
  }
  public function down(): void { Schema::dropIfExists('reorder_events'); }
};
