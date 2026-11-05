<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A sale made offline carries the phone's own id, so syncing twice can never make two invoices.
        Schema::table('sales', fn (Blueprint $t) => $t->string('client_tx', 40)->nullable()->unique());
        Schema::table('customer_ledger', fn (Blueprint $t) => $t->string('client_tx', 40)->nullable()->unique());
        Schema::table('trips', fn (Blueprint $t) => $t->timestamp('return_requested_at')->nullable());
        Schema::create('duty_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('trip_id')->nullable()->constrained('trips');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('gps_consent')->default(false);
            $table->timestamps();
        });
        // Only recorded while on duty, and only after the phone's location permission is given.
        Schema::create('gps_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_id')->constrained('duty_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->index();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->timestamp('recorded_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_points');
        Schema::dropIfExists('duty_sessions');
        Schema::table('trips', fn (Blueprint $t) => $t->dropColumn('return_requested_at'));
        Schema::table('customer_ledger', fn (Blueprint $t) => $t->dropColumn('client_tx'));
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('client_tx'));
    }
};
