<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply: six marine categories (Provisions, Cabin & Galley Stores, Technical Stores, Safety & PPE, Bonded Stores, Crew Shop) on top of the mother catalog,
 *  and a separate bonded approval per account. Columns only: the mother catalog's own categories and products are not touched. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('marine_products')) Schema::table('marine_products', function (Blueprint $t) {
      if (!Schema::hasColumn('marine_products', 'mcat')) $t->string('mcat', 12)->nullable()->index();
      if (!Schema::hasColumn('marine_products', 'msub')) $t->string('msub', 12)->nullable();
      if (!Schema::hasColumn('marine_products', 'mcat_by')) $t->string('mcat_by', 120)->nullable();
    });
    if (Schema::hasTable('marine_accounts')) Schema::table('marine_accounts', function (Blueprint $t) {
      if (!Schema::hasColumn('marine_accounts', 'bonded_ok')) $t->boolean('bonded_ok')->default(false);
      if (!Schema::hasColumn('marine_accounts', 'bonded_by')) $t->string('bonded_by', 120)->nullable();
      if (!Schema::hasColumn('marine_accounts', 'bonded_at')) $t->timestamp('bonded_at')->nullable();
    });
  }
  public function down(): void {
    if (Schema::hasTable('marine_products')) Schema::table('marine_products', function (Blueprint $t) { foreach (['mcat', 'msub', 'mcat_by'] as $c) if (Schema::hasColumn('marine_products', $c)) $t->dropColumn($c); });
    if (Schema::hasTable('marine_accounts')) Schema::table('marine_accounts', function (Blueprint $t) { foreach (['bonded_ok', 'bonded_by', 'bonded_at'] as $c) if (Schema::hasColumn('marine_accounts', $c)) $t->dropColumn($c); });
  }
};
