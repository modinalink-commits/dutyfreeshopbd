<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('incomplete_orders')) return;
    Schema::create('incomplete_orders', function (Blueprint $t) {
      $t->id(); $t->string('token', 40)->index(); $t->string('phone', 20)->index(); $t->string('name', 120)->nullable(); $t->string('email', 120)->nullable();
      $t->text('address')->nullable(); $t->string('district', 80)->nullable(); $t->json('items')->nullable(); $t->unsignedInteger('qty')->default(0);
      $t->decimal('subtotal', 12, 2)->default(0); $t->decimal('discount', 12, 2)->default(0); $t->decimal('shipping', 12, 2)->default(0); $t->decimal('total', 12, 2)->default(0);
      $t->string('discount_note', 250)->nullable(); $t->string('pay_method', 20)->nullable(); $t->string('customer_category', 20)->nullable(); $t->string('membership', 80)->nullable();
      $t->string('note', 500)->nullable(); $t->string('last_step', 30)->nullable(); $t->unsignedTinyInteger('progress')->default(0);
      $t->string('status', 12)->default('open')->index(); $t->unsignedBigInteger('order_id')->nullable(); $t->string('ip', 45)->nullable();
      $t->timestamp('last_activity_at')->nullable()->index(); $t->timestamps();
    });
  }
  public function down(): void {}
};
