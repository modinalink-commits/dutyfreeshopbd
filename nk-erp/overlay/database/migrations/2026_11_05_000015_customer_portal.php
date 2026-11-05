<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->boolean('portal_enabled')->default(true);
            $t->timestamp('portal_last_seen')->nullable();
            $t->string('owner_name', 120)->nullable();
        });
        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash', 255);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 20)->unique();
            $table->foreignId('customer_id')->constrained('customers');
            $table->json('items'); // [{size, qty, type}]
            $table->string('address', 255)->nullable();
            $table->date('wanted_on')->nullable();
            $table->string('note', 255)->nullable();
            $table->string('status', 12)->default('new')->index(); // new, approved, on_trip, delivered, rejected
            $table->foreignId('route_id')->nullable()->constrained('routes');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignId('salesman_id')->nullable()->constrained('employees');
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->date('delivery_date')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
        Schema::dropIfExists('customer_otps');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['portal_enabled', 'portal_last_seen', 'owner_name']));
    }
};
