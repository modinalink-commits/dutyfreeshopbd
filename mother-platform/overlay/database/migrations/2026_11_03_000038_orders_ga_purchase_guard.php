<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** One order = one browser Purchase: the success/track page claims this stamp atomically before firing GA4/Meta. Existing orders are stamped so old orders never re-fire. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('orders', 'ga_purchase_at')) {
      Schema::table('orders', function (Blueprint $t) { $t->timestamp('ga_purchase_at')->nullable(); });
      DB::table('orders')->update(['ga_purchase_at' => now()]);
    }
  }
  public function down(): void {}
};
