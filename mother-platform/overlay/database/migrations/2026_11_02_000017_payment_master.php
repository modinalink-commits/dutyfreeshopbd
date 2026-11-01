<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** payment_methods becomes the single master list (name, logo, status, order, description) for checkout, orders, invoice. Additive. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('payment_methods')) return;
    Schema::table('payment_methods', function (Blueprint $t) {
      if (!Schema::hasColumn('payment_methods', 'logo_url')) $t->string('logo_url', 300)->nullable();
      if (!Schema::hasColumn('payment_methods', 'description')) $t->string('description', 300)->nullable();
      if (!Schema::hasColumn('payment_methods', 'code')) $t->string('code', 30)->nullable();
    });
    DB::table('payment_methods')->whereNull('code')->update(['code' => DB::raw('kind')]);
    $n = 10;
    if (!DB::table('payment_methods')->where('kind', 'cod')->exists()) DB::table('payment_methods')->insert(['kind' => 'cod', 'code' => 'cod', 'title' => 'Cash on Delivery', 'description' => 'Pay when you receive', 'logo_url' => '/pay/cod.png', 'active' => true, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
    if (!DB::table('payment_methods')->where('kind', 'shurjopay')->exists()) DB::table('payment_methods')->insert(['kind' => 'shurjopay', 'code' => 'shurjopay', 'title' => 'Pay Online', 'description' => 'bKash, Nagad, Rocket & Card via ShurjoPay', 'logo_url' => '/pay/shurjopay-logo.png', 'active' => true, 'position' => 0, 'created_at' => now(), 'updated_at' => now()]);
    // renumber: Cash on Delivery, Pay Online, then the rest in their current order
    foreach (['cod', 'shurjopay'] as $k) { DB::table('payment_methods')->where('kind', $k)->update(['position' => $n]); $n += 10; }
    foreach (DB::table('payment_methods')->whereNotIn('kind', ['cod', 'shurjopay'])->orderBy('position')->orderBy('id')->get(['id']) as $r) { DB::table('payment_methods')->where('id', $r->id)->update(['position' => $n]); $n += 10; }
  }
  public function down(): void {}
};
