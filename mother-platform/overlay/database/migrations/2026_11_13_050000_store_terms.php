<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Owner (13 Nov 2026): per-store Terms & Conditions template (pricing, payment, delivery time, fulfilment, returns, shipping, guards, vendor details, pause).
// Every column is nullable / has a safe default, so existing source stores keep working exactly as before (null = use the global setting).
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasTable('stores')) return;
            Schema::table('stores', function (Blueprint $t) {
                $add = function (string $col, callable $def) use ($t) { if (!Schema::hasColumn('stores', $col)) $def($t); };
                $add('sale_pct', fn($t) => $t->decimal('sale_pct', 6, 2)->nullable());
                $add('regular_pct', fn($t) => $t->decimal('regular_pct', 6, 2)->nullable());
                $add('price_min', fn($t) => $t->unsignedInteger('price_min')->nullable());
                $add('price_max', fn($t) => $t->unsignedInteger('price_max')->nullable());
                $add('pay_mode', fn($t) => $t->string('pay_mode', 10)->default('prepaid'));
                $add('delivery_min', fn($t) => $t->unsignedSmallInteger('delivery_min')->nullable());
                $add('delivery_max', fn($t) => $t->unsignedSmallInteger('delivery_max')->nullable());
                $add('delivery_style', fn($t) => $t->string('delivery_style', 8)->default('range'));
                $add('fulfillment', fn($t) => $t->string('fulfillment', 12)->default('dropship'));
                $add('return_days', fn($t) => $t->unsignedSmallInteger('return_days')->nullable());
                $add('return_cost_by', fn($t) => $t->string('return_cost_by', 10)->nullable());
                $add('return_note', fn($t) => $t->string('return_note', 255)->nullable());
                $add('ship_rule', fn($t) => $t->string('ship_rule', 10)->default('default'));
                $add('ship_flat', fn($t) => $t->unsignedInteger('ship_flat')->nullable());
                $add('ship_free_over', fn($t) => $t->unsignedInteger('ship_free_over')->nullable());
                $add('price_jump_pct', fn($t) => $t->unsignedSmallInteger('price_jump_pct')->nullable());
                $add('min_order_value', fn($t) => $t->unsignedInteger('min_order_value')->nullable());
                $add('max_qty', fn($t) => $t->unsignedSmallInteger('max_qty')->nullable());
                $add('max_order_value', fn($t) => $t->unsignedInteger('max_order_value')->nullable());
                $add('cutoff_time', fn($t) => $t->string('cutoff_time', 5)->nullable());
                $add('auth_note', fn($t) => $t->string('auth_note', 160)->nullable());
                $add('vendor_contact', fn($t) => $t->string('vendor_contact', 60)->nullable());
                $add('vendor_pay_terms', fn($t) => $t->string('vendor_pay_terms', 160)->nullable());
                $add('vendor_lead_days', fn($t) => $t->unsignedSmallInteger('vendor_lead_days')->nullable());
                $add('order_channel', fn($t) => $t->string('order_channel', 12)->default('manual'));
                $add('internal_note', fn($t) => $t->text('internal_note')->nullable());
                $add('paused', fn($t) => $t->boolean('paused')->default(false));
                $add('terms_updated_at', fn($t) => $t->timestamp('terms_updated_at')->nullable());
            });
            // Fashion store: delivery window default so the template starts filled
            \Illuminate\Support\Facades\DB::table('stores')->whereNull('delivery_min')->whereNotNull('source_category')->update(['delivery_min' => 3, 'delivery_max' => 5]);
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
