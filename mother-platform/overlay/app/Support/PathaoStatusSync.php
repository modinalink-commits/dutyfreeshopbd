<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/**
 * The one place where Pathao delivery / return updates turn into order outcomes.
 * Callers (webhook, scheduled reconciliation, manual sync) go through CourierSync::apply(), which hands every Pathao
 * update that means "partial delivery" or "return" to outcome() below.
 *
 *   Shipment ── partial delivery ──▶ Return Pending (return_type Partial)  ── returned goods back ──▶ Returned
 *   Shipment ── whole parcel back ─▶ Return Pending (return_type Full)     ── returned goods back ──▶ Returned
 *   Shipment ── exchange ──────────▶ Return Pending (return_type Exchange) ── goods back ──▶ Exchange Received
 *
 * Pathao reports the parcel, not the items. We never guess an item or a money figure: the returned items are worked
 * out only when the amount Pathao collected matches exactly ONE combination of the order's items; otherwise the order
 * still goes to Return Pending (Partial), flagged for manual review ("Billing Mismatch" / "Item Review").
 * Stock goes back only when the goods are confirmed received, and only for items that are known.
 */
class PathaoStatusSync {
  const OPEN = ['Shipment', 'Late Delivery', 'Delivered', 'Partial Delivered', 'Return Pending'];
  /** Stages a courier verdict (Partial / Exchange / Return) may still move forward. "Delivered" is included because an earlier sync may have read Pathao's "Partial Delivery" as plain delivered. */
  const ENTRY = ['Shipment', 'Late Delivery', 'Delivered'];

  public static function collected(array $i): ?float {
    foreach (['collected_amount', 'amount_collected', 'collected_cod'] as $k) if (isset($i[$k]) && is_numeric($i[$k]) && (float)$i[$k] > 0) return (float)$i[$k];
    return null;
  }

  /** $action comes from PathaoStatusMapper (the admin-editable map): partial_pending | partial_received | exchange_pending | exchange_received | return_pending | returned. Safe to run twice for the same event. */
  public static function outcome(int $id, string $action, array $i = []): void {
    // Received / Returned are set only when OUR warehouse confirms the goods in hand (the Received button). The courier result only ever makes an order "Pending".
    $action = ['partial_received'=>'partial_pending', 'exchange_received'=>'exchange_pending', 'returned'=>'return_pending'][$action] ?? $action;
    $o = DB::table('orders')->find($id); if (!$o || !in_array($o->status, self::OPEN, true)) return;
    if ($action === 'exchange_pending') { if (in_array($o->status, self::ENTRY, true)) { DB::table('orders')->where('id', $id)->update(['return_type' => 'Exchange']); self::setStatus($o, 'Return Pending', 'Pathao: Exchange'); } return; }
    if ($action === 'exchange_received') { if ($o->status === 'Return Pending' && $o->return_type === 'Exchange') { DB::table('orders')->where('id', $id)->update(['return_received_at' => now()]); self::setStatus($o, 'Exchange Received', 'Pathao: exchange received'); } return; }
    $items = DB::table('order_items')->where('order_id', $id)->orderBy('id')->get(); if ($items->isEmpty()) return;
    $known = $items->contains(fn($x) => $x->outcome !== null);
    $got = self::collected($i);
    if ($action === 'partial_pending') {
      if ($known) { self::checkMoney($o, $items, $got); if ($o->status !== 'Return Pending' && $o->return_type === 'Partial') self::setStatus($o, 'Return Pending', 'Pathao: Partial Return'); return; }
      if ($o->status === 'Return Pending') return;
      $ret = self::match($items, $got, $o);
      if ($ret !== null) { [$ok, $msg] = ShipmentOutcome::save($id, $ret, 'Pathao Partial Return (auto, matched on collected ৳'.number_format((float)$got, 2).')'); if ($ok) self::clear($id); return; }
      DB::table('orders')->where('id', $id)->update(['return_type' => 'Partial']);
      self::setStatus($o, 'Return Pending', 'Pathao: Partial Return (returned items not known yet)');
      self::flag($id, $got === null ? 'Item Review' : 'Billing Mismatch', $got === null
        ? 'Pathao reported a Partial Return but did not say which items came back or how much was collected. Record the delivery outcome for this order.'
        : 'Pathao collected ৳'.number_format($got, 2).' (parcel ৳'.number_format((float)$o->total, 2).'); no single combination of items matches. Record the delivery outcome and check the billing.');
      if ($got !== null) DB::table('orders')->where('id', $id)->update(['collected_amount' => $got]);
      return;
    }
    if ($action === 'return_pending') {
      if (!in_array($o->status, self::ENTRY, true) || $known) return;   // already in a return flow, or staff recorded it
      $all = []; foreach ($items as $x) $all[$x->id] = (int)$x->qty;
      ShipmentOutcome::save($id, $all, 'Pathao Regular Return (auto)');
      return;
    }
    if ($action === 'partial_received') {
      if ($o->status !== 'Return Pending' || $o->return_type !== 'Partial') return;
      if ($items->contains(fn($x) => (int)($x->ret_qty ?? 0) > 0)) { [$ok] = ShipmentOutcome::receive($id); if ($ok) self::clear($id); }
      else self::flag($id, 'Item Review', 'Pathao says the returned goods are back, but the returned items are not recorded. Record the delivery outcome, then confirm the received quantities.');
      return;
    }
    if ($action === 'returned') {
      if ($o->status === 'Return Pending' && $o->return_type === 'Partial') return;
      if (in_array($o->status, ['Shipment', 'Late Delivery'], true) && !$known) { $all = []; foreach ($items as $x) $all[$x->id] = (int)$x->qty; ShipmentOutcome::save($id, $all, 'Pathao: whole parcel returned (auto)'); }
      $o2 = DB::table('orders')->find($id);
      if ($o2 && $o2->status === 'Return Pending') { [$ok] = ShipmentOutcome::receive($id); if ($ok) self::clear($id); }
    }
  }

  /** Returned quantity per item, only when exactly one combination of delivered items equals what Pathao collected (item prices alone, or plus the delivery charge). */
  private static function match($items, ?float $got, object $o): ?array {
    if ($got === null) return null;
    $L = $items->values()->all(); $n = count($L); $combos = 1; foreach ($L as $x) { $combos *= ((int)$x->qty + 1); if ($combos > 20000) return null; }
    $ship = (float)($o->shipping ?? 0); $targets = [$got, $got - $ship]; $hits = []; $cur = [];
    $rec = function (int $k, float $sum) use (&$rec, &$cur, &$hits, $L, $n, $targets) {
      if ($k === $n) {
        $units = array_sum($cur); $all = 0; foreach ($L as $x) $all += (int)$x->qty;
        if ($units < 1 || $units >= $all) return;
        foreach ($targets as $t) if (abs($sum - $t) < 0.5) { $hits[implode(',', $cur)] = $cur; return; }
        return;
      }
      for ($q = 0; $q <= (int)$L[$k]->qty; $q++) { $cur[$k] = $q; $rec($k + 1, $sum + $q * (float)$L[$k]->price); }
    };
    $rec(0, 0.0);
    if (count($hits) !== 1) return null;
    $v = array_values($hits)[0]; $ret = []; foreach ($L as $k => $x) $ret[$x->id] = (int)$x->qty - (int)$v[$k];
    return $ret;
  }
  private static function checkMoney(object $o, $items, ?float $got): void {
    if ($got === null) return;
    $recV = ShipmentOutcome::summary($o, $items)['recV']; $ship = (float)($o->original_shipping ?? $o->shipping ?? 0);
    DB::table('orders')->where('id', $o->id)->update(['collected_amount' => $got]);
    if (abs($got - $recV) > 1 && abs($got - ($recV + $ship)) > 1) self::flag((int)$o->id, 'Billing Mismatch', 'Pathao collected ৳'.number_format($got, 2).' but the delivered items add up to ৳'.number_format($recV, 2).' (+ delivery ৳'.number_format($ship, 2).'). Check the billing before settling.');
  }
  private static function setStatus(object $o, string $to, string $note): void {
    if ($o->status === $to) return; $now = now();
    DB::table('orders')->where('id', $o->id)->update(['status' => $to, 'updated_at' => $now]);
    DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => null, 'user_name' => 'Pathao (auto)', 'from_status' => $o->status, 'to_status' => $to, 'note' => $note, 'created_at' => $now, 'updated_at' => $now]);
    OrderActivity::log((int)$o->id, 'pathao_outcome', $o->status, $to, $note.'.');
  }
  public static function flag(int $id, string $flag, string $note): void {
    $cur = DB::table('orders')->where('id', $id)->value('billing_flag');
    DB::table('orders')->where('id', $id)->update(['billing_flag' => $flag, 'billing_flag_note' => $note]);
    if ($cur !== $flag) OrderActivity::log($id, 'billing_flag', $cur, $flag, $note);
  }
  public static function clear(int $id): void { DB::table('orders')->where('id', $id)->whereNotNull('billing_flag')->update(['billing_flag' => null, 'billing_flag_note' => null]); }
}
