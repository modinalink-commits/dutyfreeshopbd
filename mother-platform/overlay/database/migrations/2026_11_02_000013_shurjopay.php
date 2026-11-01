<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'sp_order_id')) $t->string('sp_order_id', 80)->nullable()->index();
      if (!Schema::hasColumn('orders', 'sp_method')) $t->string('sp_method', 60)->nullable();
    });
  }
  public function down(): void {}
};
