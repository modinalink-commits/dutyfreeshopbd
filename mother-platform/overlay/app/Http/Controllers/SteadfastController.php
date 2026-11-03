<?php
namespace App\Http\Controllers;
use App\Support\CourierSync;
use App\Support\Sms;
use App\Support\Steadfast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SteadfastController extends Controller {
  public function show() {
    $q = DB::table('orders')->where('courier', 'Steadfast')->whereNotNull('consignment_id');
    return view('steadfast_setup', ['ready' => Steadfast::configured(), 'hasHook' => (bool)Sms::secret('steadfast_webhook_secret'), 'sent' => (clone $q)->count(),
      'recent' => (clone $q)->orderByDesc('courier_sent_at')->limit(15)->get(['id', 'invoice', 'customer_name', 'consignment_id', 'courier_status', 'courier_fee', 'courier_sent_at'])]);
  }
  public function save(Request $r) {
    $d = $r->validate(['webhook_secret' => 'nullable|string|max:200', 'enabled' => 'nullable|boolean']);
    if (!empty($d['webhook_secret'])) Sms::setSecret('steadfast_webhook_secret', trim($d['webhook_secret']));
    DB::table('couriers')->updateOrInsert(['name' => 'Steadfast'], ['active' => $r->boolean('enabled', true), 'updated_at' => now(), 'created_at' => now()]);
    return back()->with('ok', 'Steadfast settings saved.');
  }
  public function connect() {
    if (!Steadfast::configured()) return back()->with('bad', 'Save the Steadfast Api-Key and Secret-Key on the Fraud Check page first.');
    $r = Steadfast::balance();
    if ($r['code'] === 200) return back()->with('ok', 'Connected to Steadfast. Current balance: ৳' . number_format((float)($r['json']['current_balance'] ?? 0)));
    if ($r['code'] === 403) return back()->with('bad', 'Steadfast answered 403: the account is not approved for API use yet (ownership / KYC review pending at Steadfast).');
    if ($r['code'] === 401) return back()->with('bad', 'Steadfast refused the keys (401). Check Api-Key and Secret-Key.');
    return back()->with('bad', ($r['err'] ?? 'Steadfast did not answer') . ' (HTTP ' . $r['code'] . ')');
  }
  public function sendOne($id) { [$ok, $msg] = $this->book((int)$id); return back()->with($ok ? 'ok' : 'bad', $msg); }
  public function refresh($id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->consignment_id && $o->courier === 'Steadfast', 404);
    $i = Steadfast::info($o->consignment_id); if (!$i) return back()->with('bad', 'Could not read the status from Steadfast.');
    CourierSync::apply($o, $i); return back()->with('ok', 'Steadfast status: ' . $i['order_status']);
  }
  public function book(int $id): array {
    $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found'];
    $items = DB::table('order_items')->where('order_id', $id)->get();
    [$ok, $msg, $data] = Steadfast::send($o, $items); if (!$ok) return [false, $msg];
    DB::transaction(function () use ($o, $id, $data) {
      DB::table('orders')->where('id', $id)->update(['consignment_id' => $data['consignment_id'], 'tracking_code' => $data['tracking_code'] ?? $o->tracking_code, 'courier' => 'Steadfast', 'courier_status' => 'Pending', 'courier_sent_at' => now(), 'courier_stage' => 'Pending', 'courier_cod' => \App\Support\PaymentSettle::cod($o), 'settlement_status' => 'Pending', 'status' => 'Shipment', 'updated_at' => now()]);
      DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'from_status' => $o->status, 'to_status' => 'Shipment', 'note' => 'Sent to Steadfast, consignment ' . $data['consignment_id'], 'created_at' => now(), 'updated_at' => now()]);
    });
    return [true, $msg];
  }
  /** Steadfast posts here with a Bearer token (or ?token=). The payload is never trusted: the parcel is re-read from the API. */
  public function webhook(Request $r) {
    $sec = (string)Sms::secret('steadfast_webhook_secret');
    if ($sec !== '') { $got = preg_replace('/^bearer\s+/i', '', (string)$r->header('Authorization', $r->query('token', ''))); if (!hash_equals($sec, (string)$got)) return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401); }
    $cid = $r->input('consignment_id');
    if ($cid && ($o = DB::table('orders')->where('consignment_id', (string)$cid)->where('courier', 'Steadfast')->first())) { $i = Steadfast::info($o->consignment_id); if ($i) CourierSync::apply($o, $i); }
    return response()->json(['status' => 'success', 'message' => 'Webhook received successfully.'], 200);
  }
}
