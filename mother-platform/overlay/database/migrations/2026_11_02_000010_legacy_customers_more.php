<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('legacy_customers')) return;
    Schema::table('legacy_customers', function (Blueprint $t) {
      foreach (['old_id' => fn() => $t->unsignedBigInteger('old_id')->nullable()->index(), 'old_status' => fn() => $t->smallInteger('old_status')->nullable(), 'customer_type' => fn() => $t->smallInteger('customer_type')->nullable(), 'is_blocked' => fn() => $t->smallInteger('is_blocked')->nullable(),
        'city_id' => fn() => $t->integer('city_id')->nullable(), 'sub_city_id' => fn() => $t->integer('sub_city_id')->nullable(), 'old_created_at' => fn() => $t->string('old_created_at', 30)->nullable(), 'old_updated_at' => fn() => $t->string('old_updated_at', 30)->nullable(),
        'last_order_date' => fn() => $t->string('last_order_date', 30)->nullable(), 'coupon_income' => fn() => $t->decimal('coupon_income', 14, 2)->nullable(), 'username' => fn() => $t->string('username', 120)->nullable()] as $c => $f) if (!Schema::hasColumn('legacy_customers', $c)) $f();
    });
  }
  public function down(): void {}
};
