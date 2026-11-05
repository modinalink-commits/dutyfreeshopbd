<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('role', 20)->index(); // driver, salesman, warehouse, manager, accountant, other
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('markets', 255)->nullable();
            $table->unsignedInteger('shops')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('reg_no', 40)->nullable();
            $table->string('type', 40)->default('Pickup');
            $table->unsignedInteger('capacity')->default(0);
            $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('Available');
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_no', 20)->unique();
            $table->date('trip_date')->index();
            $table->foreignId('vehicle_id')->constrained('vehicles');
            $table->foreignId('route_id')->nullable()->constrained('routes');
            $table->foreignId('driver_id')->nullable()->constrained('employees');
            $table->foreignId('salesman_id')->nullable()->constrained('employees');
            $table->foreignId('warehouse_staff_id')->nullable()->constrained('employees');
            $table->string('loading_time', 5)->nullable();
            $table->string('status', 16)->default('draft')->index(); // draft, dispatched, returned, settled, closed
            $table->string('remarks', 255)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users');
            $table->timestamp('counted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('dispatched_at')->nullable();
            $table->string('return_time', 5)->nullable();
            $table->foreignId('return_checked_by')->nullable()->constrained('employees');
            $table->string('return_remarks', 255)->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->json('cash_notes')->nullable();
            $table->decimal('cash_counted', 12, 2)->nullable();
            $table->decimal('digital_verified', 12, 2)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('trip_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('size_kg');
            $table->unsignedInteger('qty');
        });
        Schema::create('trip_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->unsignedSmallInteger('size_kg');
            $table->unsignedInteger('full_returned')->default(0);
            $table->unsignedInteger('damaged')->default(0);
            $table->unsignedInteger('empty_returned')->default(0);
        });
        Schema::create('trip_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips');
            $table->string('kind', 10); // stock, cash
            $table->string('detail', 255);
            $table->string('reason', 255);
            $table->foreignId('approved_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::table('stock_movements', fn (Blueprint $t) => $t->foreignId('trip_id')->nullable()->index()->constrained('trips'));
        Schema::table('sales', fn (Blueprint $t) => $t->foreignId('trip_id')->nullable()->index()->constrained('trips'));
        Schema::table('customer_ledger', fn (Blueprint $t) => $t->foreignId('trip_id')->nullable()->index()->constrained('trips'));
        Schema::table('expenses', function (Blueprint $t) {
            $t->foreignId('trip_id')->nullable()->index()->constrained('trips');
            $t->boolean('from_collection')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('trip_id'));
        Schema::table('expenses', fn (Blueprint $t) => $t->dropColumn('from_collection'));
        foreach (['customer_ledger', 'sales', 'stock_movements'] as $tb) Schema::table($tb, fn (Blueprint $t) => $t->dropConstrainedForeignId('trip_id'));
        foreach (['trip_adjustments', 'trip_returns', 'trip_loads', 'trips', 'vehicles', 'routes', 'employees'] as $t) Schema::dropIfExists($t);
    }
};
