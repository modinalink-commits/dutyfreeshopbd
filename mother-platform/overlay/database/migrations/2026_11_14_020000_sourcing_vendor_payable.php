<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Owner (14 Nov 2026): the vendors a sourcing person buys from are paid by DutyFreeBD, so every sourced line keeps the supplier name and whether that supplier has been paid. */
return new class extends Migration {
  public function up(): void {
    try {
      if (Schema::hasTable('order_items')) Schema::table('order_items', function (Blueprint $t) {
        if (!Schema::hasColumn('order_items', 'sourced_vendor')) $t->string('sourced_vendor', 160)->nullable();
        if (!Schema::hasColumn('order_items', 'vendor_paid_at')) $t->timestamp('vendor_paid_at')->nullable();
        if (!Schema::hasColumn('order_items', 'vendor_paid_by')) $t->unsignedBigInteger('vendor_paid_by')->nullable();
      });
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
