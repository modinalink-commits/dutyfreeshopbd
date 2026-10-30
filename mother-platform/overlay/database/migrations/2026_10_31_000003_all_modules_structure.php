<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::create('attributes', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('attribute_values', function (Blueprint $t) { $t->id(); $t->foreignId('attribute_id')->index(); $t->string('value'); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('product_variants', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->index(); $t->string('sku')->nullable(); $t->json('options')->nullable(); $t->decimal('price',12,2)->nullable(); $t->integer('stock')->default(0); $t->timestamps(); });
    Schema::create('product_images', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->index(); $t->string('path'); $t->integer('position')->default(0); $t->timestamps(); });
    Schema::create('reviews', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->index(); $t->foreignId('customer_id')->nullable(); $t->foreignId('order_id')->nullable(); $t->string('name'); $t->unsignedTinyInteger('rating'); $t->text('body')->nullable(); $t->boolean('verified_buyer')->default(false); $t->string('status')->default('pending'); $t->text('reply')->nullable(); $t->timestamps(); });
    Schema::create('product_views', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->nullable(); $t->string('term')->nullable(); $t->date('day')->nullable()->index(); $t->timestamps(); });
    Schema::create('suppliers', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('phone')->nullable(); $t->text('address')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('purchases', function (Blueprint $t) { $t->id(); $t->string('invoice')->unique(); $t->foreignId('supplier_id')->index(); $t->decimal('total',14,2)->default(0); $t->decimal('paid',14,2)->default(0); $t->date('purchase_date'); $t->text('note')->nullable(); $t->foreignId('user_id')->nullable(); $t->timestamps(); });
    Schema::create('purchase_items', function (Blueprint $t) { $t->id(); $t->foreignId('purchase_id')->index(); $t->foreignId('product_id')->nullable(); $t->string('name'); $t->integer('qty'); $t->decimal('cost',12,2); $t->timestamps(); });
    Schema::create('stock_logs', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->index(); $t->string('source'); $t->string('reference')->nullable(); $t->integer('before_qty'); $t->integer('change_qty'); $t->integer('after_qty'); $t->foreignId('user_id')->nullable(); $t->string('user_name')->nullable(); $t->timestamps(); });
    Schema::create('damaged_products', function (Blueprint $t) { $t->id(); $t->foreignId('product_id')->index(); $t->integer('qty'); $t->text('reason')->nullable(); $t->foreignId('user_id')->nullable(); $t->timestamps(); });
    Schema::create('couriers', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('active')->default(false); $t->boolean('is_default')->default(false); $t->json('settings')->nullable(); $t->timestamps(); });
    Schema::create('districts', function (Blueprint $t) { $t->id(); $t->string('name'); $t->decimal('delivery_charge',10,2)->default(0); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('sub_districts', function (Blueprint $t) { $t->id(); $t->foreignId('district_id')->index(); $t->string('name'); $t->decimal('delivery_charge',10,2)->nullable(); $t->timestamps(); });
    Schema::create('order_comments', function (Blueprint $t) { $t->id(); $t->string('text'); $t->string('kind')->nullable(); $t->timestamps(); });
    Schema::create('order_sources', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('money_accounts', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('type')->default('bank'); $t->decimal('opening',14,2)->default(0); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('expense_purposes', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('is_expense')->default(true); $t->timestamps(); });
    Schema::create('account_entries', function (Blueprint $t) { $t->id(); $t->string('reference')->unique(); $t->string('direction'); $t->foreignId('account_id')->index(); $t->foreignId('purpose_id')->nullable(); $t->decimal('amount',14,2); $t->text('comment')->nullable(); $t->date('entry_date')->index(); $t->foreignId('user_id')->nullable(); $t->string('user_name')->nullable(); $t->timestamps(); });
    Schema::create('bill_statements', function (Blueprint $t) { $t->id(); $t->string('name'); $t->decimal('amount',14,2)->default(0); $t->date('bill_date')->nullable(); $t->string('status')->default('unpaid'); $t->timestamps(); });
    Schema::create('withdraw_requests', function (Blueprint $t) { $t->id(); $t->foreignId('customer_id')->index(); $t->string('method'); $t->decimal('amount',14,2); $t->string('transaction_id')->nullable(); $t->string('status')->default('pending'); $t->text('note')->nullable(); $t->timestamps(); });
    Schema::create('membership_levels', function (Blueprint $t) { $t->id(); $t->string('name'); $t->decimal('min_spent',14,2)->default(0); $t->decimal('discount_percent',5,2)->default(0); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('loyalty_points', function (Blueprint $t) { $t->id(); $t->foreignId('customer_id')->index(); $t->integer('points'); $t->string('reason')->nullable(); $t->timestamps(); });
    Schema::create('contact_messages', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->text('message'); $t->string('status')->default('new'); $t->timestamps(); });
    Schema::create('blocked_ips', function (Blueprint $t) { $t->id(); $t->string('ip')->unique(); $t->string('reason')->nullable(); $t->foreignId('user_id')->nullable(); $t->timestamps(); });
    Schema::create('settings', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->text('value')->nullable(); $t->timestamps(); });
    Schema::create('banners', function (Blueprint $t) { $t->id(); $t->string('placement'); $t->string('image')->nullable(); $t->string('link')->nullable(); $t->boolean('active')->default(true); $t->integer('position')->default(0); $t->timestamps(); });
    Schema::create('sliders', function (Blueprint $t) { $t->id(); $t->string('image')->nullable(); $t->string('link')->nullable(); $t->boolean('active')->default(true); $t->integer('position')->default(0); $t->timestamps(); });
    Schema::create('pages', function (Blueprint $t) { $t->id(); $t->string('slug')->unique(); $t->string('title'); $t->text('body')->nullable(); $t->string('meta_title')->nullable(); $t->text('meta_description')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('sms_logs', function (Blueprint $t) { $t->id(); $t->string('to'); $t->text('body'); $t->string('event')->nullable(); $t->string('status')->default('queued'); $t->text('provider_response')->nullable(); $t->timestamps(); });
    Schema::create('email_logs', function (Blueprint $t) { $t->id(); $t->string('to'); $t->string('subject'); $t->string('event')->nullable(); $t->string('status')->default('queued'); $t->timestamps(); });
    Schema::create('campaigns', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('channel'); $t->text('body')->nullable(); $t->string('status')->default('draft'); $t->timestamp('scheduled_at')->nullable(); $t->timestamps(); });
    Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name')->unique(); $t->json('permissions'); $t->timestamps(); });
    Schema::create('audit_logs', function (Blueprint $t) { $t->id(); $t->foreignId('user_id')->nullable(); $t->string('user_name')->nullable(); $t->string('action'); $t->string('subject')->nullable(); $t->json('meta')->nullable(); $t->string('ip')->nullable(); $t->timestamps(); });
    Schema::create('employees', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('department')->nullable(); $t->string('phone')->nullable(); $t->decimal('salary',12,2)->default(0); $t->date('joined_at')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); });
    Schema::create('payroll_entries', function (Blueprint $t) { $t->id(); $t->foreignId('employee_id')->index(); $t->string('month'); $t->decimal('amount',12,2); $t->string('status')->default('unpaid'); $t->timestamps(); });
    Schema::create('sales_channels', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('active')->default(false); $t->json('settings')->nullable(); $t->timestamps(); });
    Schema::table('users', function (Blueprint $t) { $t->foreignId('role_id')->nullable(); $t->string('phone')->nullable(); });
    Schema::table('customers', function (Blueprint $t) { $t->string('type')->default('retail'); $t->integer('points')->default(0); $t->string('ip')->nullable(); });
    Schema::table('products', function (Blueprint $t) { $t->text('description')->nullable(); $t->string('slug')->nullable()->index(); $t->string('meta_title')->nullable(); $t->text('meta_description')->nullable(); });
    Schema::table('orders', function (Blueprint $t) { $t->string('payment_method')->nullable(); $t->decimal('discount',12,2)->default(0); $t->string('coupon')->nullable(); $t->string('tracking_code')->nullable(); });
  }
  public function down(): void {}
};
