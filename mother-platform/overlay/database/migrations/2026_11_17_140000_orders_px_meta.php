<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Meta events (17 Nov 2026): orders.px_meta keeps the visitor's Meta browser identifiers (_fbp, _fbc, IP, browser) from the moment the order is placed, so the later server events (OrderDelivered / OrderReturned, online-paid Purchase) can be matched to the ad click. Nullable, no index (read by order id only). down() drops the column. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('orders', 'px_meta')) Schema::table('orders', function (Blueprint $t) { $t->text('px_meta')->nullable(); });
  }
  public function down(): void {
    if (Schema::hasColumn('orders', 'px_meta')) Schema::table('orders', function (Blueprint $t) { $t->dropColumn('px_meta'); });
  }
};
