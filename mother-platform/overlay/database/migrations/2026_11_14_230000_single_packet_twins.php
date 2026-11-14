<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Single Packet twins: every normal product gets a loose-packet twin (1/10 of the carton). Only adds columns; no data is changed here. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products')) return;
    if (!Schema::hasColumn('products', 'parent_product_id')) Schema::table('products', function (Blueprint $t) { $t->unsignedBigInteger('parent_product_id')->nullable()->index(); });
    if (!Schema::hasColumn('products', 'pack_size')) Schema::table('products', function (Blueprint $t) { $t->unsignedSmallInteger('pack_size')->default(10); });
  }
  public function down(): void {
    if (Schema::hasColumn('products', 'parent_product_id')) Schema::table('products', function (Blueprint $t) { $t->dropColumn('parent_product_id'); });
    if (Schema::hasColumn('products', 'pack_size')) Schema::table('products', function (Blueprint $t) { $t->dropColumn('pack_size'); });
  }
};
