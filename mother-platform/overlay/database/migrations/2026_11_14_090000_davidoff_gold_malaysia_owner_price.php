<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision (14 Nov): Davidoff Gold Malaysia (product 1242) is sold below the protected margin on purpose.
 * The automatic raise to the minimum safe price (3,199 -> 3,960) is taken back for this one product. Logged in price history and audit.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products')) return;
    try {
      $p = DB::table('products')->where('id', 1242)->first();
      if (!$p || stripos((string)$p->name, 'Davidoff Gold Malaysia') === false || abs((float)$p->price - 3960) > 0.01) return;
      DB::transaction(function () use ($p) {
        DB::table('products')->where('id', $p->id)->update(['price' => 3199, 'updated_at' => now()]);
        if (Schema::hasTable('product_price_history')) DB::table('product_price_history')->insert(['product_id' => $p->id, 'old_rate' => 3960, 'new_rate' => 3199, 'purchase_rate' => 0, 'changed_by' => 'Owner', 'source' => 'Owner decision', 'note' => 'Owner: sold below protected margin on purpose; raise to safe price taken back', 'created_at' => now(), 'updated_at' => now()]);
        if (Schema::hasTable('audit_logs')) DB::table('audit_logs')->insert(['user_name' => 'Owner', 'action' => 'price.set', 'subject' => 'Sale price set: ' . $p->name, 'meta' => json_encode(['previous' => ['price' => 3960], 'new' => ['price' => 3199, 'reason' => 'Owner decision: sold below protected margin on purpose']]), 'created_at' => now(), 'updated_at' => now()]);
      });
      try { \App\Support\BundlePricing::flush(); \App\Support\BundlePricing::sync([1242]); } catch (\Throwable $e) {}
      try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
