<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Costing Model (application side). The heavy lifting is in the database functions of CostingSql.
 *   Weighted Average = reporting   |   FIFO batch cost = real COGS   |   Actual batch cost x (1 + Minimum Protected Profit %) = minimum sale protection.
 * Every call here is safe to make before the migration ran: it simply does nothing.
 */
class Costing {

  public static function ready(): bool {
    static $r = null; if ($r !== null) return $r;
    try { $r = Cache::remember('costing.ready', 60, fn() => Schema::hasTable('stock_batches') && Schema::hasTable('order_item_batches') && Schema::hasColumn('products', 'fifo_cost')); } catch (\Throwable $e) { $r = false; }
    return $r;
  }
  /** Run a costing database call inside its own savepoint, so a costing problem can never undo a purchase or a stock change. */
  private static function run(callable $fn, $default = null) {
    if (!self::ready()) return $default;
    try { return DB::transaction($fn); } catch (\Throwable $e) { report($e); return $default; }
  }

  // ---------- movements ----------
  /** Stock in: a new batch. Returns the batch id. */
  public static function addBatch(int $pid, int $qty, float $cost, ?int $purchaseId, ?int $supplierId, $date, string $ref, string $source = 'purchase'): ?int {
    return self::run(function () use ($pid, $qty, $cost, $purchaseId, $supplierId, $date, $ref, $source) {
      $r = DB::selectOne('select costing_add_batch(?,?,?,?,?,?,?,?,?) as id', [$pid, $qty, $cost, $purchaseId, $supplierId, $date ? substr((string)$date, 0, 10) : null, $ref, $source, auth()->user()->name ?? 'system']);
      return $r && $r->id !== null ? (int)$r->id : null;
    });
  }
  /** A purchase is reversed / edited: its batches leave the queue. */
  public static function reversePurchase(int $purchaseId): void { self::run(fn() => DB::select('select costing_reverse_purchase(?)', [$purchaseId])); }
  /** Confirmed return: goods go back on the batches they were shipped from. Returns the cost restored. */
  public static function restock(int $itemId, int $qty, int $orderId, string $ref): float {
    return (float)self::run(fn() => DB::selectOne('select costing_restock(?,?,?,?) as c', [$itemId, $qty, $orderId, $ref])->c, 0);
  }
  /** Damage, purchase return or manual decrease: oldest batches go first. */
  public static function out(int $pid, int $qty): void { if ($qty > 0) self::run(fn() => DB::select('select costing_out(?,?)', [$pid, $qty])); }
  /** Make the open batches add up to the stock on hand (manual edit, import). Returns the difference it fixed. */
  public static function sync(int $pid): int { $d = (int)self::run(fn() => DB::selectOne('select costing_sync(?) as d', [$pid])->d, 0); try { BundlePricing::sync([$pid]); } catch (\Throwable $e) { report($e); } return $d; } // a stock / cost change also refreshes the product's bundle prices (the FIFO cost may have moved)
  public static function syncAll(): int { return (int)self::run(fn() => DB::selectOne('select costing_sync_all() as n')->n, 0); }
  /** A generic stock movement (damage, manual, reversal of a return ...) seen from the batches. */
  public static function moved(int $pid, int $delta, string $source, string $ref): void {
    if (!self::ready() || $delta === 0) return;
    if ($delta > 0) { $c = (float)(DB::table('products')->where('id', $pid)->value('cost') ?? 0); self::addBatch($pid, $delta, $c, null, null, null, $ref, 'adjustment'); }
    else self::out($pid, -$delta);
    self::sync($pid);
  }

  // ---------- reads ----------
  /** SQL: the cost the profit rule uses (current FIFO batch, else average, else last purchase price). */
  public static function fifoSql(string $a = 'p'): string { return self::ready() ? "coalesce(nullif($a.fifo_cost,0), nullif($a.avg_cost,0), nullif($a.cost,0), 0)" : "coalesce(nullif($a.avg_cost,0), nullif($a.cost,0), 0)"; }
  /** SQL: the raw FIFO figure (0 when no stock batch exists). */
  public static function fifoRawSql(string $a = 'p'): string { return self::ready() ? "$a.fifo_cost" : "0"; }
  /** Who may see purchase / batch costs: the Admin, and roles given the Inventory Pricing (costs) permission. */
  public static function canSee(): bool { try { return ProfitFloor::admin() || \App\Http\Middleware\ModulePermission::can(auth()->user(), 'costs', 'view'); } catch (\Throwable $e) { return false; } }
  /** SQL: the reporting cost (weighted average of the stock on hand, else last purchase price). */
  public static function avgSql(string $a = 'p'): string { return "coalesce(nullif($a.avg_cost,0), nullif($a.cost,0), 0)"; }
  /** Last purchase price of a product row (the fallback for units no batch covers). */
  private static function fallback(int $pid): float { $p = DB::table('products')->where('id', $pid)->first(['cost', 'avg_cost']); return $p ? ((float)$p->cost > 0 ? (float)$p->cost : (float)$p->avg_cost) : 0.0; }
  /** Expected unit cost of $qty units shipped now: the oldest batches first, units no batch covers at the last purchase price. */
  public static function expectedUnit(int $pid, int $qty): float {
    $qty = max(1, $qty); if (!self::ready()) return self::fallback($pid);
    $need = $qty; $sum = 0.0;
    foreach (DB::table('stock_batches')->where('product_id', $pid)->where('qty_remaining', '>', 0)->where('status', 'open')->orderBy('purchase_date')->orderBy('id')->get(['qty_remaining', 'unit_cost']) as $b) {
      $t = min($need, (int)$b->qty_remaining); $sum += $t * (float)$b->unit_cost; $need -= $t; if ($need <= 0) break;
    }
    if ($need > 0) $sum += $need * self::fallback($pid);
    return $sum / $qty;
  }
  public static function batches(int $pid, bool $all = true) {
    return DB::table('stock_batches as b')->leftJoin('suppliers as s', 's.id', '=', 'b.supplier_id')->leftJoin('purchases as u', 'u.id', '=', 'b.purchase_id')
      ->where('b.product_id', $pid)->when(!$all, fn($q) => $q->where('b.qty_remaining', '>', 0))->orderBy('b.purchase_date')->orderBy('b.id')
      ->get(['b.*', 's.name as supplier_name', 'u.invoice as purchase_invoice']);
  }

  // ---------- shipment: protected profit on the ACTUAL batch cost ----------
  /** 'block' (default), 'warn' (allow, but log) or 'off'. */
  public static function shipMode(): string { $m = Procure::set('costing_ship_profit', 'block'); return in_array($m, ['block', 'warn', 'off'], true) ? $m : 'block'; }
  /** Products on this order whose revenue (after the order discount) is below actual FIFO cost x (1 + the protected profit % active when the order was created). [name, qty, cost, min, revenue, short] rows. */
  public static function shipViolations(int $orderId): array {
    $o = DB::table('orders')->where('id', $orderId)->first(['id', 'discount', 'created_at']); if (!$o) return []; $at = $o->created_at ? (string)$o->created_at : null; // the rule that was active when the order was created
    $items = DB::table('order_items')->where('order_id', $orderId)->get(['product_id', 'name', 'qty', 'price']);
    $sub = 0.0; foreach ($items as $i) $sub += (float)$i->qty * (float)$i->price;
    $disc = min((float)($o->discount ?? 0), $sub); $by = [];
    foreach ($items as $i) { if (!$i->product_id) continue; $k = (int)$i->product_id; $v = (float)$i->qty * (float)$i->price; $by[$k] = ($by[$k] ?? ['name' => $i->name, 'qty' => 0, 'val' => 0.0]); $by[$k]['qty'] += (int)$i->qty; $by[$k]['val'] += $v; }
    $bad = [];
    foreach ($by as $pid => $x) {
      if ($x['qty'] <= 0) continue;
      $unit = self::expectedUnit($pid, $x['qty']); if ($unit <= 0) continue; // no cost on file: cannot be checked
      $cost = round($unit * $x['qty'], 2); $min = ceil(round($cost * ProfitFloor::rate($at) * 100, 6)) / 100;
      // Approved Clearance: the line may ship at the clearance price that was in force when the order was placed (never below it).
      $cp = Clearance::priceAt((int)$pid, $at); if ($cp !== null) $min = min($min, round($cp * $x['qty'], 2));
      $bm = AutoPrice::minTotal((int)$pid, (int)$x['qty']); if ($bm !== null) $min = min($min, $bm); // Best Sellers pack offer (approved exception)
      $rev = round($x['val'] - ($sub > 0 ? $disc * $x['val'] / $sub : 0), 2);
      if ($rev + 0.004 < $min) $bad[] = ['name' => $x['name'], 'qty' => $x['qty'], 'cost' => $cost, 'min' => $min, 'rev' => $rev, 'short' => round($min - $rev, 2)];
    }
    return $bad;
  }
}
