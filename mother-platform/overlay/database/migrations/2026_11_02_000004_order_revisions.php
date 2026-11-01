<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Order Edit / Revision system: every change to an existing order is a revision row (who, when, why, before, after); items changes in the child table. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_revisions')) Schema::create('order_revisions', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->string('ip', 60)->nullable();
      $t->string('revision_type', 40)->default('items'); $t->string('status', 20)->default('applied')->index();
      $t->decimal('old_subtotal', 12, 2)->default(0); $t->decimal('new_subtotal', 12, 2)->default(0);
      $t->decimal('old_discount', 12, 2)->default(0); $t->decimal('new_discount', 12, 2)->default(0);
      $t->decimal('old_delivery_charge', 12, 2)->default(0); $t->decimal('new_delivery_charge', 12, 2)->default(0);
      $t->decimal('old_grand_total', 12, 2)->default(0); $t->decimal('new_grand_total', 12, 2)->default(0); $t->decimal('amount_difference', 12, 2)->default(0);
      $t->string('reason', 120)->nullable(); $t->text('note')->nullable(); $t->boolean('customer_confirmed')->default(false);
      $t->unsignedBigInteger('call_log_id')->nullable(); $t->boolean('courier_warning')->default(false); $t->boolean('sms_sent')->default(false);
      $t->text('discount_note')->nullable(); $t->text('delivery_note')->nullable(); $t->jsonb('payload')->nullable(); $t->timestamp('applied_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('order_revision_items')) Schema::create('order_revision_items', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_revision_id')->index(); $t->unsignedBigInteger('order_item_id')->nullable(); $t->unsignedBigInteger('product_id')->nullable();
      $t->string('name', 255)->nullable(); $t->string('action', 20); $t->integer('old_quantity')->nullable(); $t->integer('new_quantity')->nullable();
      $t->decimal('old_price', 12, 2)->nullable(); $t->decimal('new_price', 12, 2)->nullable(); $t->decimal('old_total', 12, 2)->nullable(); $t->decimal('new_total', 12, 2)->nullable(); $t->jsonb('metadata')->nullable();
    });
  }
  public function down(): void {}
};
