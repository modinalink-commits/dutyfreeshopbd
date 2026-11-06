<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'successful_received_count_snapshot')) $t->integer('successful_received_count_snapshot')->nullable();
      if (!Schema::hasColumn('orders', 'applied_discounts_snapshot')) $t->text('applied_discounts_snapshot')->nullable();
      if (!Schema::hasColumn('orders', 'available_discounts_snapshot')) $t->text('available_discounts_snapshot')->nullable();
    });
  }
  public function down(): void {}
};
