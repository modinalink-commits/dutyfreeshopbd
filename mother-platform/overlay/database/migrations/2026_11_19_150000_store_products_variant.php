<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Source products that have several options (e.g. a 3 / 5 / 9 ml decant) are imported at the price of the FIRST option. Keep that option's name so the product page can say which size the price is for. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('store_products') && !Schema::hasColumn('store_products', 'variant')) Schema::table('store_products', function (Blueprint $t) { $t->string('variant', 120)->nullable(); });
  }
  public function down(): void {
    if (Schema::hasTable('store_products') && Schema::hasColumn('store_products', 'variant')) Schema::table('store_products', function (Blueprint $t) { $t->dropColumn('variant'); });
  }
};
