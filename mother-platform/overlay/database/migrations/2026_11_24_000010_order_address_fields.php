<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Area / thana / city / district read from the customer's one address box. Existing orders are not touched (all new columns are empty). */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      foreach (['addr_area' => 80, 'addr_thana' => 80, 'addr_city' => 80, 'addr_district' => 60, 'addr_note' => 160] as $c => $len) if (!Schema::hasColumn('orders', $c)) $t->string($c, $len)->nullable();
      if (!Schema::hasColumn('orders', 'addr_check')) $t->smallInteger('addr_check')->nullable(); // null = not read yet, 0 = read, 1 = a person must check, 2 = checked by staff
    });
    try { \Illuminate\Support\Facades\DB::statement('create index if not exists orders_addr_check_idx on orders (addr_check, id) where addr_check = 1'); } catch (\Throwable $e) {}
    try { \Illuminate\Support\Facades\DB::statement('create index if not exists orders_addr_district_idx on orders (addr_district, created_at)'); } catch (\Throwable $e) {}
  }
  public function down(): void {
    try { \Illuminate\Support\Facades\DB::statement('drop index if exists orders_addr_check_idx'); \Illuminate\Support\Facades\DB::statement('drop index if exists orders_addr_district_idx'); } catch (\Throwable $e) {}
    Schema::table('orders', function (Blueprint $t) { foreach (['addr_area', 'addr_thana', 'addr_city', 'addr_district', 'addr_note', 'addr_check'] as $c) if (Schema::hasColumn('orders', $c)) $t->dropColumn($c); });
  }
};
