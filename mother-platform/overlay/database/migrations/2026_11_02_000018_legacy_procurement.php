<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('suppliers', 'legacy_supplier_id')) Schema::table('suppliers', function (Blueprint $t) { $t->unsignedBigInteger('legacy_supplier_id')->nullable()->unique(); });
    if (!Schema::hasColumn('purchases', 'legacy_purchase_id')) Schema::table('purchases', function (Blueprint $t) { $t->unsignedBigInteger('legacy_purchase_id')->nullable()->unique(); });
  }
  public function down(): void {}
};
