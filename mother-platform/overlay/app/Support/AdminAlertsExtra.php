<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** More admin alert detectors (staff mistakes, pricing, purchasing, missing logistics, system faults). Read-only: they only RAISE/CLEAR alerts in the Issues Center and never change business data. */
class AdminAlertsExtra
{
  public static function tick(): void {
    if (!Cache::add('adminalerts.extra.lock', 1, 280)) return;
    foreach (['staffCancels', 'staffPrices', 'priceSwing', 'jobs', 'disk', 'noConsignment', 'badOrders', 'stockMissing'] as $d) { try { self::$d(); } catch (\Throwable $e) {} }
    if (Cache::add('adminalerts.extra.slow', 1, 1750)) foreach (['belowFloor', 'purchaseJump'] as $d) { try { self::$d(); } catch (\Throwable $e) {} }
  }

  private static function set(bool $bad, string $fp, string $sev, string $svc, string $title, string $desc, string $cause, string $action, string $why): void {
    if ($bad) DeployHealth::raise($fp, $sev, $svc, $title, $desc, $cause, $action); else DeployHealth::resolve($fp, 'System (' . $why . ')');
  }

  /** One staff member cancelled 5+ orders in the last hour. */
  private static function staffCancels(): void {
    if (!Schema::hasTable('order_activity_logs')) return;
    $r = DB::table('order_activity_logs')->selectRaw('staff_name, count(*) as n')->where('action_type', 'status_changed')->where('new_value', 'Cancelled')->whereNotNull('staff_id')->where('created_at', '>=', now()->subHour())->groupBy('staff_name')->havingRaw('count(*) >= 5')->orderByDesc('n')->first();
    self::set((bool)$r, 'staff_cancels', 'high', 'Staff', 'Unusual number of order cancellations', $r ? $r->n . ' orders were cancelled by ' . $r->staff_name . ' in the last 60 minutes.' : '', 'A staff mistake, a bulk action by accident or a misuse of the cancel button.', 'Open Staff Activity, check the cancelled orders and talk to the staff member.', 'cancellations are normal');
  }

  /** Staff changed 10+ product prices by hand in the last hour. */
  private static function staffPrices(): void {
    if (!Schema::hasTable('product_price_history') || !Schema::hasColumn('product_price_history', 'created_at')) return;
    $n = DB::table('product_price_history')->where('created_at', '>=', now()->subHour())->where('changed_by', 'not like', 'System%')->count();
    self::set($n >= 10, 'staff_price_edits', 'high', 'Pricing', 'Many manual price changes', $n . ' product prices were changed by staff in the last 60 minutes.', 'A bulk edit or an accidental price change.', 'Open the price history and check the changes.', 'price edits are normal');
  }

  /** Rate up/down: 3+ price changes of 30% or more in 24 hours. */
  private static function priceSwing(): void {
    if (!Schema::hasTable('product_price_history') || !Schema::hasColumn('product_price_history', 'created_at')) return;
    $n = DB::table('product_price_history')->where('created_at', '>=', now()->subDay())->where('old_rate', '>', 0)->whereRaw('abs(new_rate - old_rate) / old_rate >= 0.3')->count();
    self::set($n >= 3, 'price_swing', 'high', 'Pricing', 'Large price swings', $n . ' products changed price by 30% or more in the last 24 hours.', 'A wrong purchase rate, a typo in a price or a supplier rate change.', 'Open the price history and check the big changes.', 'no large swings');
  }

  /** Published products priced below the protected margin. */
  private static function belowFloor(): void {
    if (!Schema::hasTable('product_price_history')) return;
    $rate = ProfitFloor::rate(); $c = Costing::fifoSql('p');
    $n = DB::table('products as p')->where('p.published', true)->where('p.price', '>', 0)->whereRaw("$c > 0")->whereRaw("p.price + 0.004 < $c * ?", [$rate])->count();
    self::set($n >= 1, 'below_margin', 'high', 'Pricing', 'Products priced below the protected margin', $n . ' published products are priced below the protected margin.', 'A purchase rate went up or a price was set too low.', 'Open Pricing / Profit Floor and raise these prices or use the approved override.', 'all prices are safe');
  }

  /** Purchasing: a purchase rate 20%+ higher than the previous purchase of the same product (last 24 h). */
  private static function purchaseJump(): void {
    if (!Schema::hasTable('purchase_items')) return;
    $rows = DB::table('purchase_items')->where('created_at', '>=', now()->subDay())->whereNotNull('product_id')->where('cost', '>', 0)->orderByDesc('id')->limit(300)->get(['id', 'product_id', 'cost']); $bad = 0;
    foreach ($rows as $x) { $prev = DB::table('purchase_items')->where('product_id', $x->product_id)->where('id', '<', $x->id)->where('cost', '>', 0)->orderByDesc('id')->value('cost'); if ($prev && (float)$x->cost >= (float)$prev * 1.2) $bad++; }
    self::set($bad >= 1, 'purchase_jump', 'high', 'Purchasing', 'Purchase rate jumped', $bad . ' purchased items cost 20% or more above the previous purchase in the last 24 hours.', 'A supplier raised the rate or the purchase was entered wrongly.', 'Open Purchases, check these rates and the selling prices.', 'no purchase rate jumps');
  }

  /** Failed background jobs. */
  private static function jobs(): void {
    if (!Schema::hasTable('failed_jobs')) return;
    $n = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(30))->count();
    self::set($n >= 5, 'jobs_failing', 'high', 'System', 'Background jobs are failing', $n . ' background jobs failed in the last 30 minutes.', 'A service (SMS, courier, AI, mail) or a code fault.', 'Open the failed jobs list and the latest error log.', 'jobs are running');
  }

  /** Server disk almost full. */
  private static function disk(): void {
    $f = @disk_free_space(storage_path()); $t = @disk_total_space(storage_path()); if (!$f || !$t) return; $pct = (int)round($f / $t * 100);
    self::set($pct < 10, 'disk_low', 'high', 'Server', 'Server disk is almost full', 'Only ' . $pct . '% of the disk is free.', 'Logs, uploads or old releases have filled the disk.', 'Clean old releases, logs and backups before it fills up.', 'disk space is fine');
  }

  /** Logistics: parcels in Shipment with no courier consignment for 30+ minutes. */
  private static function noConsignment(): void {
    $n = DB::table('orders')->where('status', 'Shipment')->whereNull('consignment_id')->where('updated_at', '<', now()->subMinutes(30))->count();
    self::set($n >= 1, 'no_consignment', 'high', 'Courier', 'Shipment without a courier consignment', $n . ' orders are in Shipment but have no courier consignment.', 'The courier booking failed or was skipped.', 'Open these orders and book the courier again.', 'all shipments have a consignment');
  }

  /** Logistics: approved/packaging orders with no phone or no address. */
  private static function badOrders(): void {
    $n = DB::table('orders')->whereIn('status', ['Approved', 'Packaging'])->where(fn($q) => $q->whereNull('address')->orWhere('address', '')->orWhereNull('phone')->orWhere('phone', ''))->count();
    self::set($n >= 1, 'orders_missing_address', 'warning', 'Orders', 'Orders missing address or phone', $n . ' approved orders have no address or phone number.', 'The order was approved before the customer details were complete.', 'Open these orders and complete the address or phone before shipping.', 'no incomplete orders');
  }

  /** Logistics: open orders containing a product that is out of stock. */
  private static function stockMissing(): void {
    $n = DB::table('orders as o')->whereIn('o.status', ['Approved', 'Packaging'])->whereExists(fn($q) => $q->select(DB::raw(1))->from('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->whereColumn('i.order_id', 'o.id')->where('p.stock', '<=', 0))->count();
    self::set($n >= 1, 'stock_missing', 'high', 'Inventory', 'Orders waiting for stock', $n . ' approved orders contain a product with no stock.', 'Stock ran out or a purchase has not been received.', 'Open Inventory / Purchases and arrange the missing stock.', 'stock is available for open orders');
  }
}
