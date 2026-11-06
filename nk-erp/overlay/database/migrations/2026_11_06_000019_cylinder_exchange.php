<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra charge when a customer swaps one brand's cylinder for another's, set per other brand and size.
        Schema::create('exchange_charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
            $t->unsignedSmallInteger('size_kg');
            $t->decimal('charge', 12, 2)->default(0);
            $t->foreignId('updated_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['brand_id', 'size_kg']);
        });

        // One brand swap: the customer hands over an empty of one brand and takes a full of another.
        Schema::create('cylinder_exchanges', function (Blueprint $t) {
            $t->id();
            $t->string('ref_no', 20)->unique();
            $t->string('channel', 10)->default('counter'); // counter | route
            $t->foreignId('trip_id')->nullable()->constrained('trips');
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->string('walkin_name', 120)->nullable();
            $t->string('walkin_phone', 20)->nullable();
            $t->unsignedSmallInteger('size_kg');
            $t->unsignedInteger('qty');
            $t->foreignId('given_brand_id')->constrained('brands');  // empty the customer gives us
            $t->foreignId('taken_brand_id')->constrained('brands');  // full we give the customer
            $t->decimal('unit_price', 12, 2);
            $t->decimal('charge_unit', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->decimal('paid', 12, 2)->default(0);
            $t->string('method', 10)->default('cash');
            $t->string('status', 12)->default('pending'); // pending | approved | rejected
            $t->foreignId('sale_id')->nullable()->constrained('sales');
            $t->string('note', 255)->nullable();
            $t->string('decision_note', 255)->nullable();
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_exchanges');
        Schema::dropIfExists('exchange_charges');
    }
};
