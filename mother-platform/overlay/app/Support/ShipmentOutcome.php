<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
/**
 * Shipment outcome. Parcel outcome is always CALCULATED from item-level outcomes (never picked by hand):
 *   nothing returned          -> Delivered
 *   some received + some back -> Partial Return (status "Partial Return Pending", then "Partial Returned" when the warehouse receives it)
 *   everything returned       -> Full Return   (status "Return Pending", then "Returned")
 * Phase 1 changes statuses, item outcomes and logs only: stock and the final payable amount are NOT touched here.
 */
class ShipmentOutcome {
  const RECORDABLE = ['Shipment', 'Late Delivery', 'Partial Delivered', 'Partial Return Pending', 'Return Pending'];
  const PARTIAL = ['Partial Return Pending', 'Partial Returned'];
  const FULL = ['Return Pending', 'Returned'];
  /** Statuses that only this module may set (a person must not pick a parcel type by hand). */
  const SYSTEM_ONLY = ['Partial Return Pending', 'Partial Returned'];

  /** @param iterable $items rows with qty and ret_qty. @return array{total:int,ret:int,rec:int,type:?string,status:string} */
  public static function classify(iterable $items): array {
    $tot = 0; $ret = 0;
    foreach ($items as $i) { $q = max(0, (int)$i->qty); $r = max(0, min($q, (int)($i->ret_qty ?? 0))); $tot += $q; $ret += $r; }
    $rec = $tot - $ret;
    if ($tot > 0 && $ret === $tot) return ['total'=>$tot, 'ret'=>$ret, 'rec'=>$rec, 'type'=>'Full', 'status'=>'Return Pending'];
    if ($ret > 0) return ['total'=>$tot, 'ret'=>$ret, 'rec'=>$rec, 'type'=>'Partial', 'status'=>'Partial Return Pending'];
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
    if (!in_array($o->status, ['Partial Return Pending', 'Partial Returned', 'Return Pending', 'Returned'], true) || empty($o->return_type)) return '';
    $s = self::summary($o, $items);
    return '<br><small style="color:#9A3412;font-weight:700">'.($o->return_type === 'Partial' ? 'Partial Return' : 'Full Return').' · '.$s['ret'].' of '.$s['total'].' item'.($s['total'] == 1 ? '' : 's').' '.(in_array($o->status, ['Partial Returned', 'Returned'], true) ? 'returned' : 'returning').'</small>';
  }
  /** Save the outcome of every item and move the order to the calculated status. @return array [ok, message, status] */
  public static function save(int $orderId, array $retQty, ?string $note = null): array {
    $o = DB::table('orders')->find($orderId); if (!$o) return [false, 'Order not found.', null];
    if (!in_array($o->status, self::RECORDABLE, true)) return [false, 'An order in "'.$o->status.'" cannot get a delivery outcome. It must be in Shipment (or an open return).', $o->status];
    $items = DB::table('order_items')->where('order_id', $orderId)->orderBy('id')->get();
    if ($items->isEmpty()) return [false, 'This order has no items.', $o->status];
    foreach ($items as $i) { $r = (int)($retQty[$i->id] ?? 0); if ($r < 0 || $r > (int)$i->qty) return [false, 'Return quantity for "'.$i->name.'" must be between 0 and '.(int)$i->qty.'.', $o->status]; $i->ret_qty = $r; }
    $c = self::classify($items); $now = now(); $lines = []; $by = Auth::user()->name ?? 'System';
    DB::transaction(function () use ($items, $c, $o, $orderId, $now, &$lines) {
      foreach ($items as $i) {
        $out = $i->ret_qty > 0 ? 'Return Pending' : 'Received';
        DB::table('order_items')->where('id', $i->id)->update(['outcome'=>$out, 'ret_qty'=>$i->ret_qty, 'outcome_at'=>$now]);
        $lines[] = $i->name.' ×'.$i->qty.' → '.($i->ret_qty > 0 ? ($i->ret_qty < $i->qty ? 'Received '.($i->qty - $i->ret_qty).', Return Pending '.$i->ret_qty : 'Return Pending') : 'Received');
      }
      DB::table('orders')->where('id', $orderId)->update(['status'=>$c['status'], 'return_type'=>$c['type'], 'updated_at'=>$now]);
      if ($o->status !== $c['status']) DB::table('order_status_logs')->insert(['order_id'=>$orderId, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'System', 'from_status'=>$o->status, 'to_status'=>$c['status'], 'note'=>'Delivery outcome: '.($c['type'] ? $c['type'].' Return, '.$c['ret'].' of '.$c['total'].' items returning' : 'all items received').($note ? ' · '.$note : ''), 'created_at'=>$now, 'updated_at'=>$now]);
    });
    OrderActivity::log($orderId, 'delivery_outcome', $o->status, $c['status'], 'Delivery Outcome Updated — '.($c['type'] ? $c['type'].' Return ('.$c['ret'].' of '.$c['total'].' items returning)' : 'all items received').($note ? ' · '.$note : ''), ['items'=>$lines, 'return_type'=>$c['type'], 'classified'=>$c['status']]);
    return [true, $c['type'] ? ($c['type'] === 'Partial' ? 'Partial Return: ' : 'Full Return: ').$c['ret'].' of '.$c['total'].' items returning. Status: '.$c['status'].'.' : 'All items received. Status: Delivered.', $c['status']];
  }
  /** Warehouse receives the returned goods: Partial Return Pending -> Partial Returned, Return Pending -> Returned. */
  public static function receive(int $orderId): array {
    $o = DB::table('orders')->find($orderId); if (!$o) return [false, 'Order not found.', null];
    if (!in_array($o->status, ['Partial Return Pending', 'Return Pending'], true)) return [false, 'Only an order waiting for its return can be received.', $o->status];
    $to = $o->status === 'Partial Return Pending' ? 'Partial Returned' : 'Returned'; $now = now(); $n = 0;
    DB::transaction(function () use ($o, $orderId, $to, $now, &$n) {
      $n = DB::table('order_items')->where('order_id', $orderId)->where('ret_qty', '>', 0)->update(['outcome'=>'Returned', 'outcome_at'=>$now]);
      DB::table('orders')->where('id', $orderId)->update(['status'=>$to, 'return_received_at'=>$now, 'return_type'=>$o->return_type ?: ($to === 'Returned' ? 'Full' : 'Partial'), 'updated_at'=>$now]);
      DB::table('order_status_logs')->insert(['order_id'=>$orderId, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'System', 'from_status'=>$o->status, 'to_status'=>$to, 'note'=>'Returned goods received at the warehouse', 'created_at'=>$now, 'updated_at'=>$now]);
    });
    OrderActivity::log($orderId, 'return_received', $o->status, $to, 'Returned items received at the warehouse ('.$n.' item line'.($n === 1 ? '' : 's').' → Returned). Stock is not changed yet.');
    return [true, ($to === 'Returned' ? 'Marked Returned.' : 'Partial return received.'), $to];
  }
}
