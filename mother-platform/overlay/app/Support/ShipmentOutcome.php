<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
/**
 * Shipment outcome. Parcel outcome is always CALCULATED from item-level outcomes (never picked by hand):
 *   nothing returned          -> Delivered
 *   some received + some back -> Partial Return:  Return Pending (return_type Partial) -> Returned (goods back, stock added)
 *   everything returned       -> Full Return:     Return Pending -> Returned (goods back, stock added)
 *   exchange                  ->                  Return Pending (return_type Exchange) -> Exchange Received
 * The order-status bar only ever shows Shipment, Partial Delivered, Delivered, Late Delivery, Return Pending, Returned, Exchange Received. The kind of return is kept in return_type / pathao_outcome.
 * Money: for a Partial Return the order total becomes the value of the RECEIVED items only (no delivery charge, no discount); the original figures stay in original_*.
 * Stock: leaves the shelf when the order first reaches Shipment (DB trigger); goes back only when the returned goods are physically received, and only if it was deducted.
 */
class ShipmentOutcome {
  const RECORDABLE = ['Shipment', 'Late Delivery', 'Partial Delivered', 'Return Pending'];
  const PARTIAL = [];
  const FULL = ['Return Pending', 'Returned'];
  const RETURN_STATUSES = ['Return Pending', 'Returned', 'Exchange Received'];
  /** Statuses that only this module may set (a person must not pick a parcel type by hand). */
  /** Statuses the courier decides once a parcel exists. Staff cannot set them by hand; the courier result sets them and the warehouse only confirms receipt. */
  const COURIER_SET = ['Shipment', 'Late Delivery', 'Partial Delivered', 'Delivered', 'Return Pending', 'Returned', 'Exchange Received'];
  public static function courierLocked(object $o): bool { return !empty($o->consignment_id) && in_array($o->status, self::COURIER_SET, true); }
  /** Message when a manual move is not allowed, null when it is. */
  public static function manualBlock(object $o, string $to): ?string {
    if ($o->status === $to || !self::courierLocked($o) || !in_array($to, self::COURIER_SET, true)) return null;
    return 'This parcel is with the courier, so its delivery / return status comes from the courier result and cannot be changed by hand. Use the Received button when the goods reach the warehouse.';
  }
  const SYSTEM_ONLY = [];

  /** @param iterable $items rows with qty and ret_qty. @return array{total:int,ret:int,rec:int,type:?string,status:string} */
  public static function classify(iterable $items): array {
    $tot = 0; $ret = 0;
    foreach ($items as $i) { $q = max(0, (int)$i->qty); $r = max(0, min($q, (int)($i->ret_qty ?? 0))); $tot += $q; $ret += $r; }
    $rec = $tot - $ret;
    if ($tot > 0 && $ret === $tot) return ['total'=>$tot, 'ret'=>$ret, 'rec'=>$rec, 'type'=>'Full', 'status'=>'Return Pending'];
    if ($ret > 0) return ['total'=>$tot, 'ret'=>$ret, 'rec'=>$rec, 'type'=>'Partial', 'status'=>'Return Pending'];
    return ['total'=>$tot, 'ret'=>0, 'rec'=>$rec, 'type'=>null, 'status'=>'Delivered'];
  }
  public static function itemLabel($i): string {
    $q = (int)$i->qty; $r = (int)($i->ret_qty ?? 0);
    if (!($i->outcome ?? null)) return 'Pending';
    if ($i->outcome === 'Received') return 'Received';
    return ($r > 0 && $r < $q ? $r.' of '.$q.' ' : '').$i->outcome;
  }
  /** Numbers for the order page / list: item counts are in units (quantity). */
  public static function summary(object $o, iterable $items): array {
    $c = self::classify($items); $recV = 0.0; $retV = 0.0; $has = false; $lines = 0;
    foreach ($items as $i) { $q = (int)$i->qty; $r = max(0, min($q, (int)($i->ret_qty ?? 0))); $recV += ($q - $r) * (float)$i->price; $retV += $r * (float)$i->price; $lines++; if (!empty($i->outcome)) $has = true; }
    return $c + ['recV'=>round($recV, 2), 'retV'=>round($retV, 2), 'lines'=>$lines, 'has'=>$has];
  }
  /** Small line under the status pill in the order list. */
  public static function rowNote(object $o, iterable $items): string {
    $cf = !empty($o->courier_flag) ? '<br><small style="color:#B45309;font-weight:800">⚠ '.e($o->courier_flag).'</small>' : '';
    $flag = $cf.(!empty($o->billing_flag) ? '<br><small style="color:#B91C1C;font-weight:800" title="'.e((string)($o->billing_flag_note ?? '')).'">⚠ '.e($o->billing_flag).' · needs review</small>' : '');
    if (!in_array($o->status, self::RETURN_STATUSES, true) || empty($o->return_type)) return $flag;
    $s = self::summary($o, $items);
    return '<br><small style="color:#9A3412;font-weight:700">'.($o->return_type === 'Partial' ? 'Partial Return' : 'Full Return').' · '.$s['ret'].' of '.$s['total'].' item'.($s['total'] == 1 ? '' : 's').' '.($o->status === 'Returned' ? 'returned' : 'returning').'</small>'.$flag;
  }
  /** Partial: total = received items only. Anything else: put the original figures back if they had been replaced. */
  private static function moneyFor(object $o, array $c, float $recV): array {
    $has = $o->original_total !== null;
    if ($c['type'] === 'Partial') return ['total'=>round($recV, 2), 'shipping'=>0, 'discount'=>0] + ($has ? [] : ['original_total'=>$o->total, 'original_shipping'=>$o->shipping, 'original_discount'=>$o->discount ?? 0]);
    if ($has) return ['total'=>$o->original_total, 'shipping'=>$o->original_shipping ?? 0, 'discount'=>$o->original_discount ?? 0, 'original_total'=>null, 'original_shipping'=>null, 'original_discount'=>null];
    return [];
  }
  /** Save the outcome of every item and move the order to the calculated status. @return array [ok, message, status] */
  public static function save(int $orderId, array $retQty, ?string $note = null): array {
    $o = DB::table('orders')->find($orderId); if (!$o) return [false, 'Order not found.', null];
    if (!in_array($o->status, self::RECORDABLE, true)) return [false, 'An order in "'.$o->status.'" cannot get a delivery outcome. It must be in Shipment (or an open return).', $o->status];
    $items = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->get();
    if ($items->isEmpty()) return [false, 'This order has no items.', $o->status];
    foreach ($items as $i) { $r = (int)($retQty[$i->id] ?? 0); if ($r < 0 || $r > (int)$i->qty) return [false, 'Return quantity for "'.$i->name.'" must be between 0 and '.(int)$i->qty.'.', $o->status]; $i->ret_qty = $r; }
    $c = self::classify($items); $now = now(); $lines = []; $s = self::summary($o, $items);
    $money = self::moneyFor($o, $c, $s['recV']);
    DB::transaction(function () use ($items, $c, $o, $orderId, $now, &$lines, $money) {
      foreach ($items as $i) {
        $out = $i->ret_qty > 0 ? 'Return Pending' : 'Received';
        DB::table('order_items')->where('id', $i->id)->update(['outcome'=>$out, 'ret_qty'=>$i->ret_qty, 'back_qty'=>null, 'outcome_at'=>$now]);
        $lines[] = $i->name.' ×'.$i->qty.' → '.($i->ret_qty > 0 ? ($i->ret_qty < $i->qty ? 'Received '.($i->qty - $i->ret_qty).', Return Pending '.$i->ret_qty : 'Return Pending') : 'Received');
      }
      DB::table('orders')->where('id', $orderId)->update(['status'=>$c['status'], 'return_type'=>$c['type'], 'billing_flag'=>null, 'billing_flag_note'=>null, 'updated_at'=>$now] + $money);
      if ($o->status !== $c['status']) DB::table('order_status_logs')->insert(['order_id'=>$orderId, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'System', 'from_status'=>$o->status, 'to_status'=>$c['status'], 'note'=>'Delivery outcome: '.($c['type'] ? $c['type'].' Return, '.$c['ret'].' of '.$c['total'].' items returning' : 'all items received').($note ? ' · '.$note : ''), 'created_at'=>$now, 'updated_at'=>$now]);
    });
    $fin = ($c['type'] === 'Partial') ? ' Final delivered amount ৳'.number_format($s['recV'], 2).' (original ৳'.number_format((float)($o->original_total ?? $o->total), 2).').' : '';
    OrderActivity::log($orderId, 'delivery_outcome', $o->status, $c['status'], 'Delivery Outcome Updated — '.($c['type'] ? $c['type'].' Return ('.$c['ret'].' of '.$c['total'].' items returning)' : 'all items received').'.'.$fin.($note ? ' · '.$note : ''), ['items'=>$lines, 'return_type'=>$c['type'], 'classified'=>$c['status']]);
    return [true, ($c['type'] ? ($c['type'] === 'Partial' ? 'Partial Return: ' : 'Full Return: ').$c['ret'].' of '.$c['total'].' items returning. Status: '.$c['status'].'.' : 'All items received. Status: Delivered.').$fin, $c['status']];
  }
  /** Lines the warehouse has to take back: [item row, expected qty]. A Return Pending order without a recorded outcome means the whole parcel. */
  public static function expected(object $o, $items): array {
    $out = []; $any = $items->contains(fn($i) => (int)($i->ret_qty ?? 0) > 0);
    foreach ($items as $i) { $q = $any ? (int)($i->ret_qty ?? 0) : ($o->status === 'Return Pending' && $o->return_type !== 'Partial' ? (int)$i->qty : 0); if ($q > 0) $out[] = [$i, $q]; }
    return $out;
  }
  /**
   * Warehouse receives the returned goods ("Partial Return Received" / "Return Received").
   * $back = [order_item_id => quantity physically received] (default: everything that was expected). Stock goes up by exactly that, once.
   * Return Pending -> Returned (Exchange: Return Pending -> Exchange Received). A partial return restores only the returning items.
   */
  public static function receive(int $orderId, array $back = []): array {
    $o = DB::table('orders')->find($orderId); if (!$o) return [false, 'Order not found.', null];
    if ($o->status !== 'Return Pending') return [false, 'Only an order waiting for its return can be received.', $o->status];
    $items = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->get(); $exp = self::expected($o, $items);
    if (!$exp) return [false, 'No returning items were found on this order.', $o->status];
    foreach ($exp as [$i, $q]) { $b = array_key_exists($i->id, $back) ? (int)$back[$i->id] : $q; if ($b < 0 || $b > $q) return [false, 'Received quantity for "'.$i->name.'" must be between 0 and '.$q.'.', $o->status]; $back[$i->id] = $b; }
    $ex = $o->return_type === 'Exchange'; $full = !$ex && $o->return_type !== 'Partial'; $to = $ex ? 'Exchange Received' : 'Returned'; $now = now(); $lines = []; $stockDone = false;
    $canStock = $o->stock_deducted_at !== null && $o->stock_returned_at === null;
    DB::transaction(function () use ($o, $orderId, $exp, $back, $to, $now, $full, $ex, $canStock, &$lines, &$stockDone) {
      foreach ($exp as [$i, $q]) {
        $b = (int)$back[$i->id];
        DB::table('order_items')->where('id', $i->id)->update(['outcome'=>'Returned', 'ret_qty'=>$q, 'back_qty'=>$b, 'outcome_at'=>$now]);
        if ($canStock && $b > 0 && $i->product_id) { Procure::stock((int)$i->product_id, $b, 'order-return', (string)$o->invoice); $stockDone = true; }
        $lines[] = $i->name.' → Returned'.($b !== $q ? ' (received back '.$b.' of '.$q.')' : '').($canStock && $b > 0 && $i->product_id ? ', stock +'.$b : '');
      }
      DB::table('orders')->where('id', $orderId)->update(['status'=>$to, 'return_received_at'=>$now, 'return_type'=>$o->return_type ?: 'Full', 'updated_at'=>$now] + ($stockDone ? ['stock_returned_at'=>$now] : []));
      DB::table('order_status_logs')->insert(['order_id'=>$orderId, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'System', 'from_status'=>$o->status, 'to_status'=>$to, 'note'=>'Returned goods received at the warehouse'.($stockDone ? ' · stock added' : ''), 'created_at'=>$now, 'updated_at'=>$now]);
    });
    $why = $stockDone ? ' Stock added.' : ($o->stock_deducted_at === null ? ' Stock unchanged (this order shipped before automatic stock deduction started).' : ($o->stock_returned_at !== null ? ' Stock was already returned.' : ' Stock unchanged (nothing received back).'));
    OrderActivity::log($orderId, 'return_received', $o->status, $to, ($ex ? 'Exchange Received' : ($full ? 'Return Received' : 'Partial Return Received (order is Returned)')).'.'.$why, ['items'=>$lines]);
    return [true, ($ex ? 'Exchange received at the warehouse.' : ($full ? 'Return received, order is Returned.' : 'Partial return received, order is Returned.')).$why, $to];
  }
  /** Retired: a partial return now ends at Returned when the goods are received. */
  public static function complete(int $orderId): array { return [false, 'A partial return is complete as soon as the goods are received.', null]; }
}
