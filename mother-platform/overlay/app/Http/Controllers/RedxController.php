<?php
namespace App\Http\Controllers;
use App\Support\CourierSync;
use App\Support\Redx;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedxController extends Controller {
  public function show() {
    $q = DB::table('orders')->where('courier', 'RedX')->whereNotNull('consignment_id');
    return view('redx_setup', ['env' => Redx::env(), 'ready' => Redx::configured(), 'storeId' => Redx::storeId(), 'weight' => Sms::secret('redx_weight') ?: '0.5', 'hasHook' => (bool)Sms::secret('redx_webhook_secret'), 'stores' => session('stores', []),
      'sent' => (clone $q)->count(), 'recent' => (clone $q)->orderByDesc('courier_sent_at')->limit(15)->get(['id', 'invoice', 'customer_name', 'consignment_id', 'courier_status', 'courier_fee', 'courier_sent_at'])]);
  }
  public function save(Request $r) {
    $d = $r->validate(['env' => 'required|in:live,sandbox', 'token' => 'nullable|string|max:2000', 'store_id' => 'nullable|string|max:60', 'weight' => 'nullable|numeric|min:0.1|max:25', 'webhook_secret' => 'nullable|string|max:200', 'enabled' => 'nullable|boolean']);
    Sms::setSecret('redx_env', $d['env']);
    foreach (['token', 'webhook_secret'] as $k) if (!empty($d[$k])) Sms::setSecret('redx_' . $k, trim($d[$k]));
    if (!empty($d['store_id'])) Sms::setSecret('redx_store_id', trim($d['store_id']));
    if (!empty($d['weight'])) Sms::setSecret('redx_weight', (string)$d['weight']);
    DB::table('couriers')->updateOrInsert(['name' => 'RedX'], ['active' => $r->boolean('enabled', true), 'updated_at' => now(), 'created_at' => now()]);
    DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'courier.settings', 'subject' => 'RedX settings saved (' . $d['env'] . ')', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    return back()->with('ok', 'RedX settings saved. Now press "Connect & load stores".');
  }
  public function connect() {
    if (!Redx::configured()) return back()->with('bad', 'Save the RedX token first.');
    $r = Redx::call('GET', '/pickup/stores');
    if (in_array($r['code'], [401, 403], true)) return back()->with('bad', 'RedX refused the token (HTTP '.$r['code'].', mode '.strtoupper(Redx::env()).', token length '.strlen(Redx::token()).'). RedX says: '.mb_substr(json_encode($r['json'], JSON_UNESCAPED_UNICODE), 0, 200).'. Use the token for the same mode (Production token with Live).');
    if ($r['code'] !== 200) return back()->with('bad', ($r['err'] ?? 'RedX did not answer') . ' (HTTP ' . $r['code'] . ')');
    $stores = $r['json']['pickup_stores'] ?? [];
    if (!$stores) return back()->with('ok', 'Connected to RedX, but no pickup store exists yet. Create one in the RedX panel (My Shops).');
    if (!Redx::storeId()) Sms::setSecret('redx_store_id', (string)$stores[0]['id']);
    return back()->with('ok', 'Connected to RedX. ' . count($stores) . ' pickup store(s) found.')->with('stores', $stores);
  }
  public function sendOne($id) { [$ok, $msg] = $this->book((int)$id); return back()->with($ok ? 'ok' : 'bad', $msg); }
  public function refresh($id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->consignment_id && $o->courier === 'RedX', 404);
    $i = Redx::info($o->consignment_id); if (!$i) return back()->with('bad', 'Could not read the status from RedX.');
    CourierSync::apply($o, $i); return back()->with('ok', 'RedX status: ' . $i['order_status']);
  }
  public function book(int $id): array {
    $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found'];
    $items = DB::table('order_items')->where('order_id', $id)->get();
    [$ok, $msg, $data] = Redx::send($o, $items); if (!$ok) return [false, $msg];
    DB::transaction(function () use ($o, $id, $data) {
      DB::table('orders')->where('id', $id)->update(['consignment_id' => $data['consignment_id'], 'courier' => 'RedX', 'courier_status' => 'Pending', 'courier_sent_at' => now(), 'courier_stage' => 'Pending', 'courier_cod' => \App\Support\PaymentSettle::cod($o), 'settlement_status' => 'Pending', 'status' => 'Shipment', 'updated_at' => now()]);
      DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'from_status' => $o->status, 'to_status' => 'Shipment', 'note' => 'Sent to RedX, tracking ' . $data['consignment_id'], 'created_at' => now(), 'updated_at' => now()]);
    });
    return [true, $msg];
  }
  /** RedX posts here (token in the query string). The payload is never trusted: the parcel is re-read from the API. */
  public function webhook(Request $r) {
    $sec = (string)Sms::secret('redx_webhook_secret');
    if ($sec !== '' && !hash_equals($sec, (string)$r->query('token', ''))) return response()->json(['ok' => false], 403);
    $cid = $r->input('tracking_number');
    if ($cid && ($o = DB::table('orders')->where('consignment_id', (string)$cid)->where('courier', 'RedX')->first())) { $i = Redx::info($o->consignment_id); if ($i) CourierSync::apply($o, $i); }
    return response()->json(['ok' => true], 200);
  }
}
