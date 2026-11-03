<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Coupon portal: name/notes, exact start/end date+time, customer targeting, product/category/brand scope, channel, order-count rules. Old coupons keep working unchanged (all new columns are optional). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('coupons')) return;
    Schema::table('coupons', function (Blueprint $t) {
      $add = fn($c, $f) => Schema::hasColumn('coupons', $c) ?: $f();
      $add('name', fn() => $t->string('name', 120)->nullable());
      $add('description', fn() => $t->text('description')->nullable());
      $add('starts_at', fn() => $t->timestamp('starts_at')->nullable());
      $add('ends_at', fn() => $t->timestamp('ends_at')->nullable());
      $add('audience', fn() => $t->string('audience', 20)->default('all'));
      $add('audience_phones', fn() => $t->text('audience_phones')->nullable());
      $add('min_past_orders', fn() => $t->unsignedInteger('min_past_orders')->nullable());
      $add('max_past_orders', fn() => $t->unsignedInteger('max_past_orders')->nullable());
      $add('product_ids', fn() => $t->text('product_ids')->nullable());
      $add('category_ids', fn() => $t->text('category_ids')->nullable());
      $add('brand_ids', fn() => $t->text('brand_ids')->nullable());
      $add('min_qty', fn() => $t->unsignedInteger('min_qty')->nullable());
      $add('channel', fn() => $t->string('channel', 10)->default('any'));
      $add('created_by', fn() => $t->string('created_by', 80)->nullable());
    });
  }
  public function down(): void {}
};
