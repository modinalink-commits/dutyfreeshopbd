<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner 17 Nov 2026: keep the courier rider's name and number with the order (Pathao gives them once the parcel is assigned for delivery), so a rider's call is recognised. Additive only. */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'rider_name')) $t->string('rider_name', 120)->nullable();
      if (!Schema::hasColumn('orders', 'rider_phone')) $t->string('rider_phone', 20)->nullable()->index();
      if (!Schema::hasColumn('orders', 'rider_at')) $t->timestamp('rider_at')->nullable();
    });
  }
  public function down(): void {
    Schema::table('orders', function (Blueprint $t) { foreach (['rider_at', 'rider_phone', 'rider_name'] as $c) if (Schema::hasColumn('orders', $c)) $t->dropColumn($c); });
  }
};
