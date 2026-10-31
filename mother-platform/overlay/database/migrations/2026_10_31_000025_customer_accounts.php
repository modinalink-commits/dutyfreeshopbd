<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('customers')) Schema::table('customers', function (Blueprint $t) {
      if (!Schema::hasColumn('customers', 'password')) $t->string('password')->nullable();
      if (!Schema::hasColumn('customers', 'dob')) $t->date('dob')->nullable();
      if (!Schema::hasColumn('customers', 'referred_by')) $t->string('referred_by', 40)->nullable();
      if (!Schema::hasColumn('customers', 'registered_at')) $t->timestamp('registered_at')->nullable();
      if (!Schema::hasColumn('customers', 'phone_verified_at')) $t->timestamp('phone_verified_at')->nullable();
    });
    if (Schema::hasTable('coupons') && !Schema::hasColumn('coupons', 'is_public')) Schema::table('coupons', function (Blueprint $t) { $t->boolean('is_public')->default(false); });
    if (!Schema::hasTable('customer_addresses')) Schema::create('customer_addresses', function (Blueprint $t) {
      $t->id(); $t->foreignId('customer_id')->index(); $t->string('label', 20)->default('Home'); $t->string('receiver', 80); $t->string('phone', 20);
      $t->string('district', 60)->nullable(); $t->string('thana', 60)->nullable(); $t->string('area', 80)->nullable(); $t->string('road', 200)->nullable(); $t->string('landmark', 120)->nullable();
      $t->boolean('is_default')->default(false); $t->timestamps();
    });
    if (!Schema::hasTable('customer_wishlist')) Schema::create('customer_wishlist', function (Blueprint $t) {
      $t->id(); $t->foreignId('customer_id')->index(); $t->foreignId('product_id'); $t->timestamps(); $t->unique(['customer_id', 'product_id']);
    });
    if (!Schema::hasTable('customer_sessions')) Schema::create('customer_sessions', function (Blueprint $t) {
      $t->id(); $t->foreignId('customer_id')->index(); $t->string('token_hash', 64)->unique(); $t->string('device', 120)->nullable(); $t->string('ip', 45)->nullable();
      $t->timestamp('last_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
