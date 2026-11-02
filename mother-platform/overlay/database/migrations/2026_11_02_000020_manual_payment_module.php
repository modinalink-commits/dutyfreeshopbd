<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders','pay_note')) $t->text('pay_note')->nullable();
      if (!Schema::hasColumn('orders','pay_reason')) $t->string('pay_reason', 120)->nullable();
      if (!Schema::hasColumn('orders','pay_amount')) $t->decimal('pay_amount', 12, 2)->nullable();
    });
    try { \Illuminate\Support\Facades\DB::statement('create index if not exists orders_pay_status_idx on orders (pay_status)'); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
