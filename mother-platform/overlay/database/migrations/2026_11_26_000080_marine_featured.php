<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: "Feature on Homepage". Position 1-8 of a product in the home page product row of marinedutyfreebd.com; null = not picked. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('marine_products') && !Schema::hasColumn('marine_products', 'featured')) Schema::table('marine_products', function (Blueprint $t) { $t->unsignedTinyInteger('featured')->nullable()->index(); });
  }
  public function down(): void {
    if (Schema::hasTable('marine_products') && Schema::hasColumn('marine_products', 'featured')) Schema::table('marine_products', function (Blueprint $t) { $t->dropColumn('featured'); });
  }
};
