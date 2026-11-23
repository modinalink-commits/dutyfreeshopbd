<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Division read from the address, next to area / thana / city / district. Existing orders are not touched. */
return new class extends Migration {
  public function up(): void { if (!Schema::hasColumn('orders', 'addr_division')) Schema::table('orders', fn(Blueprint $t) => $t->string('addr_division', 40)->nullable()); }
  public function down(): void { if (Schema::hasColumn('orders', 'addr_division')) Schema::table('orders', fn(Blueprint $t) => $t->dropColumn('addr_division')); }
};
