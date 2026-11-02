<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('supplier_payments', 'legacy_payment_id')) Schema::table('supplier_payments', function (Blueprint $t) { $t->unsignedBigInteger('legacy_payment_id')->nullable()->unique(); });
  }
  public function down(): void {}
};
