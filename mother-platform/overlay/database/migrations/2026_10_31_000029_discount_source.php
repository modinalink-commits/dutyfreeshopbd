<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      foreach (['discount_type' => 250, 'discount_rule' => 250, 'discount_source' => 250, 'discount_by' => 12, 'customer_category' => 20, 'membership_status' => 80] as $c => $n) if (!Schema::hasColumn('orders', $c)) $t->string($c, $n)->nullable();
      if (!Schema::hasColumn('orders', 'discount_pct')) $t->decimal('discount_pct', 6, 2)->nullable();
    });
    // New, switched-off rules. Nothing applies until an admin turns them on in Discount Center.
    $pos = (int)DB::table('discount_rules')->max('position');
    foreach ([['vip', 'VIP customer discount', 'Customers marked VIP get a percent or a fixed amount off every order.', ['Discount %' => 0, 'Fixed TK' => 0]],
              ['returning', 'Returning customer offer', 'Customers who ordered before get a percent off.', ['Discount %' => 0, 'Max TK' => 0]]] as $i => $r)
      if (!DB::table('discount_rules')->where('key', $r[0])->exists()) DB::table('discount_rules')->insert(['key' => $r[0], 'title' => $r[1], 'help' => $r[2], 'enabled' => false, 'config' => json_encode($r[3]), 'position' => $pos + 1 + $i, 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void {}
};
