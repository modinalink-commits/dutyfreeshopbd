<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Parcel went out as courier COD but the customer paid DutyFree directly. Payment is flagged cod_direct; once the owner verifies it the courier COD on the order is reduced and the courier must be informed. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('order_payments') && !Schema::hasColumn('order_payments', 'cod_direct')) Schema::table('order_payments', fn($t) => $t->boolean('cod_direct')->default(false));
    if (!Schema::hasColumn('orders', 'cod_zeroed_at')) Schema::table('orders', function ($t) {
      $t->decimal('cod_direct_amount', 12, 2)->nullable(); $t->timestamp('cod_zeroed_at')->nullable(); $t->timestamp('pathao_informed_at')->nullable(); $t->string('pathao_informed_by', 120)->nullable();
    });
    DB::statement("create index if not exists orders_cod_inform_idx on orders (cod_zeroed_at) where cod_zeroed_at is not null and pathao_informed_at is null");
  }
  public function down(): void { DB::statement('drop index if exists orders_cod_inform_idx'); }
};
