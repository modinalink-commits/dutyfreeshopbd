<?php
namespace App\Http\Controllers;
use App\Support\Carrybee;
use App\Support\CourierSync;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarrybeeController extends Controller {
  public function show() {
    return view('carrybee_setup', ['env' => Carrybee::env(), 'ready' => Carrybee::configured(), 'hasSecret' => (bool)Sms::secret('carrybee_client_secret'), 'cid' => Sms::secret('carrybee_client_id'), 'ctx' => Sms::secret('carrybee_client_context'), 'storeId' => Carrybee::storeId(), 'weight' => Sms::secret('carrybee_weight') ?: '0.5', 'hasHook' => (bool)Sms::secret('carrybee_webhook_secret'), 'stores' => session('stores', []),
      'sent' => DB::table('orders')->where('courier', 'Carrybee')->whereNotNull('consignment_id')->count(), 'recent' => DB::table('orders')->where('courier', 'Carrybee')->whereNotNull('consignment_id')->orderByDesc('courier_sent_at')->limit(15)->get(['id', 'invoice', 'customer_name', 'consignment_id', 'courier_status', 'courier_fee', 'courier_sent_at'])]);
  }
  public function save(Request $r) {
    $d = $r->validate(['env' => 'required|in:live,sandbox', 'client_id' => 'nullable|string|max:120', 'client_secret' => 'nullable|string|max:200', 'client_context' => 'nullable|string|max:200', 'store_id' => 'nullable|string|max:60', 'weight' => 'nullable|numeric|min:0.1|max:25', 'webhook_secret' => 'nullable|string|max:200', 'enabled' => 'nullable|boolean']);
    Sms::setSecret('carrybee_env', $d['env']);
    foreach (['client_id', 'client_secret', 'client_context', 'webhook_secret'] as $k) if (!empty($d[$k])) Sms::setSecret('carrybee_' . $k, trim($d[$k]));
    if (!empty($d['store_id'])) Sms::setSecret('carrybee_store_id', trim($d['store_id']));
    if (!empty($d['weight'])) Sms::setSecret('carrybee_weight', (string)$d['weight']);
    DB::table('couriers')->updateOrInsert(['name' => 'Carrybee'], ['active' => $r->boolean('enabled', true), 'updated_at' => now(), 'created_at' => now()]);
    $this->audit($r, 'courier.settings', 'Carrybee settings saved (' . $d['env'] . ')');
    return back()->with('ok', 'Carrybee settings saved. Now press "Connect & load stores".');
  }
  public function connect() {
    if (!Carrybee::configured()) return back()->with('bad', 'Save Client ID, Client Secret and Client Context first.');
    $r = Carrybee::call('GET', '/api/v2/stores');
    if ($r['code'] === 401 || $r['code'] === 403) return back()->with('bad', 'Carrybee refused the credentials. Check the three values and that live/sandbox matches.');
    if ($r['code'] !== 200) return back()->with('bad', ($r['err'] ?? 'Carrybee did not answer') . ' (HTTP ' . $r['code'] . ')');
    $stores = $r['json']['data']['stores'] ?? [];
    if (!$stores) return back()->with('ok', 'Connected to Carrybee, but no pickup store exists yet. Create one in the Carrybee merchant panel (Stores).');
    if (!Carrybee::storeId()) { $def = collect($stores)->firstWhere('is_default_pickup_store', true) ?: $stores[0]; Sms::setSecret('carrybee_store_id', (string)$def['id']); }
    return back()->with('ok', 'Connected to Carrybee. ' . count($stores) . ' store(s) found.')->with('stores', $stores);
  }
  public function sendOne($id) { [$ok, $msg] = $this->book((int)$id); return back()->with($ok ? 'ok' : 'bad', $msg); }
  public function sendBulk(Request $r) {
    $d = $r->validate(['ids' => 'required|array|max:100', 'ids.*' => 'integer']); $okN = 0; $fail = [];
    foreach ($d['ids'] as $id) { [$ok, $msg] = $this->book((int)$id); if ($ok) $okN++; else $fail[] = '#' . $id . ': ' . $msg; }
    return back()->with($fail ? 'bad' : 'ok', "$okN order(s) sent to Carrybee." . ($fail ? ' Not sent: ' . implode(' | ', array_slice($fail, 0, 5)) : ''));
  }
  public function refresh($id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->consignment_id && $o->courier === 'Carrybee', 404);
    $i = Carrybee::info($o->consignment_id); if (!$i) return back()->with('bad', 'Could not read the status from Carrybee.');
    CourierSync::apply($o, $i);
    return back()->with('ok', 'Carrybee status: ' . $i['order_status']);
  }
  public function book(int $id): array {
    if (($o0 = DB::table('orders')->find($id)) && ($db = \App\Support\DupGuard::block($o0, 'Shipment'))) return [false, $db];
    if ($sm = \App\Support\StockGuard::shipBlock($id)) return [false, $sm];
    $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found', []];
    $items = DB::table('order_items')->where('order_id', $id)->get();
    [$ok, $msg, $data] = Carrybee::send($o, $items); if (!$ok) return [false, $msg];
    DB::transaction(function () use ($o, $id, $data) {
      DB::table('orders')->where('id', $id)->update(['consignment_id' => $data['consignment_id'], 'courier' => 'Carrybee', 'courier_status' => 'Pending', 'courier_fee' => $data['delivery_fee'], 'courier_sent_at' => now(), 'courier_stage' => 'Pending', 'courier_cod' => \App\Support\PaymentSettle::cod($o), 'settlement_status' => 'Pending', 'status' => 'Shipment', 'updated_at' => now()]);
      DB::table('order_status_logs')->insert(['order_id' => $id, 'user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'from_status' => $o->status, 'to_status' => 'Shipment', 'note' => 'Sent to Carrybee, consignment ' . $data['consignment_id'], 'created_at' => now(), 'updated_at' => now()]);
    });
    return [true, $msg];
  }
  /** Carrybee pushes here. We echo the secret header (required by Carrybee) and always re-read the parcel from the API instead of trusting the payload. */
  public function webhook(Request $r) {
    $sec = (string)Sms::secret('carrybee_webhook_secret'); $h = (string)$r->header('X-CB-Webhook-Integration-Header', '');
    if ($sec !== '' && !hash_equals($sec, $h)) return response()->json(['ok' => false], 403);
    $cid = $r->input('consignment_id');
    if ($cid && ($o = DB::table('orders')->where('consignment_id', (string)$cid)->where('courier', 'Carrybee')->first())) { $i = Carrybee::info($o->consignment_id); if ($i) CourierSync::apply($o, $i); }
    return response()->json(['ok' => true], 202, $h !== '' ? ['X-CB-Webhook-Integration-Header' => $h] : []);
  }
  private function audit(Request $r, string $a, string $s): void { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => $a, 'subject' => $s, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); }
}
