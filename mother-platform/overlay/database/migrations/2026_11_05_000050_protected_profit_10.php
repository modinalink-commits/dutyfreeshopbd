<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\BundlePricing;

/**
 * Owner instruction: the minimum protected profit moves from 15% to 10% as ONE central setting (settings.minimum_protected_profit_percent).
 * The 15% era is kept in settings.minimum_protected_profit_history so an order placed before the change is still judged by 15%.
 * Existing sale prices are NOT touched; only the minimum safe price / discountable amount / bundle safe caps are recalculated.
 */
return new class extends Migration {
  public function up(): void {
    $put = function (string $k, string $v) { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); };
    $had = DB::table('settings')->where('key', 'minimum_protected_profit_percent')->exists();
    if ($had) return; // already set by an admin: leave it alone
    $put('minimum_protected_profit_history', '[{"from":"2000-01-01 00:00:00","percent":15}]');
    $put('minimum_protected_profit_from', '2026-11-05 12:25:00');
    $put('minimum_protected_profit_percent', '10');
    try {
      BundlePricing::flush(); [$ch, $cap, $dis] = BundlePricing::sync();
      DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Minimum protected profit changed', 'subject' => "Minimum protected margin changed from 15% to 10% (owner instruction). Minimum Safe Price = FIFO cost x 1.10. $ch products' bundle prices recalculated, $cap auto-reduced.",
        'meta' => json_encode(['old' => 15, 'new' => 10, 'reason' => 'Owner instruction: global margin rule change 15% to 10%', 'historical_orders' => 'keep the rule active when they were created', 'sale_prices' => 'unchanged'], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
