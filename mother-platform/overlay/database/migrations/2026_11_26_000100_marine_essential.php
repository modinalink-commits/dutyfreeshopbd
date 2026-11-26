<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: "Ship essential" mark on a marine product (goods a ship or its crew needs on board). Drives the Ship essentials row and filter on marinedutyfreebd.com. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('marine_products') && !Schema::hasColumn('marine_products', 'essential')) Schema::table('marine_products', function (Blueprint $t) { $t->boolean('essential')->default(false)->index(); });
  }
  public function down(): void {
    if (Schema::hasTable('marine_products') && Schema::hasColumn('marine_products', 'essential')) Schema::table('marine_products', function (Blueprint $t) { $t->dropColumn('essential'); });
  }
};
