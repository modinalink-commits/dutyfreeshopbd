<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as MP;
use App\Support\OrderActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Order page "Quick Edit" popups for the non-money fields. Every change is logged to the permanent staff activity (old value, new value, who, when). */
class QuickEditController extends Controller {
  const LOCKED = ['Delivered', 'Partial Delivered', 'Returned', 'Exchange Received', 'Late Delivery', 'Return Pending'];
  const COURIERS = ['Pathao', 'Steadfast', 'RedX', 'Carrybee', 'Other'];
  private function err(string $m) { return response()->json(['ok'=>false, 'error'=>$m], 422); }
  private function load($id) { $o = DB::table('orders')->find($id); abort_unless($o, 404); return $o; }
  private function can(string $mod): bool { return MP::can(Auth::user(), $mod, 'edit'); }
  private function phone(string $p): ?string { $d = preg_replace('/\D+/', '', $p); if (str_starts_with($d, '880')) $d = '0'.substr($d, 3); elseif (strlen($d) === 10 && $d[0] === '1') $d = '0'.$d; return preg_match('/^01[3-9]\d{8}$/', $d) ? $d : null; }

  public function act(Request $r, $id, string $what) { return match ($what) { 'customer' => $this->customer($r, $id), 'address' => $this->address($r, $id), 'courier' => $this->courier($r, $id), 'duplicate' => $this->duplicate($r, $id), default => $this->payreq($r, $id) }; }
  /** Mark an order as a duplicate of another (never deletes): links it to the original, moves it to Cancel so the existing Cancel rules drop it from stock, courier, revenue and customer value, and writes the staff audit log. */
  public function duplicate(Request $r, $id) {
    $o = $this->load($id); if (!$this->can('orders')) return $this->err('Your role does not allow editing orders.');
    if ($r->input('undo')) {
      if (empty($o->duplicate_of_order_id)) return $this->err('This order is not marked as a duplicate.');
      $orig = DB::table('orders')->where('id', $o->duplicate_of_order_id)->value('invoice');
      DB::transaction(function () use ($o, $orig) {
        DB::table('orders')->where('id', $o->id)->update(['duplicate_of_order_id'=>null, 'status'=>'New', 'updated_at'=>now()]);
        DB::table('order_status_logs')->insert(['order_id'=>$o->id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'from_status'=>$o->status, 'to_status'=>'New', 'note'=>'Not a duplicate (was linked to '.$orig.')', 'created_at'=>now(), 'updated_at'=>now()]);
        OrderActivity::log((int)$o->id, 'duplicate_unmarked', 'Duplicate of '.$orig, null, 'Duplicate mark removed; order restored to New');
      });
      return response()->json(['ok'=>true, 'msg'=>'Duplicate mark removed. The order is back in New.']);
    }
    if (!empty($o->duplicate_of_order_id)) return $this->err('This order is already marked as a duplicate.');
    if (in_array($o->status, self::LOCKED)) return $this->err('An order in "'.$o->status.'" cannot be marked as a duplicate.');
    if (!empty($o->consignment_id)) return $this->err('A courier parcel already exists for this order. Cancel the parcel first, then mark the duplicate.');
    $inv = strtoupper(trim((string)$r->input('original'))); if ($inv === '') return $this->err('Enter the invoice number of the original order.');
    $orig = DB::table('orders')->whereRaw('upper(invoice) = ?', [$inv])->first(); if (!$orig) return $this->err('No order found with invoice '.$inv.'.');
    if ((int)$orig->id === (int)$o->id) return $this->err('An order cannot be a duplicate of itself.');
    if (!empty($orig->duplicate_of_order_id)) return $this->err($orig->invoice.' is itself a duplicate. Use the real original.');
    if ($orig->status === 'Cancel') return $this->err('The original order '.$orig->invoice.' is cancelled; pick the order that is actually being processed.');
    $note = mb_substr(trim((string)$r->input('note')), 0, 300);
    DB::transaction(function () use ($o, $orig, $note) {
      DB::table('orders')->where('id', $o->id)->update(['duplicate_of_order_id'=>$orig->id, 'status'=>'Cancel', 'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$o->id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'from_status'=>$o->status, 'to_status'=>'Cancel', 'note'=>trim('Duplicate of '.$orig->invoice.($note !== '' ? ' — '.$note : '')), 'created_at'=>now(), 'updated_at'=>now()]);
      OrderActivity::log((int)$o->id, 'marked_duplicate', (string)$o->status, 'Duplicate', 'Order marked as Duplicate — Original: '.$orig->invoice.($note !== '' ? ' ('.$note.')' : ''), ['original_id'=>(int)$orig->id, 'original_invoice'=>$orig->invoice]);
      OrderActivity::log((int)$orig->id, 'duplicate_linked', null, $o->invoice, $o->invoice.' was marked as a duplicate of this order');
    });
    return response()->json(['ok'=>true, 'msg'=>$o->invoice.' marked as Duplicate of '.$orig->invoice.'. It is excluded from stock, courier, SMS and revenue; nothing was deleted.']);
  }
  public function customer(Request $r, $id) {
    $o = $this->load($id); if (!$this->can('orders')) return $this->err('Your role does not allow editing orders.');
    if (in_array($o->status, self::LOCKED)) return $this->err('An order in "'.$o->status.'" cannot be edited.');
    $name = mb_substr(trim((string)$r->input('name')), 0, 120); if ($name === '') return $this->err('Customer name is required.');
    $email = mb_substr(trim((string)$r->input('email')), 0, 160); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return $this->err('Email is not valid.');
    $phone = $this->phone((string)$r->input('phone')); if (!$phone) return $this->err('Enter a valid Bangladeshi mobile number (01XXXXXXXXX).');
    if ($phone !== (string)$o->phone && !MP::can(Auth::user(), 'orders', 'all')) return $this->err('Changing the mobile number needs full order permission (it moves the order between customer histories).');
    $upd = []; $log = [];
    if ($name !== (string)$o->customer_name) { $upd['customer_name'] = $name; $log[] = ['name', (string)$o->customer_name, $name]; }
    if ($phone !== (string)$o->phone) { $upd['phone'] = $phone; $log[] = ['mobile', (string)$o->phone, $phone]; }
    if ($email !== (string)($o->email ?? '')) { $upd['email'] = $email !== '' ? $email : null; $log[] = ['email', (string)($o->email ?? ''), $email]; }
    if (!$upd) return $this->err('Nothing was changed.');
    DB::table('orders')->where('id', $o->id)->update($upd + ['updated_at'=>now()]);
    foreach ($log as [$f, $a, $b]) OrderActivity::log((int)$o->id, 'customer_'.$f.'_changed', $a, $b, 'Customer '.$f.' changed');
    return response()->json(['ok'=>true, 'msg'=>'Customer details saved.'.(!empty($o->consignment_id) ? ' The courier parcel already exists — update it there if needed.' : '')]);
  }
  public function address(Request $r, $id) {
    $o = $this->load($id); if (!$this->can('orders')) return $this->err('Your role does not allow editing orders.');
    if (in_array($o->status, self::LOCKED)) return $this->err('An order in "'.$o->status.'" cannot be edited.');
    $addr = mb_substr(trim((string)$r->input('address')), 0, 500); if ($addr === '') return $this->err('Address is required.');
    $dist = mb_substr(trim((string)$r->input('district')), 0, 120);
    $upd = []; $log = [];
    if ($addr !== (string)$o->address) { $upd['address'] = $addr; $log[] = ['address', (string)$o->address, $addr]; }
    if ($dist !== (string)$o->district) { $upd['district'] = $dist; $log[] = ['district', (string)$o->district, $dist]; }
    if (!$upd) return $this->err('Nothing was changed.');
    DB::table('orders')->where('id', $o->id)->update($upd + ['updated_at'=>now()]);
    foreach ($log as [$f, $a, $b]) OrderActivity::log((int)$o->id, 'delivery_'.$f.'_changed', mb_substr($a, 0, 250), mb_substr($b, 0, 250), 'Delivery '.$f.' changed');
    return response()->json(['ok'=>true, 'msg'=>'Address saved. The delivery charge was not recalculated — use Quick Edit → Delivery Charge if the zone changed.'.(!empty($o->consignment_id) ? ' The courier parcel already exists — update it there if needed.' : '')]);
  }
  public function courier(Request $r, $id) {
    $o = $this->load($id); if (!$this->can('couriers')) return $this->err('Your role does not allow changing the courier.');
    $c = (string)$r->input('courier'); if (!in_array($c, self::COURIERS, true)) return $this->err('Choose a courier.');
    $cn = mb_substr(trim((string)$r->input('consignment')), 0, 80);
    $upd = []; $oldC = (string)($o->courier ?? ''); $oldN = (string)($o->consignment_id ?? '');
    if ($c !== $oldC) $upd['courier'] = $c;
    if ($cn !== $oldN) $upd['consignment_id'] = $cn !== '' ? $cn : null;
    if (!$upd) return $this->err('Nothing was changed.');
    DB::table('orders')->where('id', $o->id)->update($upd + ['updated_at'=>now()]);
    if (isset($upd['courier'])) OrderActivity::log((int)$o->id, 'courier_changed', $oldC ?: '—', $c, 'Courier changed');
    if (array_key_exists('consignment_id', $upd)) OrderActivity::log((int)$o->id, 'consignment_changed', $oldN ?: '—', $cn ?: '—', 'Tracking / consignment changed');
    return response()->json(['ok'=>true, 'msg'=>'Courier saved. Note: this only records the change here — it does not rebook the parcel with the courier.']);
  }
  /** Sends the customer a payment request SMS and records it against the invoice (staff activity). */
  public function payreq(Request $r, $id) {
    $o = $this->load($id); if (!$this->can('orders')) return $this->err('Your role does not allow this.');
    $amt = round((float)$r->input('amount'), 2); $due = round((float)$o->total - (float)($o->paid_total ?? 0), 2);
    if ($amt <= 0) return $this->err('Enter the amount requested.'); if ($amt > $due + 0.009) return $this->err('The amount is more than the due (৳'.number_format($due).').');
    $method = mb_substr(trim((string)$r->input('method')), 0, 60); $msg = mb_substr(trim((string)$r->input('message')), 0, 320);
    $to = $this->phone((string)$r->input('mobile')); if (!$to) return $this->err('Enter a valid customer mobile number.'); if ($msg === '') return $this->err('Write the message.');
    try { \App\Support\Sms::queue(\App\Support\Sms::number($to) ?: $to, $msg, 'payment_request', ['order_id'=>$o->id]); } catch (\Throwable $e) { return $this->err('The SMS could not be queued: '.mb_substr($e->getMessage(), 0, 120)); }
    OrderActivity::log((int)$o->id, 'payment_request_sent', null, '৳'.number_format($amt, 2), 'Payment request via '.($method ?: 'SMS').' to '.$to, ['amount'=>$amt, 'method'=>$method]);
    return response()->json(['ok'=>true, 'msg'=>'Payment request sent to '.$to.' and saved on this invoice.']);
  }
}
