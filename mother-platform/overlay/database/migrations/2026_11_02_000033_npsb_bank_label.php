<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Customer-facing label "Bank Transfer" becomes "NPSB Bank Transfer" (history included). Codes and logic untouched. */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    DB::table('orders')->where('payment_method', 'Bank Transfer')->update(['payment_method' => 'NPSB Bank Transfer']);
    DB::table('order_payments')->where('method', 'Bank Transfer')->update(['method' => 'NPSB Bank Transfer']);
  }
  public function down(): void {}
};
