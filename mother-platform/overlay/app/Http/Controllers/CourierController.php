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
    $d = $r->validate(['env'=>'required|in:live,sandbox','client_id'=>'nullable|string|max:120','client_secret'=>'nullable|string|max:200','username'=>'nullable|string|max:150','password'=>'nullable|string|max:200','store_id'=>'nullable|integer','weight'=>'nullable|numeric|min:0.5|max:10','enabled'=>'nullable|boolean']);
    Sms::setSecret('pathao_env', $d['env']);
    foreach (['client_id','client_secret','username','password'] as $k) if (!empty($d[$k])) Sms::setSecret('pathao_'.$k, trim($d[$k]));
    if (!empty($d['store_id'])) Sms::setSecret('pathao_store_id', (string)$d['store_id']);
    if (!empty($d['weight'])) Sms::setSecret('pathao_weight', (string)$d['weight']);
    // credentials or environment changed: drop cached tokens
    foreach (['pathao_access','pathao_refresh','pathao_exp'] as $k) Sms::setSecret($k, null);
    DB::table('couriers')->updateOrInsert(['name'=>'Pathao'], ['active'=>$r->boolean('enabled', true), 'updated_at'=>now(), 'created_at'=>now()]);
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
    \App\Support\CourierSync::apply($o, $i);
    return back()->with('ok', 'Pathao status: '.($i['order_status'] ?? $i['order_status_slug'] ?? 'unknown'));
  }
  private function book(int $id): array {
    $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found'];
    $items = DB::table('order_items')->where('order_id', $id)->get();
    [$ok, $msg, $data] = Pathao::send($o, $items);
    if (!$ok) return [false, $msg];
    DB::transaction(function () use ($o, $id, $data) {
      DB::table('orders')->where('id', $id)->update(['consignment_id'=>$data['consignment_id'], 'courier'=>'Pathao', 'courier_status'=>$data['order_status'] ?? 'Pending', 'courier_fee'=>$data['delivery_fee'] ?? null, 'courier_sent_at'=>now(), 'courier_stage'=>'Pending', 'courier_cod'=>$o->total, 'settlement_status'=>'Pending', 'status'=>'Shipment', 'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'from_status'=>$o->status,'to_status'=>'Shipment','note'=>'Sent to Pathao, consignment '.$data['consignment_id'],'created_at'=>now(),'updated_at'=>now()]);
    });
    return [true, $msg];
  }
  private function audit(Request $r, string $a, string $s): void { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$s,'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]); }

  public function sync() { $n = \App\Support\CourierSync::run(60); return back()->with('ok', "Sync finished. $n parcel(s) changed status."); }
  /** Pathao pushes here; we never trust the payload, we re-read the parcel from the Pathao API. */
  public function webhook(Request $r) {
    $cid = $r->input('consignment_id'); $sec = Sms::secret('pathao_webhook_secret');
    if ($sec && !hash_equals($sec, (string)$r->header('X-PATHAO-Signature', $r->query('key', '')))) return response()->json(['ok'=>false], 403);
    if ($cid && ($o = DB::table('orders')->where('consignment_id', (string)$cid)->first())) { $i = Pathao::info($o->consignment_id); if ($i) \App\Support\CourierSync::apply($o, $i); }
    return response()->json(['ok'=>true], 202);
  }
  public function settle(Request $r) {
    $d = $r->validate(['ids'=>'required|array|max:500', 'ids.*'=>'integer', 'ref'=>'nullable|string|max:120']);
    $n = DB::table('orders')->whereIn('id', $d['ids'])->where('courier_stage', 'Delivered')->update(['settlement_status'=>'Paid', 'settlement_date'=>now(), 'settlement_ref'=>$d['ref'] ?? null, 'updated_at'=>now()]);
    $this->audit($r, 'courier.settle', "$n parcels marked paid".(!empty($d['ref']) ? ' ref '.$d['ref'] : ''));
    return back()->with('ok', "$n delivered parcel(s) marked as paid / settled.");
  }
  private function dash(Request $r): array {
    \App\Support\CourierSync::auto();
    $q = DB::table('orders as o')->leftJoin('stores as s', 's.id', '=', 'o.store_id')->whereNotNull('o.consignment_id')
      ->when($r->filled('courier'), fn($x) => $x->where('o.courier', $r->courier))->when($r->filled('store'), fn($x) => $x->where('o.store_id', (int)$r->store))
      ->when($r->filled('stage'), fn($x) => $x->where('o.courier_stage', $r->stage))->when($r->filled('from'), fn($x) => $x->where('o.courier_sent_at', '>=', $r->from))->when($r->filled('to'), fn($x) => $x->where('o.courier_sent_at', '<=', $r->to.' 23:59:59'));
    $rows = (clone $q)->orderByDesc('o.courier_sent_at')->select('o.*', 's.name as store_name')->limit(1000)->get();
    $by = fn($st) => $rows->where('courier_stage', $st)->count(); $del = $rows->where('courier_stage', 'Delivered');
    $cards = [['Total parcels', $rows->count()], ['Pickup requested', $by('Pickup Requested') + $by('Pending')], ['In transit', $by('In Transit') + $by('Picked Up')], ['At hub', $by('At Hub')], ['Out for delivery', $by('Out for Delivery')], ['Delivered', $del->count()], ['Returned', $by('Returned')], ['Failed', $by('Failed Delivery') + $by('Hold')], ['Cancelled', $by('Cancelled')],
      ['COD collected', '৳'.number_format($del->sum('collected_amount'))], ['Payment pending', '৳'.number_format($del->where('settlement_status', '!=', 'Paid')->sum(fn($o) => (float)$o->collected_amount - (float)$o->courier_fee))], ['Payment received', '৳'.number_format($del->where('settlement_status', 'Paid')->sum(fn($o) => (float)$o->collected_amount - (float)$o->courier_fee))]];
    $perf = $rows->groupBy(fn($o) => $o->courier ?: 'Pathao')->map(function ($g, $name) {
      $d = $g->where('courier_stage', 'Delivered'); $ret = $g->where('courier_stage', 'Returned')->count(); $done = $d->count() + $ret;
      $avg = $d->filter(fn($o) => $o->courier_delivered_at && $o->courier_sent_at)->avg(fn($o) => (strtotime($o->courier_delivered_at) - strtotime($o->courier_sent_at)) / 86400);
      return ['Courier'=>$name, 'Total Parcel'=>$g->count(), 'Delivered'=>$d->count(), 'Returned'=>$ret, 'Success Rate'=>$done ? round($d->count() / $done * 100).'%' : '—', 'Avg Delivery Time'=>$avg !== null ? round($avg, 1).' days' : '—', 'COD Collected'=>'৳'.number_format($d->sum('collected_amount')), 'Pending Payment'=>'৳'.number_format($d->where('settlement_status', '!=', 'Paid')->sum(fn($o) => (float)$o->collected_amount - (float)$o->courier_fee))];
    })->values();
    return [$rows, $cards, $perf];
  }
  public function dashboard(Request $r) {
    [$rows, $cards, $perf] = $this->dash($r);
    return view('courier_dashboard', ['rows'=>$rows->take(200), 'cards'=>$cards, 'perf'=>$perf, 'stages'=>\App\Support\CourierSync::STAGES, 'couriers'=>DB::table('orders')->whereNotNull('consignment_id')->distinct()->pluck('courier'), 'stores'=>DB::table('stores')->orderBy('name')->get(['id','name']), 'last'=>\Illuminate\Support\Facades\Cache::get('courier.last_sync'), 'ready'=>Pathao::configured()]);
  }
  public function export(Request $r, $fmt) {
    [$rows, , $perf] = $this->dash($r);
    $head = ['Invoice','Customer','Phone','Store','Courier','Consignment','Stage','Courier status','COD','Delivery fee','Return fee','Collected','Settlement','Settled on','Reference','Sent','Delivered'];
    $data = $rows->map(fn($o) => [$o->invoice,$o->customer_name,$o->phone,$o->store_name,$o->courier,$o->consignment_id,$o->courier_stage,$o->courier_status,$o->courier_cod,$o->courier_fee,$o->courier_return_fee,$o->collected_amount,$o->settlement_status,$o->settlement_date,$o->settlement_ref,$o->courier_sent_at,$o->courier_delivered_at])->all();
    $title = 'Courier parcels';
    if ($fmt === 'csv') return response()->streamDownload(function () use ($head, $data) { $f = fopen('php://output', 'w'); fputcsv($f, $head); foreach ($data as $d) fputcsv($f, $d); fclose($f); }, 'courier-parcels.csv', ['Content-Type'=>'text/csv']);
    $h = '<meta charset="utf-8"><h2>'.$title.'</h2><table border="1" cellpadding="4" style="border-collapse:collapse;font:12px sans-serif"><tr>'.collect($head)->map(fn($c) => '<th style="background:#FFF1E8">'.e($c).'</th>')->implode('').'</tr>'.collect($data)->map(fn($d) => '<tr>'.collect($d)->map(fn($c) => '<td>'.e((string)$c).'</td>')->implode('').'</tr>')->implode('').'</table>';
    if ($fmt === 'xls') return response($h, 200, ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8', 'Content-Disposition'=>'attachment; filename="courier-parcels.xls"']);
    if ($fmt === 'doc') return response($h, 200, ['Content-Type'=>'application/msword; charset=utf-8', 'Content-Disposition'=>'attachment; filename="courier-parcels.doc"']);
    return response($h.'<script>window.onload=function(){window.print()}</script>');
  }
}
