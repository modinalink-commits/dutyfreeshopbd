<?php
namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Goods of an earlier order that come back with a later parcel (owner 16 Nov 2026).
 * The return belongs to the order the goods were sold on (source); the later order is only the parcel they came back with (carrier) and is never changed.
 * Stock goes up only here, at "confirm received": whole cartons to the carton product, sellable loose packets to its Single Packet twin, damaged goods to no stock.
 * Money is a separate step (settle): the value is worked out from the source order's real selling price after its discount.
 * Everything is counted in packets (pack_size of the product, 1 when the product has no Single Packet twin) so a carton can never be returned twice as packets.
 */
class PastReturn {
  const SOURCE_STATUSES = ['Delivered', 'Partial Delivered', 'Returned']; // orders whose goods (or part of them) are with the customer
  public static function ready(): bool { try { return Schema::hasTable('order_past_returns'); } catch (\Throwable $e) { return false; } }
  private static function tail(?string $phone): string { return substr(preg_replace('/\D/', '', (string)$phone), -11); }
  /** Packets per carton for a sold line: the product's pack size when it has a Single Packet twin, else 1 (the line is returned in whole units only). */
  private static function pack(?int $pid): array {
    if (!$pid) return [1, null];
    $twin = DB::table('products')->where('parent_product_id', $pid)->first(['id', 'name']);
    if (!$twin) return [1, null];
    return [max(1, (int)(DB::table('products')->where('id', $pid)->value('pack_size') ?: 10)), $twin];
  }
  /** Packets of one sold line already taken back through this module. */
  private static function taken(int $itemId): int {
    return (int)DB::table('order_past_returns')->where('source_item_id', $itemId)->where('status', 'received')->selectRaw('coalesce(sum(cartons * pack_size + packets + damaged_packets), 0) as n')->value('n');
  }
  /** What the customer still holds of one sold line, in packets: sold minus the cartons that went back on the order's own return minus earlier past-order returns. */
  private static function line(object $i): array {
    [$pack, $twin] = self::pack($i->product_id ? (int)$i->product_id : null);
    $kept = max(0, (int)$i->qty - (int)($i->ret_qty ?? 0)); $sold = $kept * $pack; $taken = self::taken((int)$i->id);
    return ['pack' => $pack, 'twin' => $twin, 'sold' => $sold, 'taken' => $taken, 'left' => max(0, $sold - $taken), 'ownReturn' => (int)($i->ret_qty ?? 0)];
  }
  /** Earlier orders of the same customer with what can still come back. */
  public static function options(int $carrierId): array {
    $c = DB::table('orders')->find($carrierId); if (!$c) return [false, 'Order not found.', []];
    $ph = self::tail($c->phone); if (strlen($ph) < 10) return [false, 'This order has no usable phone number, so earlier orders cannot be matched safely.', []];
    $orders = DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->where('id', '!=', $carrierId)->whereIn('status', self::SOURCE_STATUSES)->where('created_at', '>=', now()->subDays(365))->orderByDesc('id')->limit(25)->get(['id', 'invoice', 'status', 'total', 'created_at', 'customer_name']);
    $out = [];
    foreach ($orders as $o) {
      $rows = [];
      foreach (DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get() as $i) {
        $l = self::line($i); if ($l['sold'] <= 0) continue;
        $rows[] = ['id' => (int)$i->id, 'name' => (string)$i->name, 'qty' => (int)$i->qty, 'pack' => $l['pack'], 'has_packets' => $l['twin'] !== null, 'sold' => $l['sold'], 'taken' => $l['taken'], 'left' => $l['left'], 'price' => (float)$i->price];
      }
      if ($rows) $out[] = ['id' => (int)$o->id, 'invoice' => Inv::show($o->invoice), 'status' => (string)$o->status, 'date' => \Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y'), 'items' => $rows];
    }
    return [true, 'ok', $out];
  }
  /** Value of $units packets of a sold line at the price really paid: line price per packet, reduced by the order's own discount share. */
  private static function value(object $o, object $i, int $pack, int $units): float {
    $sub = (float)DB::table('order_items')->where('order_id', $o->id)->selectRaw('coalesce(sum(qty * price), 0) as s')->value('s');
    $disc = (float)($o->original_total !== null ? ($o->original_discount ?? 0) : ($o->discount ?? 0));
    $ratio = $sub > 0 ? max(0, min(1, 1 - $disc / $sub)) : 1;
    return round($units * ((float)$i->price / max(1, $pack)) * $ratio, 2);
  }
  /**
   * Confirm that the goods are in hand. $lines = [['item'=>source order_item id, 'cartons'=>n, 'packets'=>n, 'damaged'=>n], ...]. $token makes the request safe to repeat.
   * @return array [ok, message, rows]
   */
  public static function receive(int $carrierId, array $lines, string $token): array {
    if (!self::ready()) return [false, 'The past-order return table is not installed yet.', []];
    $token = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $token), 0, 60); if (strlen($token) < 8) return [false, 'Missing request token. Reload the page and try again.', []];
    $c = DB::table('orders')->find($carrierId); if (!$c) return [false, 'Order not found.', []];
    $ph = self::tail($c->phone);
    $want = [];
    foreach ($lines as $l) { $id = (int)($l['item'] ?? 0); $ca = max(0, (int)($l['cartons'] ?? 0)); $pk = max(0, (int)($l['packets'] ?? 0)); $dm = max(0, (int)($l['damaged'] ?? 0)); if ($id && ($ca + $pk + $dm) > 0) $want[$id] = ['cartons' => $ca, 'packets' => $pk, 'damaged' => $dm]; }
    if (!$want) return [false, 'Enter at least one quantity.', []];
    if ($done = DB::table('order_past_returns')->where('batch_token', $token)->get()) { if ($done->isNotEmpty()) return [true, 'This return was already received. Nothing was added twice.', $done->all()]; }
    $uid = Auth::id(); $uname = Auth::user()->name ?? 'System'; $now = now(); $made = []; $err = null; $dup = false;
    try {
      DB::transaction(function () use ($want, $carrierId, $c, $ph, $token, $uid, $uname, $now, &$made, &$err, &$dup) {
        $items = DB::table('order_items')->whereIn('id', array_keys($want))->orderBy('id')->get()->keyBy('id');
        // lock the source orders (in id order) so two people receiving the same goods at the same moment are handled one after the other
        $srcIds = $items->pluck('order_id')->unique()->sort()->values()->all();
        $src = DB::table('orders')->whereIn('id', $srcIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if (DB::table('order_past_returns')->where('batch_token', $token)->exists()) { $dup = true; return; } // nothing was written yet in this transaction
        foreach ($want as $id => $w) {
          $i = $items[$id] ?? null; if (!$i) { $err = 'A selected product line was not found.'; throw new \RuntimeException('__rollback__'); }
          $o = $src[$i->order_id] ?? null;
          if (!$o || (int)$o->id === $carrierId) { $err = 'Goods of this parcel itself are received with "Return Received", not here.'; throw new \RuntimeException('__rollback__'); }
          if (self::tail($o->phone) !== $ph || strlen($ph) < 10) { $err = 'Order '.Inv::show($o->invoice).' belongs to another customer.'; throw new \RuntimeException('__rollback__'); }
          if (!in_array($o->status, self::SOURCE_STATUSES, true)) { $err = 'Order '.Inv::show($o->invoice).' is "'.$o->status.'": nothing of it is with the customer.'; throw new \RuntimeException('__rollback__'); }
          $l = self::line($i); $pack = $l['pack'];
          if ($pack === 1 && $w['packets'] > 0) { $err = '"'.$i->name.'" is not sold by the packet: enter whole units only.'; throw new \RuntimeException('__rollback__'); }
          $units = $w['cartons'] * $pack + $w['packets'] + $w['damaged'];
          if ($units > $l['left']) { $err = '"'.$i->name.'" ('.Inv::show($o->invoice).'): '.$units.' '.($pack > 1 ? 'packets' : 'units').' entered, but only '.$l['left'].' can still come back (sold '.$l['sold'].', already returned '.$l['taken'].').'; throw new \RuntimeException('__rollback__'); }
          $val = self::value($o, $i, $pack, $units);
          $rid = (int)DB::table('order_past_returns')->insertGetId(['batch_token' => $token, 'carrier_order_id' => $carrierId, 'source_order_id' => (int)$o->id, 'source_item_id' => (int)$i->id, 'product_id' => $i->product_id ?: null, 'product_name' => mb_substr((string)$i->name, 0, 255),
            'pack_size' => $pack, 'cartons' => $w['cartons'], 'packets' => $w['packets'], 'damaged_packets' => $w['damaged'], 'unit_price' => (float)$i->price, 'refund_value' => $val, 'status' => 'received',
            'received_by' => $uid, 'received_name' => mb_substr($uname, 0, 120), 'received_at' => $now, 'settle_status' => 'pending', 'created_at' => $now, 'updated_at' => $now]);
          $ref = (string)$o->invoice; $bits = [];
          if ($w['cartons'] > 0 && $i->product_id) {
            $lid = Procure::stock((int)$i->product_id, $w['cartons'], 'order-return', $ref, false);
            $rc = $o->stock_deducted_at !== null ? Costing::restock((int)$i->id, $w['cartons'], (int)$o->id, $ref) : 0; Costing::sync((int)$i->product_id);
            if ($rc > 0) Procure::logCost($lid, $rc / $w['cartons'], 'Returned to original batch(es)');
            $bits[] = '+'.$w['cartons'].' '.($pack > 1 ? 'carton' : 'unit').($w['cartons'] > 1 ? 's' : '').' to stock';
          }
          if ($w['packets'] > 0 && $l['twin']) {
            Procure::stock((int)$l['twin']->id, $w['packets'], 'order-return', $ref, false); Costing::sync((int)$l['twin']->id);
            DB::table('products')->where('id', $l['twin']->id)->update(['stock_status' => 'in_stock', 'updated_at' => $now]);
            $bits[] = '+'.$w['packets'].' loose packets to Single Packet stock';
          }
          if ($w['damaged'] > 0) $bits[] = $w['damaged'].' damaged (not added to stock)';
          $txt = $i->name.': '.implode(', ', $bits);
          Procure::audit('past_return.received', 'Past-order return: '.Inv::show($o->invoice).' · '.$i->name, ['source_order' => $o->invoice, 'carrier_order' => $c->invoice, 'left_before' => $l['left']], ['cartons' => $w['cartons'], 'packets' => $w['packets'], 'damaged' => $w['damaged'], 'value' => $val, 'row' => $rid]);
          OrderActivity::log((int)$o->id, 'past_return_received', $o->status, $o->status, 'Goods of this order came back with parcel '.Inv::show($c->invoice).' — '.$txt.'. Value ৳'.number_format($val, 2).' (money is settled separately).', ['row' => $rid, 'carrier' => $c->invoice]);
          OrderActivity::log($carrierId, 'past_return_carried', $c->status, $c->status, 'This parcel brought back goods of earlier order '.Inv::show($o->invoice).' — '.$txt.'. This order itself is not changed.', ['row' => $rid, 'source' => $o->invoice]);
          $made[] = $rid;
        }
        if ($err) throw new \RuntimeException('__rollback__');
      });
    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) { $dup = true; }
    catch (\RuntimeException $e) { if ($e->getMessage() !== '__rollback__') { report($e); return [false, 'Nothing was saved: '.mb_substr($e->getMessage(), 0, 160), []]; } }
    if ($err) return [false, $err.' Nothing was saved.', []];
    if ($dup) return [true, 'This return was already received. Nothing was added twice.', DB::table('order_past_returns')->where('batch_token', $token)->get()->all()];
    return [true, count($made).' line'.(count($made) === 1 ? '' : 's').' received. Stock updated. The money is settled as a separate step on the original order.', DB::table('order_past_returns')->whereIn('id', $made)->get()->all()];
  }
  /** Money step, separate from stock. type: refunded (paid back), adjusted (taken off what the customer owes on another order, shown as its own entry), none (no money due). */
  public static function settle(int $rowId, string $type, float $amount, ?string $method, ?string $note, ?int $adjustOrderId): array {
    if (!in_array($type, ['refunded', 'adjusted', 'none'], true)) return [false, 'Unknown settlement type.'];
    $r = DB::table('order_past_returns')->find($rowId); if (!$r || $r->status !== 'received') return [false, 'This return line is not open.'];
    if ($type === 'none') $amount = 0;
    if ($amount < 0 || $amount > (float)$r->refund_value + 0.01) return [false, 'The amount must be between 0 and ৳'.number_format((float)$r->refund_value, 2).' (the value of the returned goods at the price paid).'];
    if ($type === 'adjusted') { $ao = $adjustOrderId ? DB::table('orders')->find($adjustOrderId) : null; if (!$ao) return [false, 'Choose the order the amount is adjusted against.']; }
    $n = DB::table('order_past_returns')->where('id', $rowId)->where('status', 'received')->where('settle_status', 'pending')->update(['settle_status' => $type, 'settle_amount' => round($amount, 2), 'settle_method' => $method ? mb_substr($method, 0, 40) : null, 'settle_note' => $note ? mb_substr($note, 0, 300) : null,
      'settle_order_id' => $type === 'adjusted' ? $adjustOrderId : null, 'settle_by' => Auth::id(), 'settle_name' => mb_substr(Auth::user()->name ?? 'System', 0, 120), 'settle_at' => now(), 'updated_at' => now()]);
    if (!$n) return [false, 'This line was already settled.'];
    $so = DB::table('orders')->find($r->source_order_id); $what = $type === 'refunded' ? 'Refund paid' : ($type === 'adjusted' ? 'Adjusted against order '.Inv::show($ao->invoice) : 'No money due');
    Procure::audit('past_return.settled', 'Past-order return settled: '.Inv::show($so->invoice ?? '').' · '.$r->product_name, ['value' => (float)$r->refund_value], ['type' => $type, 'amount' => round($amount, 2), 'method' => $method, 'adjust_order' => $adjustOrderId]);
    OrderActivity::log((int)$r->source_order_id, 'past_return_settled', null, null, $what.': ৳'.number_format($amount, 2).' for returned '.$r->product_name.($method ? ' ('.$method.')' : '').($note ? ' · '.$note : ''), ['row' => $rowId]);
    if ($type === 'adjusted') OrderActivity::log((int)$adjustOrderId, 'past_return_adjustment', null, null, 'Adjustment entry: ৳'.number_format($amount, 2).' for goods returned from earlier order '.Inv::show($so->invoice ?? '').' ('.$r->product_name.'). Shown separately; this order\'s sale is not changed.', ['row' => $rowId]);
    return [true, $what.'.'];
  }
  /** Correction: undo a received line (wrong entry). Stock that was added is taken out again; refused when that stock is no longer there. The row stays, marked voided, with who and why. */
  public static function void(int $rowId, string $reason): array {
    $reason = trim($reason); if (mb_strlen($reason) < 3) return [false, 'Write the reason for the correction.'];
    $msg = null; $ok = false;
    DB::transaction(function () use ($rowId, $reason, &$msg, &$ok) {
      $r = DB::table('order_past_returns')->where('id', $rowId)->lockForUpdate()->first();
      if (!$r || $r->status !== 'received') { $msg = 'This return line is not open.'; return; }
      if ($r->settle_status !== 'pending' && $r->settle_status !== 'none') { $msg = 'Money was already settled for this line. It cannot be undone here.'; return; }
      $o = DB::table('orders')->find($r->source_order_id); $ref = (string)($o->invoice ?? '');
      $twin = $r->product_id ? DB::table('products')->where('parent_product_id', $r->product_id)->first(['id', 'stock']) : null;
      if ($r->cartons > 0 && $r->product_id && (int)DB::table('products')->where('id', $r->product_id)->value('stock') < (int)$r->cartons) { $msg = 'The cartons added by this return are no longer in stock (already sold), so it cannot be undone automatically.'; return; }
      if ($r->packets > 0 && (!$twin || (int)$twin->stock < (int)$r->packets)) { $msg = 'The packets added by this return are no longer in stock (already sold), so it cannot be undone automatically.'; return; }
      if ($r->cartons > 0 && $r->product_id) { Procure::stock((int)$r->product_id, -(int)$r->cartons, 'order-return', $ref.' (correction)', true); Costing::sync((int)$r->product_id); }
      if ($r->packets > 0 && $twin) { Procure::stock((int)$twin->id, -(int)$r->packets, 'order-return', $ref.' (correction)', false); Costing::sync((int)$twin->id); }
      DB::table('order_past_returns')->where('id', $rowId)->update(['status' => 'voided', 'void_by' => Auth::id(), 'void_name' => mb_substr(Auth::user()->name ?? 'System', 0, 120), 'void_at' => now(), 'void_reason' => mb_substr($reason, 0, 300), 'updated_at' => now()]);
      Procure::audit('past_return.voided', 'Past-order return corrected: '.Inv::show($ref).' · '.$r->product_name, ['cartons' => (int)$r->cartons, 'packets' => (int)$r->packets, 'damaged' => (int)$r->damaged_packets], ['status' => 'voided', 'reason' => $reason]);
      OrderActivity::log((int)$r->source_order_id, 'past_return_voided', null, null, 'Past-order return entry removed (correction): '.$r->product_name.' · '.$reason.'. Stock that it had added was taken out again.', ['row' => $rowId]);
      $ok = true; $msg = 'Entry removed and its stock taken out again. The record is kept.';
    });
    return [$ok, $msg ?? 'Failed.'];
  }
  /** Rows to show on an order page: returns of this order's goods, and returns this parcel carried. */
  public static function forOrder(int $orderId): array {
    if (!self::ready()) return ['own' => [], 'carried' => []];
    $inv = fn($rows, $col) => DB::table('orders')->whereIn('id', collect($rows)->pluck($col)->filter()->unique())->pluck('invoice', 'id');
    $own = DB::table('order_past_returns')->where('source_order_id', $orderId)->orderByDesc('id')->get()->all(); $car = DB::table('order_past_returns')->where('carrier_order_id', $orderId)->orderByDesc('id')->get()->all();
    $i1 = $inv($own, 'carrier_order_id'); $i2 = $inv($car, 'source_order_id');
    foreach ($own as $r) $r->other_invoice = isset($i1[$r->carrier_order_id]) ? Inv::show($i1[$r->carrier_order_id]) : null;
    foreach ($car as $r) $r->other_invoice = isset($i2[$r->source_order_id]) ? Inv::show($i2[$r->source_order_id]) : null;
    return ['own' => $own, 'carried' => $car];
  }

  /**
   * Admin self-check on live data with NOTHING kept: every scenario runs inside one database transaction that is always rolled back
   * (stock, rows, logs and any auto-publish all disappear). Returns [ok, message, checks[]].
   */
  public static function selfTest(int $carrierId): array {
    [$ok, $m, $orders] = self::options($carrierId); if (!$ok) return [false, $m, []];
    $pick = [];
    foreach ($orders as $o) { foreach ($o['items'] as $i) { if ($i['has_packets'] && $i['left'] >= $i['pack'] && $i['pack'] >= 5) { $pick[] = ['order' => $o, 'item' => $i]; break; } } if (count($pick) === 2) break; }
    if (count($pick) < 2) return [false, 'This customer needs two earlier delivered orders with a carton product (Single Packet twin) to run the check.', []];
    [$A, $B] = [$pick[0]['item'], $pick[1]['item']]; $oa = $pick[0]['order']; $ob = $pick[1]['order'];
    $out = []; $add = function (string $name, bool $pass, string $detail) use (&$out) { $out[] = ['name' => $name, 'pass' => $pass, 'detail' => $detail]; };
    $stock = fn($pid) => (int)DB::table('products')->where('id', $pid)->value('stock');
    $rows = fn() => (int)DB::table('order_past_returns')->count();
    $left = function (int $itemId) { $i = DB::table('order_items')->find($itemId); return self::line($i)['left']; };
    $snap = fn() => md5(json_encode([DB::table('orders')->where('id', $carrierId)->first(), DB::table('order_items')->where('order_id', $carrierId)->orderBy('id')->get()->all()]));
    DB::beginTransaction();
    try {
      $ia = DB::table('order_items')->find($A['id']); $ib = DB::table('order_items')->find($B['id']);
      $ta = self::pack((int)$ia->product_id)[1]; $tb = self::pack((int)$ib->product_id)[1];
      $carrier0 = $snap(); $src0 = md5(json_encode([DB::table('orders')->where('id', $oa['id'])->first(), DB::table('order_items')->where('order_id', $oa['id'])->orderBy('id')->get()->all()]));
      // 1. loose packets of an old order
      $s0 = $stock($ta->id); $p0 = $stock((int)$ia->product_id); $l0 = $left($A['id']); $pub0 = (bool)DB::table('products')->where('id', $ta->id)->value('published');
      [$k, $msg, $r1] = self::receive($carrierId, [['item' => $A['id'], 'cartons' => 0, 'packets' => 3, 'damaged' => 0]], 'selftest-loose-1');
      $row = $r1[0] ?? null;
      $add('1. পুরোনো অর্ডারের ৩টি খুচরা প্যাকেট', $k && $stock($ta->id) === $s0 + 3 && $stock((int)$ia->product_id) === $p0 && $left($A['id']) === $l0 - 3 && $row && (int)$row->source_order_id === $oa['id'] && (int)$row->carrier_order_id === $carrierId,
        'অর্ডার '.$oa['invoice'].' · '.$A['name'].': প্যাকেট স্টক '.$s0.' → '.$stock($ta->id).', কার্টন স্টক '.$p0.' → '.$stock((int)$ia->product_id).', ফেরতযোগ্য '.$l0.' → '.$left($A['id']).', মূল্য ৳'.($row ? number_format((float)$row->refund_value, 2) : '?').' · গ্রহণকারী '.($row->received_name ?? '?'));
      $add('1ক. বহনকারী অর্ডার ও মূল অর্ডারের বিক্রি অপরিবর্তিত', $snap() === $carrier0 && md5(json_encode([DB::table('orders')->where('id', $oa['id'])->first(), DB::table('order_items')->where('order_id', $oa['id'])->orderBy('id')->get()->all()])) === $src0, 'অর্ডার ও আইটেম সারি আগের মতোই আছে (স্ট্যাটাস, পরিমাণ, মোট)');
      $add('1খ. প্যাকেট পণ্য স্বয়ংক্রিয় প্রকাশ', true, 'গ্রহণের আগে published='.($pub0 ? 'হ্যাঁ' : 'না').', পরে='.(DB::table('products')->where('id', $ta->id)->value('published') ? 'হ্যাঁ' : 'না').' (বিদ্যমান স্টক-ইন নিয়ম)');
      // 2. duplicate confirm (same request again)
      $n = $rows(); $s1 = $stock($ta->id);
      [$k2, $msg2] = self::receive($carrierId, [['item' => $A['id'], 'cartons' => 0, 'packets' => 3, 'damaged' => 0]], 'selftest-loose-1');
      $add('2. একই গ্রহণ দুবার নিশ্চিত', $k2 && $rows() === $n && $stock($ta->id) === $s1, 'সারি '.$n.' → '.$rows().', প্যাকেট স্টক '.$s1.' → '.$stock($ta->id).' · "'.$msg2.'"');
      // 3. two source orders in one parcel
      $n = $rows(); $sa = $stock($ta->id); $sb = $stock($tb->id);
      [$k3, $msg3, $r3] = self::receive($carrierId, [['item' => $A['id'], 'cartons' => 0, 'packets' => 2, 'damaged' => 0], ['item' => $B['id'], 'cartons' => 0, 'packets' => 1, 'damaged' => 0]], 'selftest-multi-1');
      $srcs = collect($r3)->pluck('source_order_id')->unique()->count();
      $add('3. একই পার্সেলে দুটি অর্ডারের রিটার্ন', $k3 && $rows() === $n + 2 && $srcs === 2 && $stock($ta->id) === $sa + 2 && $stock($tb->id) === $sb + 1, 'অর্ডার '.$oa['invoice'].' ও '.$ob['invoice'].': '.($rows() - $n).'টি সারি, মূল অর্ডার '.$srcs.'টি, বহনকারী একটিই');
      // 4. more than can come back
      $n = $rows(); $lb = $left($B['id']); $sb = $stock($tb->id);
      [$k4, $msg4] = self::receive($carrierId, [['item' => $B['id'], 'cartons' => 0, 'packets' => $lb + 1, 'damaged' => 0]], 'selftest-excess-1');
      $add('4. অতিরিক্ত পরিমাণ', !$k4 && $rows() === $n && $stock($tb->id) === $sb, 'ফেরতযোগ্য '.$lb.', চাওয়া '.($lb + 1).' → "'.$msg4.'"');
      // 5. damaged goods
      $n = $rows(); $sb = $stock($tb->id); $pb = $stock((int)$ib->product_id); $lb = $left($B['id']);
      [$k5, $msg5, $r5] = self::receive($carrierId, [['item' => $B['id'], 'cartons' => 0, 'packets' => 0, 'damaged' => 2]], 'selftest-damaged-1');
      $add('5. ক্ষতিগ্রস্ত পণ্য', $k5 && $rows() === $n + 1 && $stock($tb->id) === $sb && $stock((int)$ib->product_id) === $pb && $left($B['id']) === $lb - 2 && (int)($r5[0]->damaged_packets ?? 0) === 2, 'প্যাকেট স্টক '.$sb.' → '.$stock($tb->id).' (যোগ হয়নি), ফেরতযোগ্য '.$lb.' → '.$left($B['id']).', রেকর্ডে ২টি ক্ষতিগ্রস্ত');
      // 6. a whole carton
      $lb = $left($B['id']);
      if ($lb >= $B['pack']) { $pb = $stock((int)$ib->product_id); $sb = $stock($tb->id);
        [$k6, $msg6] = self::receive($carrierId, [['item' => $B['id'], 'cartons' => 1, 'packets' => 0, 'damaged' => 0]], 'selftest-carton-1');
        $add('6. পুরো কার্টন', $k6 && $stock((int)$ib->product_id) === $pb + 1 && $stock($tb->id) === $sb && $left($B['id']) === $lb - $B['pack'], 'কার্টন স্টক '.$pb.' → '.$stock((int)$ib->product_id).', ফেরতযোগ্য '.$lb.' → '.$left($B['id']).' (প্রতি কার্টনে '.$B['pack'].' প্যাকেট, পণ্যের সেটিংস থেকে)');
      } else $add('6. পুরো কার্টন', true, 'বাদ: এই লাইনে পুরো কার্টন ফেরতযোগ্য নেই');
      // 7. money step is separate
      $rid = (int)$row->id; $val = (float)$row->refund_value;
      [$k7a, $m7a] = self::settle($rid, 'refunded', $val + 50, 'bKash', null, null);
      [$k7b, $m7b] = self::settle($rid, 'adjusted', $val, null, 'self test', $carrierId);
      [$k7c, $m7c] = self::settle($rid, 'refunded', $val, 'Cash', null, null);
      $adj = DB::table('order_activity_logs')->where('order_id', $carrierId)->where('action_type', 'past_return_adjustment')->exists();
      $add('7. টাকা আলাদা ধাপ', !$k7a && $k7b && !$k7c && $adj && $snap() === $carrier0, 'মূল্যের বেশি ফেরত আটকায়; সমন্বয় ৳'.number_format($val, 2).' নতুন অর্ডারে আলাদা এন্ট্রি হিসেবে লেখা হয়, অর্ডারের মোট বদলায় না; দ্বিতীয়বার নিষ্পত্তি আটকায়');
      [$k7d, $m7d] = self::void($rid, 'self test');
      $add('7ক. টাকা নিষ্পত্তির পর সংশোধন আটকায়', !$k7d, '"'.$m7d.'"');
      // 8. correction keeps history
      $r8 = DB::table('order_past_returns')->where('batch_token', 'selftest-multi-1')->where('source_item_id', $A['id'])->first(); $sa = $stock($ta->id); $la = $left($A['id']);
      [$k8, $m8] = self::void((int)$r8->id, 'ভুল এন্ট্রি (self test)');
      $v = DB::table('order_past_returns')->find($r8->id);
      $add('8. সংশোধন ও ইতিহাস', $k8 && $stock($ta->id) === $sa - 2 && $left($A['id']) === $la + 2 && $v->status === 'voided' && $v->void_reason !== null, 'প্যাকেট স্টক '.$sa.' → '.$stock($ta->id).', ফেরতযোগ্য '.$la.' → '.$left($A['id']).', সারি মোছেনি (voided, কারণ ও নামসহ)');
      $logs = DB::table('order_activity_logs')->whereIn('order_id', [$oa['id'], $ob['id'], $carrierId])->where('action_type', 'like', 'past_return%')->count(); $aud = DB::table('audit_logs')->where('action', 'like', 'past_return.%')->count();
      $add('9. কে-কখন-কত রেকর্ড', $logs >= 8 && $aud >= 5, 'অর্ডার লগে '.$logs.'টি ও অডিট লগে '.$aud.'টি এন্ট্রি');
    } catch (\Throwable $e) { $add('ত্রুটি', false, mb_substr($e->getMessage(), 0, 300)); }
    finally { while (DB::transactionLevel() > 0) DB::rollBack(); }
    $kept = (int)DB::table('order_past_returns')->where('batch_token', 'like', 'selftest-%')->count();
    $add('10. পরীক্ষার পর কিছু রাখা হয়নি', $kept === 0, 'পরীক্ষার সারি '.$kept.'টি; স্টক ও লগ আগের অবস্থায়');
    $pass = collect($out)->every(fn($c) => $c['pass']);
    return [$pass, $pass ? 'All checks passed. Nothing was kept.' : 'Some checks failed. Nothing was kept.', $out];
  }
}
