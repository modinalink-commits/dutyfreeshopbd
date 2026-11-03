<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('customer_calls')) return;
    Schema::table('customer_calls', function (Blueprint $t) {
      if (!Schema::hasColumn('customer_calls', 'ip_address')) $t->string('ip_address', 64)->nullable()->index();
      if (!Schema::hasColumn('customer_calls', 'user_agent')) $t->string('user_agent', 255)->nullable();
    });
  }
  public function down(): void {}
};
