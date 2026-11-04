<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Category based inventory rules.
 *  - STOCK_CONTROLLED (Cigarette and Vape): zero stock = unpublished, stock-in = published (database trigger), no sale at zero stock.
 *  - SOURCING (every other category): zero stock still sells (purchase after order); the shelf stock is never shown to customers.
 * Every category: an order cannot go to Shipment unless every product on it has the stock (shipBlock).
 */
class StockGuard {
  public static function modes(): array {
    return Cache::remember('stock.modes', 300, function () {
      try { return DB::table('categories')->pluck('inventory_mode', 'id')->all(); } catch (\Throwable $e) { return []; }
    });
  }
  /** SOURCING or STOCK_CONTROLLED for a category (a sub category inherits its parent). */
  public static function mode($categoryId): string {
    $m = self::modes(); $id = (int)$categoryId; $n = 0;
    if (!$id) return 'SOURCING';
    if (($m[$id] ?? null) === 'STOCK_CONTROLLED') return 'STOCK_CONTROLLED';
    static $par = null; if ($par === null) { try { $par = Cache::remember('stock.parents', 300, fn() => DB::table('categories')->pluck('parent_id', 'id')->all()); } catch (\Throwable $e) { $par = []; } }
    while (($pid = (int)($par[$id] ?? 0)) && $n++ < 4) { if (($m[$pid] ?? null) === 'STOCK_CONTROLLED') return 'STOCK_CONTROLLED'; $id = $pid; }
    return 'SOURCING';
  }
  /** Storefront: can the customer still order this product? (sourcing categories always can; stock-controlled ones only with stock) */
  public static function sellable($p): bool {
    if (!isset($p->stock) || $p->stock === null || (int)$p->stock > 0) return true;
    if (!isset($p->category_id)) return false; // category unknown: keep the old behaviour
    return self::mode($p->category_id) !== 'STOCK_CONTROLLED';
  }
  /** Shipment validation: null when every product has the stock, otherwise the red alert text. */
  public static function shipBlock(int $orderId): ?string {
    $o = DB::table('orders')->where('id', $orderId)->first(['id', 'invoice', 'stock_deducted_at', 'duplicate_of_order_id']);
    if (!$o || $o->stock_deducted_at !== null || $o->duplicate_of_order_id !== null) return null;
    $rows = DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $orderId)->whereNotNull('p.stock')
      ->groupBy('p.id', 'p.name', 'p.stock')->havingRaw('sum(i.qty) > p.stock')->selectRaw('p.name, p.stock, sum(i.qty) need')->get();
    if ($rows->isEmpty()) return null;
    $l = []; foreach ($rows->take(4) as $r) $l[] = $r->name.' (required '.(int)$r->need.', available '.max(0, (int)$r->stock).')';
    return 'RED ALERT: Cannot Ship, product stock is not available. '.implode('; ', $l).($rows->count() > 4 ? ' and '.($rows->count() - 4).' more' : '').'. Add stock (purchase / stock-in) before shipment.';
  }
}
