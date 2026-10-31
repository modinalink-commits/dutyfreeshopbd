<?php
namespace App\Http\Controllers;
use App\Support\Pathao;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CourierController extends Controller {
  public function show() {
    $stores = session('stores', []);
    return view('courier_setup', ['env'=>Pathao::env(), 'ready'=>Pathao::configured(), 'hasSecret'=>(bool)Sms::secret('pathao_client_secret'), 'hasPass'=>(bool)Sms::secret('pathao_password'), 'cid'=>Sms::secret('pathao_client_id'), 'user'=>Sms::secret('pathao_username'), 'storeId'=>Pathao::storeId(), 'weight'=>Sms::secret('pathao_weight') ?: '0.5', 'stores'=>$stores,
      'sent'=>DB::table('orders')->whereNotNull('consignment_id')->count(), 'recent'=>DB::table('orders')->whereNotNull('consignment_id')->orderByDesc('courier_sent_at')->limit(15)->get(['id','invoice','customer_name','consignment_id','courier_status','courier_fee','courier_sent_at'])]);
  }
  public function save(Request $r) {
    $d = $r->validate(['env'=>'required|in:live,sandbox','client_id'=>'nullable|string|max:120','client_secret'=>'nullable|string|max:200','username'=>'nullable|string|max:150','password'=>'nullable|string|max:200','store_id'=>'nullable|integer','weight'=>'nullable|numeric|min:0.5|max:10']);
    Sms::setSecret('pathao_env', $d['env']);
    foreach (['client_id','client_secret','username','password'] as $k) if (!empty($d[$k])) Sms::setSecret('pathao_'.$k, trim($d[$k]));
    if (!empty($d['store_id'])) Sms::setSecret('pathao_store_id', (string)$d['store_id']);
    if (!empty($d['weight'])) Sms::setSecret('pathao_weight', (string)$d['weight']);
    // credentials or environment changed: drop cached tokens
    foreach (['pathao_access','pathao_refresh','pathao_exp'] as $k) Sms::setSecret($k, null);
    $this->audit($r, 'courier.settings', 'Pathao settings saved ('.$d['env'].')');
    return back()->with('ok', 'Pathao settings saved. Now press "Connect & load stores".');
  }
  public function connect(Request $r) {
    if (!Pathao::configured()) return back()->with('bad', 'Save Client ID, Client Secret, email and password first.');
    if (!Pathao::token(true)) return back()->with('bad', 'Pathao refused the login. Check the four values and that live/sandbox matches the credentials.');
    $stores = Pathao::stores();
    if (!$stores) return back()->with('ok', 'Connected to Pathao, but no pickup store exists yet. Create one in the Pathao merchant panel (Stores); approval takes about an hour.');
    if (!Pathao::storeId()) { $def = collect($stores)->firstWhere('is_default_store', true) ?: $stores[0]; Sms::setSecret('pathao_store_id', (string)$def['store_id']); }
    return back()->with('ok', 'Connected to Pathao. '.count($stores).' store(s) found.')->with('stores', $stores);
  }
  public function sendOne(Request $r, $id) {
    [$ok, $msg] = $this->book($id);
    return back()->with($ok ? 'ok' : 'bad', $msg);
  }
  public function sendBulk(Request $r) {
    $d = $r->validate(['ids'=>'required|array|max:100', 'ids.*'=>'integer']);
    $okN = 0; $fail = [];
    foreach ($d['ids'] as $id) { [$ok, $msg] = $this->book((int)$id); if ($ok) $okN++; else $fail[] = '#'.$id.': '.$msg; }
    $m = "$okN order(s) sent to Pathao.".($fail ? ' Not sent: '.implode(' | ', array_slice($fail, 0, 5)) : '');
    return back()->with($fail ? 'bad' : 'ok', $m);
  }
  public function refresh(Request $r, $id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->consignment_id, 404);
    $i = Pathao::info($o->consignment_id);
    if (!$i) return back()->with('bad', 'Could not read the status from Pathao.');
    DB::table('orders')->where('id', $id)->update(['courier_status'=>$i['order_status'] ?? $i['order_status_slug'] ?? null, 'updated_at'=>now()]);
    return back()->with('ok', 'Pathao status: '.($i['order_status'] ?? $i['order_status_slug'] ?? 'unknown'));
  }
  private function book(int $id): array {
    $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found'];
    $items = DB::table('order_items')->where('order_id', $id)->get();
    [$ok, $msg, $data] = Pathao::send($o, $items);
    if (!$ok) return [false, $msg];
    DB::transaction(function () use ($o, $id, $data) {
      DB::table('orders')->where('id', $id)->update(['consignment_id'=>$data['consignment_id'], 'courier'=>'Pathao', 'courier_status'=>$data['order_status'] ?? 'Pending', 'courier_fee'=>$data['delivery_fee'] ?? null, 'courier_sent_at'=>now(), 'status'=>'Shipment', 'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'from_status'=>$o->status,'to_status'=>'Shipment','note'=>'Sent to Pathao, consignment '.$data['consignment_id'],'created_at'=>now(),'updated_at'=>now()]);
    });
    return [true, $msg];
  }
  private function audit(Request $r, string $a, string $s): void { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$s,'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]); }
}
