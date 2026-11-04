<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('category', 20)->index(); // refill, new_package, accessory, service
            $table->foreignId('brand_id')->nullable()->constrained('brands');
            $table->unsignedSmallInteger('size_kg')->nullable();
            $table->decimal('retail_price', 12, 2)->nullable();
            $table->decimal('dealer_price', 12, 2)->nullable();
            $table->string('note', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Accessories stock (cylinders use stock_movements). Immutable like the cylinder ledger.
        Schema::create('item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->integer('qty_delta');
            $table->string('kind', 20);
            $table->string('reference', 60)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 120);
            $table->string('phone', 20)->unique();
            $table->string('type', 12)->default('retail'); // retail, commercial, dealer
            $table->string('area', 80)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0); // 0 = credit off (default)
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique();
            $table->string('type', 10)->index(); // retail, wholesale
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('walkin_name', 120)->nullable();
            $table->string('walkin_phone', 20)->nullable();
            $table->string('stage', 12)->default('posted')->index(); // retail: posted; wholesale: waiting, on_road, delivered; voided
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->string('payment_method', 12)->nullable();
            $table->foreignId('empty_brand_id')->nullable()->constrained('brands');
            $table->unsignedInteger('empties_expected')->default(0);
            $table->unsignedInteger('empties_received')->default(0);
            $table->string('vehicle', 60)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('description', 160);
            $table->string('category', 20);
            $table->foreignId('brand_id')->nullable()->constrained('brands');
            $table->unsignedSmallInteger('size_kg')->nullable();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
        });

        // Customer money ledger: debit = bill, credit = payment. Never edited; voids post a reversing line.
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('kind', 16); // opening, sale, payment, void, adjustment
            $table->string('reference', 40)->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('method', 12)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index('customer_id');
        });

        // Our cylinders held by customers (positive = they hold ours / owe empties).
        Schema::create('customer_cylinders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('brand_id')->constrained('brands');
            $table->unsignedSmallInteger('size_kg');
            $table->integer('qty_delta');
            $table->string('reference', 40)->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->timestamp('created_at')->useCurrent();
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        foreach (['customer_cylinders', 'customer_ledger', 'sale_items', 'sales', 'customers', 'item_movements', 'products'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
