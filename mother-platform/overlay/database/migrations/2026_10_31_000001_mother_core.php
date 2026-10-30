<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) { $t->string('role')->default('staff'); $t->boolean('active')->default(true); });
    Schema::create('stores', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('domain')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('categories', function (Blueprint $t) { $t->id(); $t->string('name'); $t->foreignId('parent_id')->nullable(); $t->timestamps(); });
    Schema::create('brands', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('position')->default(0); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('products', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('sku')->nullable()->unique(); $t->foreignId('category_id')->nullable(); $t->foreignId('brand_id')->nullable(); $t->decimal('price',12,2)->default(0); $t->decimal('cost',12,2)->default(0); $t->integer('stock')->default(0); $t->boolean('published')->default(false); $t->timestamps(); });
    Schema::create('customers', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('phone')->index(); $t->string('email')->nullable(); $t->string('membership')->nullable(); $t->boolean('blocked')->default(false); $t->timestamps(); });
    Schema::create('orders', function (Blueprint $t) { $t->id(); $t->string('invoice')->unique(); $t->foreignId('store_id')->nullable(); $t->foreignId('customer_id')->nullable(); $t->string('customer_name'); $t->string('phone')->index(); $t->text('address')->nullable(); $t->string('district')->nullable(); $t->string('source')->default('Web Site'); $t->string('courier')->nullable(); $t->decimal('shipping',12,2)->default(0); $t->decimal('total',12,2)->default(0); $t->string('status')->default('New')->index(); $t->text('note')->nullable(); $t->timestamps(); });
    Schema::create('order_items', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->index(); $t->foreignId('product_id')->nullable(); $t->string('name'); $t->integer('qty')->default(1); $t->decimal('price',12,2)->default(0); $t->timestamps(); });
    Schema::create('order_status_logs', function (Blueprint $t) { $t->id(); $t->foreignId('order_id')->index(); $t->foreignId('user_id')->nullable(); $t->string('user_name')->nullable(); $t->string('from_status')->nullable(); $t->string('to_status'); $t->text('note')->nullable(); $t->timestamps(); });
  }
  public function down(): void {}
};
