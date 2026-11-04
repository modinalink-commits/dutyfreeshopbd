<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\BundlePricing;

/**
 * Global Sales Pricing & Bundle Discount Automation.
 *  - products.bundle_rule: 'global' (default, automatic) | 'custom' (hand-typed Buy 2-5 totals) | 'off'.
 *  - order_items: pricing snapshot (sale price, bundle discount requested/applied, unit/total, FIFO cost, minimum safe price, profit, rule version).
 *  - settings: global ratios (1.5 / 2.5 / 3.5 / 4.5 %), unsafe handling, rounding, rule version.
 *  - Every product is switched to the global rule and its Buy 2-5 prices are recalculated from the current sale price and FIFO cost (capped at the 15% floor).
 * Idempotent: the settings are only written when missing; the recalculation can run any number of times.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasTable('order_items')) return;
    if (!Schema::hasColumn('products', 'bundle_rule')) Schema::table('products', function (Blueprint $t) { $t->string('bundle_rule', 10)->default('global'); });
    Schema::table('order_items', function (Blueprint $t) {
      $c = fn($n) => Schema::hasColumn('order_items', $n);
      if (!$c('original_sale_price')) $t->decimal('original_sale_price', 12, 2)->nullable();
      if (!$c('bundle_quantity')) $t->integer('bundle_quantity')->nullable();
      if (!$c('requested_bundle_discount')) $t->decimal('requested_bundle_discount', 6, 2)->nullable();
      if (!$c('applied_bundle_discount')) $t->decimal('applied_bundle_discount', 6, 2)->nullable();
      if (!$c('bundle_unit_price')) $t->decimal('bundle_unit_price', 12, 2)->nullable();
      if (!$c('bundle_total')) $t->decimal('bundle_total', 12, 2)->nullable();
      if (!$c('fifo_cost_snapshot')) $t->decimal('fifo_cost_snapshot', 12, 4)->nullable();
      if (!$c('minimum_margin_snapshot')) $t->decimal('minimum_margin_snapshot', 5, 2)->nullable();
      if (!$c('minimum_safe_price_snapshot')) $t->decimal('minimum_safe_price_snapshot', 12, 2)->nullable();
      if (!$c('profit_snapshot')) $t->decimal('profit_snapshot', 12, 2)->nullable();
      if (!$c('profit_percent_snapshot')) $t->decimal('profit_percent_snapshot', 8, 2)->nullable();
      if (!$c('pricing_rule_version')) $t->integer('pricing_rule_version')->nullable();
    });
    $def = ['bundle_on' => '1', 'bundle_pct_2' => '1.5', 'bundle_pct_3' => '2.5', 'bundle_pct_4' => '3.5', 'bundle_pct_5' => '4.5', 'bundle_unsafe' => 'reduce', 'bundle_round' => 'none', 'bundle_rule_version' => '1'];
    $first = !DB::table('settings')->where('key', 'bundle_pct_2')->exists();
    foreach ($def as $k => $v) if (!DB::table('settings')->where('key', $k)->exists()) DB::table('settings')->insert(['key' => $k, 'value' => $v, 'created_at' => now(), 'updated_at' => now()]);
    if ($first) {
      try {
        BundlePricing::flush(); [$ch, $cap, $dis] = BundlePricing::sync();
        DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Global bundle pricing activated', 'subject' => "Buy 2/3/4/5 = 1.5/2.5/3.5/4.5% of the sale price, capped at FIFO cost x 1.15. $ch products recalculated, $cap auto-reduced, $dis with some tiers unavailable.",
          'meta' => json_encode(['previous' => 'Fixed 2/3/4/5% pack prices', 'new' => 'Global ratio 1.5/2.5/3.5/4.5% with automatic safe cap'], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
      } catch (\Throwable $e) { report($e); }
    }
  }
  public function down(): void {}
};
