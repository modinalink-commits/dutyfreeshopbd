<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner instruction 2026-11-15: when a sourced (vendor) product's price rises, no hold and no notification:
 * the selling price is simply recalculated from the source price + the store's markup, automatically.
 * The per-store "price jump guard" (hide the product when the vendor raises its price by more than N %) is switched off (set to 0)
 * on every store; the old values are kept in stores_price_jump_backup and down() restores them.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('stores') || !Schema::hasColumn('stores', 'price_jump_pct')) return;
    if (!Schema::hasTable('stores_price_jump_backup')) { Schema::create('stores_price_jump_backup', function ($t) { $t->unsignedBigInteger('store_id')->primary(); $t->unsignedSmallInteger('price_jump_pct')->nullable(); $t->timestamps(); }); }
    foreach (DB::table('stores')->whereNotNull('price_jump_pct')->where('price_jump_pct', '>', 0)->get(['id', 'price_jump_pct']) as $s) {
      DB::table('stores_price_jump_backup')->updateOrInsert(['store_id' => $s->id], ['price_jump_pct' => $s->price_jump_pct, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('stores')->where('id', $s->id)->update(['price_jump_pct' => 0]);
    }
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'stores.price_jump_guard_off', 'subject' => 'Vendor price rises now reprice sourced products automatically (no hold, no alert); approved by Mohammad Faruk', 'meta' => json_encode(['backup_table' => 'stores_price_jump_backup']), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  public function down(): void {
    if (!Schema::hasTable('stores_price_jump_backup') || !Schema::hasColumn('stores', 'price_jump_pct')) return;
    foreach (DB::table('stores_price_jump_backup')->get() as $b) DB::table('stores')->where('id', $b->store_id)->update(['price_jump_pct' => $b->price_jump_pct]);
  }
};
