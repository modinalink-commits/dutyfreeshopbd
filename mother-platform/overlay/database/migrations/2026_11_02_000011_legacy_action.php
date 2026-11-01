<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('legacy_customers')) return;
    Schema::table('legacy_customers', function (Blueprint $t) {
      if (!Schema::hasColumn('legacy_customers', 'action')) $t->string('action', 10)->nullable()->index();
      if (!Schema::hasColumn('legacy_customers', 'new_customer_id')) $t->unsignedBigInteger('new_customer_id')->nullable();
    });
  }
  public function down(): void {}
};
