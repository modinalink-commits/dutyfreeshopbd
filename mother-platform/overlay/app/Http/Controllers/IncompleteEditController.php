<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as MP;
use App\Support\OrderDiscount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Incomplete checkouts behave like New orders for every staff action (view, quick edit, coupon, discount, products, payment, courier, notes, history). They stay in their own table: no stock, SMS to the customer (except a payment request you send yourself), courier booking or revenue is touched until the entry is converted to a real order. */
class IncompleteEditController extends Controller {
  const STAGES = ['Open', 'Recovered', 'Cancel', 'Convert to Order'];
  private function err(string $m) { return response()->json(['ok'=>false, 'error'=>$m], 422); }
  private function load($id) { $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404); return $o; }
  private function log(int $id, string $kind, ?string $old = null, ?string $new = null, ?string $body = null, ?string $cat = null): void {
    DB::table('incomplete_activity')->insert(['incomplete_id'=>$id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? null, 'kind'=>$kind, 'category'=>$cat, 'old_value'=>$old !== null ? mb_substr($old, 0, 1000) : null, 'new_value'=>$new !== null ? mb_substr($new, 0, 1000) : null, 'body'=>$body !== null ? mb_substr($body, 0, 2000) : null, 'created_at'=>now(), 'updated_at'=>now()]);
  }
  private function phone(string $p): ?string { $d = preg_replace('/\D+/', '', $p); if (str_starts_with($d, '880')) $d = '0'.substr($d, 3); elseif (strlen($d) === 10 && $d[0] === '1') $d = '0'.$d; return preg_match('/^01[3-9]\d{8}$/', $d) ? $d : null; }
  /** The incomplete row seen as an order (same field names the order pages use). */
  public static function asOrder($r): object {
    return (object)['id'=>(int)$r->id, 'invoice'=>$r->invoice ?: 'INC-'.str_pad($r->id, 5, '0', STR_PAD_LEFT), 'status'=>'Incomplete', 'customer_name'=>(string)$r->name, 'phone'=>(string)$r->phone, 'email'=>$r->email, 'address'=>(string)$r->address, 'district'=>(string)$r->district,
      'courier'=>$r->courier ?? '', 'consignment_id'=>$r->consignment ?? '', 'coupon'=>$r->coupon ?? '', 'discount'=>(float)$r->discount, 'shipping'=>(float)$r->shipping, 'total'=>(float)$r->total, 'paid_total'=>0, 'payment_status'=>'Unpaid', 'payment_method'=>(string)$r->pay_method,
      'created_at'=>$r->created_at, 'source'=>'Website checkout'];
  }
  public static function asItems($r) {
    return collect(json_decode($r->items ?: '[]', true) ?: [])->values()->map(fn($i, $k) => (object)['id'=>$k + 1, 'product_id'=>$i['id'] ?? null, 'name'=>$i['name'] ?? '', 'qty'=>(int)($i['qty'] ?? 0), 'price'=>(float)($i['price'] ?? 0)]);
  }
  public function show($id) {
    $r = $this->load($id); $items = self::asItems($r);
    return view('incomplete_show', ['r'=>$r, 'o'=>self::asOrder($r), 'items'=>$items, 'imgs'=>IncompleteController::imgs([$r]), 'cust'=>DB::table('customers')->where('phone', $r->phone)->value('id'),
      'acts'=>DB::table('incomplete_activity')->where('incomplete_id', $id)->orderByDesc('id')->limit(200)->get()]);
  }
  /** Rewrites items/totals in one place so every action leaves a consistent row. */
  private function save($r, array $items, float $disc, float $ship, array $extra = []): void {
    $sub = 0; $q = 0; foreach ($items as $i) { $sub += (float)$i['price'] * (int)$i['qty']; $q += (int)$i['qty']; }
    $disc = max(0, min($disc, $sub));
    DB::table('incomplete_orders')->where('id', $r->id)->update($extra + ['items'=>json_encode(array_values($items), JSON_UNESCAPED_UNICODE), 'qty'=>$q, 'subtotal'=>round($sub, 2), 'discount'=>round($disc, 2), 'shipping'=>round($ship, 2), 'total'=>round(max(0, $sub - $disc + $ship), 2), 'updated_at'=>now()]);
  }
  private function autoDisc($r, array $items, string $coupon): array {
    $sub = 0; $qty = 0; foreach ($items as $i) { $sub += (float)$i['price'] * (int)$i['qty']; $qty += (int)$i['qty']; }
    $a = OrderDiscount::compute($r->phone, $sub, $coupon !== '' ? $coupon : null, ['qty'=>$qty, 'items'=>array_map(fn($x) => ['product_id'=>$x['id'] ?? null, 'price'=>$x['price'], 'qty'=>$x['qty']], $items)]);
    $kept = array_values(array_filter($a['lines'], fn($l) => $l['applied']));
    return [min($sub, round(array_sum(array_column($kept, 'amount')), 2)), $kept ? implode(' + ', array_column($kept, 'label')) : null, $a['coupon_error'] ?? null];
  }
  public function money(Request $req, $id) {
    $r = $this->load($id); if ($r->status === 'converted') return $this->err('Already converted to an order - edit the order instead.');
    $items = json_decode($r->items ?: '[]', true) ?: []; $disc = (float)$r->discount; $ship = (float)$r->shipping; $kind = (string)$req->input('kind'); $note = mb_substr(trim((string)$req->input('note')), 0, 300); $extra = []; $msg = 'Saved.'; $old = '';
    switch ($kind) {
      case 'qty':
        $m = (array)$req->input('qty', []); foreach ($items as $k => &$i) { $n = (int)($m[$k + 1] ?? $i['qty']); if ($n < 1) return $this->err('Quantity must be at least 1.'); $i['qty'] = $n; } unset($i);
        [$disc, $dn] = $this->autoDisc($r, $items, (string)($r->coupon ?? '')); $extra['discount_note'] = $dn; $old = 'quantities'; $msg = 'Quantity saved.'; break;
      case 'products':
        $new = []; $canPrice = MP::can(Auth::user(), 'orders', 'all');
        foreach ((array)$req->input('items', []) as $x) {
          $n = (int)($x['qty'] ?? 0); if ($n < 1) continue; $k = (int)($x['item_id'] ?? 0) - 1;
          if ($k >= 0 && isset($items[$k])) { $i = $items[$k]; $i['qty'] = $n; if ($canPrice && isset($x['price'])) $i['price'] = round((float)$x['price'], 2); $new[] = $i; continue; }
          $pid = (int)($x['product_id'] ?? 0); $p = $pid ? DB::table('products')->where('id', $pid)->first(['id', 'name', 'price']) : null; if (!$p) return $this->err('Product not found.');
          $price = (float)$p->price; $nm = $p->name; $vid = (int)($x['variant_id'] ?? 0);
          if ($vid) { $v = DB::table('product_variants')->where('id', $vid)->where('product_id', $pid)->first(); if ($v) { if ($v->price !== null) $price = (float)$v->price; $nm = mb_substr((string)($x['name'] ?? $p->name), 0, 250); } }
          $new[] = ['id'=>$pid, 'name'=>$nm, 'qty'=>$n, 'price'=>$price];
        }
        if (!$new) return $this->err('At least one product must stay in the checkout.');
        $items = $new; [$disc, $dn] = $this->autoDisc($r, $items, (string)($r->coupon ?? '')); $extra['discount_note'] = $dn; $old = 'products'; $msg = 'Products saved.'; break;
      case 'amount':
        $disc = max(0, (float)$req->input('discount_value')); $ship = max(0, (float)$req->input('delivery_charge')); $extra['discount_note'] = 'Manual by '.(Auth::user()->name ?? 'staff'); $old = 'amount'; $msg = 'Amount saved.'; break;
      case 'discount':
        $sub = 0; foreach ($items as $i) $sub += $i['price'] * $i['qty']; $v = max(0, (float)$req->input('discount_value')); $disc = $req->input('discount_type') === 'pct' ? round($sub * min(100, $v) / 100, 2) : $v;
        $extra['discount_note'] = 'Manual discount by '.(Auth::user()->name ?? 'staff'); $old = 'discount'; $msg = 'Discount saved.'; break;
      case 'coupon':
        $cp = strtoupper(trim((string)$req->input('coupon'))); [$d, $dn, $ce] = $this->autoDisc($r, $items, $cp);
        if ($cp !== '' && $ce) return $this->err('Coupon '.$cp.': '.$ce.'.');
        $disc = $d; $extra['coupon'] = $cp !== '' ? $cp : null; $extra['discount_note'] = $dn; $old = 'coupon'; $msg = $cp !== '' ? 'Coupon '.$cp.' applied.' : 'Coupon removed.'; break;
      case 'delivery': $ship = max(0, (float)$req->input('delivery_charge')); $old = 'delivery charge'; $msg = 'Delivery charge saved.'; break;
      case 'payment':
        $m = mb_substr(trim((string)$req->input('method')), 0, 20); $extra['pay_method'] = $m !== '' ? $m : null; $old = 'payment method'; $msg = 'Payment method saved.'; break;
      default: return $this->err('Unknown change.');
    }
    $before = (float)$r->total; $this->save($r, $items, $disc, $ship, $extra); $after = (float)DB::table('incomplete_orders')->where('id', $r->id)->value('total');
    $this->log((int)$r->id, 'edit_'.$kind, '৳'.number_format($before, 2), '৳'.number_format($after, 2), 'Changed '.$old.($note !== '' ? ' - '.$note : ''));
    return response()->json(['ok'=>true, 'msg'=>$msg.' New total ৳'.number_format($after).'.']);
  }
  public function qe(Request $req, $id, string $what) {
    $r = $this->load($id); if ($r->status === 'converted') return $this->err('Already converted to an order.');
    if ($what === 'customer') {
      $name = mb_substr(trim((string)$req->input('name')), 0, 120); $email = mb_substr(trim((string)$req->input('email')), 0, 120); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->err('Email is not valid.');
      $ph = $this->phone((string)$req->input('phone')); if (!$ph) return $this->err('Enter a valid Bangladeshi mobile number (01XXXXXXXXX).');
      DB::table('incomplete_orders')->where('id', $r->id)->update(['name'=>$name ?: null, 'phone'=>$ph, 'email'=>$email ?: null, 'updated_at'=>now()]);
      $this->log((int)$r->id, 'customer', trim($r->name.' '.$r->phone), trim($name.' '.$ph), 'Customer details changed'); return response()->json(['ok'=>true, 'msg'=>'Customer details saved.']);
    }
    if ($what === 'address') {
      $a = mb_substr(trim((string)$req->input('address')), 0, 500); if ($a === '') return $this->err('Address is required.'); $d = mb_substr(trim((string)$req->input('district')), 0, 80);
      DB::table('incomplete_orders')->where('id', $r->id)->update(['address'=>$a, 'district'=>$d ?: null, 'updated_at'=>now()]);
      $this->log((int)$r->id, 'address', (string)$r->address, $a, 'Address changed'); return response()->json(['ok'=>true, 'msg'=>'Address saved. The delivery charge was not recalculated.']);
    }
    if ($what === 'courier') {
      $c = (string)$req->input('courier'); if (!in_array($c, QuickEditController::COURIERS, true)) return $this->err('Choose a courier.'); $cn = mb_substr(trim((string)$req->input('consignment')), 0, 80);
      DB::table('incomplete_orders')->where('id', $r->id)->update(['courier'=>$c, 'consignment'=>$cn ?: null, 'updated_at'=>now()]);
      $this->log((int)$r->id, 'courier', (string)($r->courier ?? ''), $c, 'Courier noted (no parcel is booked for an incomplete checkout)'); return response()->json(['ok'=>true, 'msg'=>'Courier saved. No parcel is booked until the checkout becomes an order.']);
    }
    if ($what === 'payreq') {
      $amt = round((float)$req->input('amount'), 2); if ($amt <= 0) return $this->err('Enter the amount requested.'); if ($amt > (float)$r->total + 0.009) return $this->err('The amount is more than the total (৳'.number_format($r->total).').');
      $to = $this->phone((string)$req->input('mobile')); if (!$to) return $this->err('Enter a valid customer mobile number.'); $m = mb_substr(trim((string)$req->input('message')), 0, 320); if ($m === '') return $this->err('Write the message.');
      try { \App\Support\Sms::queue(\App\Support\Sms::number($to) ?: $to, $m, 'payment_request', ['incomplete_id'=>$r->id]); } catch (\Throwable $e) { return $this->err('The SMS could not be queued: '.mb_substr($e->getMessage(), 0, 120)); }
      $this->log((int)$r->id, 'payment_request', null, '৳'.number_format($amt, 2), 'Payment request sent to '.$to); return response()->json(['ok'=>true, 'msg'=>'Payment request sent to '.$to.'.']);
    }
    return $this->err('Unknown change.');
  }
  /** Status popup: Open / Recovered / Cancel (= Archived) / Convert to Order. */
  public function status(Request $req, $id) {
    $r = $this->load($id); $to = (string)$req->input('status'); $note = mb_substr(trim((string)$req->input('note')), 0, 500);
    if ($to === 'Convert to Order') return redirect('/admin/orders/new?inc='.$r->id);
    $map = ['Open'=>'open', 'New'=>'open', 'Incomplete'=>'open', 'Recovered'=>'recovered', 'Cancel'=>'archived', 'Archived'=>'archived'];
    if (!isset($map[$to])) return redirect('/admin/incomplete/'.$id)->with('bad', 'Choose Open, Recovered, Cancel or Convert to Order.');
    if ($r->status === 'converted') return redirect('/admin/incomplete/'.$id)->with('bad', 'Already converted to an order.');
    DB::table('incomplete_orders')->where('id', $id)->update(['status'=>$map[$to], 'updated_at'=>now()]);
    $this->log((int)$id, 'status', $r->status, $map[$to], 'Status: '.$r->status.' -> '.$map[$to].($note !== '' ? ' - '.$note : ''));
    return redirect('/admin/incomplete/'.$id)->with('ok', 'Status set to '.$to.'.');
  }
  public function comment(Request $req, $id) {
    $r = $this->load($id); $b = mb_substr(trim((string)$req->input('body')), 0, 2000); if ($b === '') return redirect('/admin/incomplete/'.$id)->with('bad', 'Write the note first.');
    $cat = in_array($req->input('category'), ['Internal Note', 'Customer Note'], true) ? $req->input('category') : 'Internal Note';
    $this->log((int)$r->id, 'note', null, null, $b, $cat); DB::table('incomplete_orders')->where('id', $id)->update(['note'=>mb_substr($b, 0, 500), 'updated_at'=>now()]);
    return redirect('/admin/incomplete/'.$id)->with('ok', 'Note saved.');
  }
}
