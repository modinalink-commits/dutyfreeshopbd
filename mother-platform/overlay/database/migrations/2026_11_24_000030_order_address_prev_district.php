<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keeps what the district field held before the address reader replaced a price label, so the change can be undone. */
return new class extends Migration {
  public function up(): void { if (!Schema::hasColumn('orders', 'addr_prev_district')) Schema::table('orders', fn(Blueprint $t) => $t->string('addr_prev_district', 120)->nullable()); }
  public function down(): void { if (Schema::hasColumn('orders', 'addr_prev_district')) Schema::table('orders', fn(Blueprint $t) => $t->dropColumn('addr_prev_district')); }
};
