<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as MP;
use App\Support\OrderActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Order page "Quick Edit" popups for the non-money fields. Every change is logged to the permanent staff activity (old value, new value, who, when). */
class QuickEditController extends Controller {
  const LOCKED = ['Delivered', 'Partial Delivered', 'Returned', 'Exchange Received', 'Late Delivery'];
  const COURIERS = ['Pathao', 'Steadfast', 'RedX', 'Carrybee', 'Other'];
  private function err(string $m) { return response()->json(['ok'=>false, 'error'=>$m], 422); }
  private function load($id) { $o = DB::table('orders')->find($id); abort_unless($o, 404); return $o; }
  private function can(string $mod): bool { return MP::can(Auth::user(), $mod, 'edit'); }
  private function phone(string $p): ?string { $d = preg_replace('/\D+/', '', $p); if (str_starts_with($d, '880')) $d = '0'.substr($d, 3); elseif (strlen($d) === 10 && $d[0] === '1') $d = '0'.$d; return preg_match('/^01[3-9]\d{8}$/', $d) ? $d : null; }

  public function act(Request $r, $id, string $what) { return match ($what) { 'customer' => $this->customer($r, $id), 'address' => $this->address($r, $id), 'courier' => $this->courier($r, $id), default => $this->payreq($r, $id) }; }
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
