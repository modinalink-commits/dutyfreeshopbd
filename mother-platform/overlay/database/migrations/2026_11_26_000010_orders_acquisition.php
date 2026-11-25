<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where a website order's buyer came from (VisitTrack source of the visit that ordered) and whether it was that phone's first order. Kept with the order; old orders stay empty (unknown). */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'acq_source')) $t->string('acq_source', 24)->nullable()->index();
      if (!Schema::hasColumn('orders', 'acq_campaign')) $t->string('acq_campaign', 80)->nullable();
      if (!Schema::hasColumn('orders', 'new_customer')) $t->boolean('new_customer')->nullable();
    });
  }
  public function down(): void {
    Schema::table('orders', function (Blueprint $t) { foreach (['acq_source', 'acq_campaign', 'new_customer'] as $c) if (Schema::hasColumn('orders', $c)) $t->dropColumn($c); });
  }
};
