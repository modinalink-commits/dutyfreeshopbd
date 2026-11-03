<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Pathao outcome sync: a manual-review flag ("Billing Mismatch" / "Item Review") on the order. */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'billing_flag')) $t->string('billing_flag', 30)->nullable()->index();
      if (!Schema::hasColumn('orders', 'billing_flag_note')) $t->text('billing_flag_note')->nullable();
    });
  }
  public function down(): void {}
};
