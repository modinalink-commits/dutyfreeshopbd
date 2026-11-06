<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasColumn('orders', 'benefit_status_snapshot')) return;
    Schema::table('orders', function (Blueprint $t) {
      $t->string('benefit_status_snapshot', 30)->nullable(); $t->string('benefit_reason_snapshot', 200)->nullable();
      $t->string('membership_snapshot', 60)->nullable(); $t->string('discount_type_snapshot', 200)->nullable();
    });
  }
  public function down(): void {}
};
