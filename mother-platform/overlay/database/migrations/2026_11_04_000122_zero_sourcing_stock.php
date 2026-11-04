<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** One-time (owner's instruction): every category except the stock-controlled Cigarette and Vape starts from zero stock (sourcing model: order first, purchase, stock-in, shipment).
 *  Old quantities are kept in stock_zero_backup and each change is written to stock_logs (MANUAL_ADJUSTMENT) so Stock History shows it. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('categories', 'inventory_mode')) return;
    if (!Schema::hasTable('stock_zero_backup')) Schema::create('stock_zero_backup', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('name', 255)->nullable(); $t->integer('old_stock'); $t->timestamps(); });
    $ctl = DB::table('categories')->where('inventory_mode', 'STOCK_CONTROLLED')->pluck('id')->all();
    if ($ctl) $ctl = array_values(array_unique(array_merge($ctl, DB::table('categories')->whereIn('parent_id', $ctl)->pluck('id')->all())));
    if (!$ctl) return; // no stock-controlled category found: do nothing rather than zero everything
    $hasLog = Schema::hasTable('stock_logs'); $n = 0;
    DB::table('products')->where('stock', '!=', 0)->where(fn($q) => $q->whereNull('category_id')->orWhereNotIn('category_id', $ctl))->orderBy('id')->chunkById(500, function ($rows) use (&$n, $hasLog) {
      foreach ($rows as $p) {
        DB::table('stock_zero_backup')->insert(['product_id' => $p->id, 'name' => mb_substr((string)$p->name, 0, 255), 'old_stock' => (int)$p->stock, 'created_at' => now(), 'updated_at' => now()]);
        if ($hasLog) DB::table('stock_logs')->insert(['product_id' => $p->id, 'source' => 'stock-reset', 'reference' => 'Sourcing categories start from zero', 'before_qty' => (int)$p->stock, 'change_qty' => -(int)$p->stock, 'after_qty' => 0, 'user_id' => null, 'user_name' => 'System', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->where('id', $p->id)->update(['stock' => 0, 'updated_at' => now()]); $n++;
      }
    });
    if ($n > 0) { try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Stock reset to zero (sourcing categories)', 'subject' => "$n products (old quantities in stock_zero_backup)", 'meta' => json_encode(['count' => $n]), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }
    foreach (['store.home8'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
  }
  public function down(): void {}
};
