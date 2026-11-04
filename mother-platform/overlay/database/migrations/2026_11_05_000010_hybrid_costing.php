<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\CostingSql;

/**
 * Hybrid Costing Model: weighted average (reporting) + FIFO batch cost (real COGS) + 15% protected profit on the actual batch cost.
 *  - stock_batches: every purchase / stock-in is its own batch; order_item_batches: exactly which batches a shipment consumed (Actual COGS).
 *  - The existing shipment trigger now consumes the oldest batches first (row locks, once per order item) and keeps the item's COGS.
 *  - Backfill: the stock on hand is split into batches from the latest received purchase lots (the stock is always the newest goods under FIFO);
 *    stock the purchase history cannot explain becomes one opening batch at the average cost. Idempotent: only products without batches are touched.
 * Orders that shipped before this migration keep their old figures (no allocation, COGS stays empty and reports fall back to the product cost).
 */
return new class extends Migration {
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    foreach (['products', 'orders', 'order_items', 'stock_logs'] as $t) if (!Schema::hasTable($t)) return;
    DB::unprepared(CostingSql::schema());
    DB::unprepared(CostingSql::functions());

    try {
    $lots = Schema::hasTable('purchase_items') && Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'stock_applied'); $nb = 0; $np = 0; $open = 0;
    DB::table('products as p')->where('p.stock', '>', 0)->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('stock_batches as b')->whereColumn('b.product_id', 'p.id'))
      ->select('p.id', 'p.stock', 'p.cost', 'p.avg_cost')->orderBy('p.id')->chunkById(300, function ($rows) use ($lots, &$nb, &$np, &$open) {
        foreach ($rows as $p) {
          $need = (int)$p->stock; $take = [];
          if ($lots) {
            try {
              foreach (DB::table('purchase_items as i')->join('purchases as u', 'u.id', '=', 'i.purchase_id')->where('i.product_id', $p->id)->where('u.stock_applied', true)->where('i.cost', '>', 0)
                ->orderByDesc('u.purchase_date')->orderByDesc('u.id')->orderByDesc('i.id')->get(['u.id as pid', 'u.supplier_id', 'u.purchase_date', 'u.invoice', 'i.qty', 'i.damage_qty', 'i.cost']) as $l) {
                $q = max(0, (int)$l->qty - (int)$l->damage_qty); if ($q <= 0) continue; $t = min($q, $need);
                $take[] = ['product_id' => $p->id, 'purchase_id' => $l->pid, 'supplier_id' => $l->supplier_id, 'purchase_date' => $l->purchase_date ?: now()->toDateString(), 'qty_received' => $q, 'qty_remaining' => $t, 'unit_cost' => round((float)$l->cost, 4), 'total_cost' => round($q * (float)$l->cost, 2), 'reference' => $l->invoice, 'source' => 'purchase', 'status' => 'open', 'created_by' => 'migration', 'created_at' => now(), 'updated_at' => now()];
                $need -= $t; if ($need <= 0) break;
              }
            } catch (\Throwable $e) { report($e); $take = []; $need = (int)$p->stock; }
          }
          $take = array_reverse($take); // oldest first, so batch ids follow the purchase order
          if ($need > 0) { $c = (float)$p->avg_cost > 0 ? (float)$p->avg_cost : (float)$p->cost; array_unshift($take, ['product_id' => $p->id, 'purchase_id' => null, 'supplier_id' => null, 'purchase_date' => '2000-01-01', 'qty_received' => $need, 'qty_remaining' => $need, 'unit_cost' => round($c, 4), 'total_cost' => round($need * $c, 2), 'reference' => 'Opening stock (before batch costing)', 'source' => 'opening', 'status' => 'open', 'created_by' => 'migration', 'created_at' => now(), 'updated_at' => now()]); $open++; }
          foreach ($take as $b) { DB::table('stock_batches')->insert($b); $nb++; }
          $np++;
        }
      }, 'p.id', 'id');
    } catch (\Throwable $e) { report($e); try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Hybrid costing backfill problem', 'subject' => mb_substr($e->getMessage(), 0, 240), 'meta' => null, 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {} }
    DB::statement('select costing_refresh(id) from products');
    $drift = (int)(DB::selectOne('select costing_sync_all() as n')->n ?? 0);
    DB::table('settings')->updateOrInsert(['key' => 'costing_ship_profit'], ['value' => DB::table('settings')->where('key', 'costing_ship_profit')->value('value') ?: 'block']);
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Hybrid costing activated', 'subject' => "$np products split into $nb stock batches ($open with an opening batch); $drift adjusted", 'meta' => json_encode(['previous' => 'Average purchase price x 1.15', 'new' => 'Actual FIFO batch cost x 1.15; average cost for reporting'], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
