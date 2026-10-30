<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::create('coupons', function (Blueprint $t) { $t->id(); $t->string('code')->unique(); $t->string('type')->default('percent'); $t->decimal('value',12,2)->default(0); $t->decimal('min_order',12,2)->default(0); $t->decimal('max_discount',12,2)->nullable(); $t->integer('usage_limit')->nullable(); $t->integer('per_customer')->default(1); $t->integer('used_count')->default(0); $t->date('valid_from')->nullable(); $t->date('valid_to')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('discount_rules', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->string('title'); $t->text('help')->nullable(); $t->boolean('enabled')->default(false); $t->json('config'); $t->integer('position')->default(0); $t->timestamps(); });
    Schema::create('product_status_logs', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->index(); $t->foreignId('user_id')->nullable(); $t->string('user_name')->nullable(); $t->string('action'); $t->timestamps(); });
  }
  public function down(): void {}
};
