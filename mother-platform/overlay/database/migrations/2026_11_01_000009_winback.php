<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('winback_events')) return;
    Schema::create('winback_events', function (Blueprint $t) {
      $t->id(); $t->string('phone', 15)->index(); $t->unsignedBigInteger('customer_id')->nullable()->index(); $t->unsignedBigInteger('cycle_order_id'); $t->unsignedSmallInteger('stage_days'); $t->decimal('pct', 5, 2);
      $t->string('mode', 8)->default('auto'); $t->string('coupon_code', 30)->nullable()->index(); $t->string('channels', 60)->nullable(); $t->timestamp('expires_at')->nullable();
      $t->timestamp('used_at')->nullable(); $t->unsignedBigInteger('used_order_id')->nullable(); $t->decimal('used_discount', 12, 2)->default(0); $t->decimal('used_total', 12, 2)->default(0);
      $t->timestamps(); $t->unique(['phone', 'cycle_order_id', 'stage_days']);
    });
  }
  public function down(): void {}
};
