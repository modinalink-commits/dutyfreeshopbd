<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Category based inventory rules.
 * Publish status and stock status are SEPARATE (Product Publish, Stock-Out & Stock-In Rules):
 *  - Stock reaching zero NEVER unpublishes a product (shipment, sale, return pending, depletion, manual edit). Only an authorised person unpublishes by hand.
 *  - Stock-in (0 -> positive: purchase, return, exchange, authorised adjustment) publishes the product automatically (database trigger products_stock_publish),
 *    unless it was unpublished by hand (products.manual_unpublish_lock = true). Publishing by hand clears the lock.
 *  - STOCK_CONTROLLED (Cigarette and Vape): stays published at zero stock, shows "Out of Stock", the Order buttons are disabled and an order is refused.
 *  - SOURCING (every other category): stays published, can be ordered at zero stock (purchase after order); the shelf stock is never shown to customers.
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
  /** Ids of every stock-controlled category and its sub categories (used to keep sourcing products visible at zero stock). */
  public static function controlledIds(): array {
    $m = self::modes(); $ids = [];
    foreach ($m as $id => $mode) if ($mode === 'STOCK_CONTROLLED') $ids[] = (int)$id;
    if ($ids) { try { $ids = array_values(array_unique(array_merge($ids, array_map('intval', DB::table('categories')->whereIn('parent_id', $ids)->pluck('id')->all())))); } catch (\Throwable $e) {} }
    return $ids;
  }
  private static ?bool $lockReady = null;
  public static function lockReady(): bool {
    if (self::$lockReady === null) { try { self::$lockReady = \Illuminate\Support\Facades\Schema::hasColumn('products', 'manual_unpublish_lock'); } catch (\Throwable $e) { self::$lockReady = false; } }
    return self::$lockReady;
  }
  /** Columns to add to a products update when an admin publishes (lock off) or unpublishes (lock on) a product by hand. */
  public static function lockCol(bool $published): array { return self::lockReady() ? ['manual_unpublish_lock' => !$published] : []; }
  /** Admin label for the stock state: In Stock / Out of Stock / Sourcing Required (kept apart from the publish status). */
  public static function stockLabel($p): string {
    if (!isset($p->stock) || $p->stock === null || (int)$p->stock > 0) return 'In Stock';
    return self::mode($p->category_id ?? null) === 'STOCK_CONTROLLED' ? 'Out of Stock' : 'Sourcing Required';
  }
  /** Storefront: can the customer still order this product? (sourcing categories always can; stock-controlled ones only with stock) */
  public static function sellable($p): bool {
    return !isset($p->stock) || $p->stock === null || (int)$p->stock > 0; // zero stock = unavailable in every category (owner decision 7 Nov 2026)
  }
  /** Storefront listings (owner decision 7 Nov 2026): every product with zero stock is hidden from lists, search, brands, deals and feeds. Untracked stock (null) stays. Direct product URLs are NOT affected. $a = table alias prefix. */
  public static function listable($qb, string $a = 'products.') {
    return $qb->where(function ($w) use ($a) { $w->whereNull($a.'stock')->orWhere($a.'stock', '>', 0); });
  }
  /** Shipment validation (Hybrid Costing): null when the order may ship, otherwise the red alert text.
   *  1. every product must have the stock (No Stock = No Shipment, every category);
   *  2. revenue after all discounts must stay at or above the ACTUAL FIFO batch cost x (1 + protected profit %). */
  public static function shipBlock(int $orderId): ?string {
    $o = DB::table('orders')->where('id', $orderId)->first(['id', 'invoice', 'stock_deducted_at', 'duplicate_of_order_id']);
    if (!$o || $o->stock_deducted_at !== null || $o->duplicate_of_order_id !== null) return null;
    $rows = DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $orderId)->whereNotNull('p.stock')
      ->groupBy('p.id', 'p.name', 'p.stock')->havingRaw('sum(i.qty) > p.stock')->selectRaw('p.name, p.stock, sum(i.qty) need')->get();
    if (!$rows->isEmpty()) {
      $l = []; foreach ($rows->take(4) as $r) $l[] = $r->name.' — Required Qty: '.(int)$r->need.', Available Qty: '.max(0, (int)$r->stock);
      return '🔴 Stock Required — This product cannot be moved to Shipment. '.implode('; ', $l).($rows->count() > 4 ? ' and '.($rows->count() - 4).' more' : '').'. Please Stock In the product first.';
    }
    return self::profitBlock($o);
  }
  /** Protected profit at shipment. Mode (Admin setting on Inventory Pricing): block (default) / warn (allow and log) / off. */
  /** Set by an authorised Send Anyway (Pre-Shipment Validation) for ONE booking only. */
  public static bool $bypassMargin = false;
  private static function profitBlock(object $o): ?string {
    try {
      if (self::$bypassMargin) return null;
      if (!Costing::ready()) return null; $mode = Costing::shipMode(); if ($mode === 'off') return null;
      $bad = Costing::shipViolations((int)$o->id); if (!$bad) return null;
      $d = array_map(fn($x) => $x['name'].' ×'.$x['qty'].' – Actual FIFO batch cost '.ProfitFloor::tk($x['cost']).', Minimum allowed revenue '.ProfitFloor::tk($x['min']).', Revenue after discount '.ProfitFloor::tk($x['rev']).', Shortfall '.ProfitFloor::tk($x['short']), array_slice($bad, 0, 4));
      if (Cache::add('costing.shipwarn.'.$o->id.'.'.$mode.'.'.md5(json_encode($bad)), 1, 600)) ProfitFloor::audit($mode === 'warn' ? 'Minimum profit warning at shipment' : 'Minimum profit blocked shipment', 'Order '.$o->invoice, null, ['mode'=>$mode, 'lines'=>$bad, 'by'=>auth()->user()->name ?? 'system']);
      if ($mode === 'warn') return null;
      return ProfitFloor::admin()
        ? '⚠ Minimum Profit Violation – Shipment blocked. This order is below the company\'s protected '.ProfitFloor::pct($o->created_at ?? null).'% profit margin on the actual batch cost. '.implode('; ', $d).(count($bad) > 4 ? ' and '.(count($bad) - 4).' more' : '').'. Raise the price or reduce the discount first (or set "Shipment profit protection" to Warn only on Inventory Pricing).'
        : '⚠ এই অর্ডার Shipment-এ দেওয়া যাবে না — এতে কোম্পানির ন্যূনতম '.ProfitFloor::pctBn($o->created_at ?? null).'% প্রফিট সুরক্ষা ভঙ্গ হবে। অনুগ্রহ করে Admin-এর সাথে যোগাযোগ করুন।';
    } catch (\Throwable $e) { report($e); return null; }
  }
}
