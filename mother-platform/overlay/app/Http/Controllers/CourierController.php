<?php
namespace App\Http\Controllers;
use App\Support\Pathao;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CourierController extends Controller {
  public function show() {
    $stores = session('stores', []);
    return view('courier_setup', ['env'=>Pathao::env(), 'ready'=>Pathao::configured(), 'hasSecret'=>(bool)Sms::secret('pathao_client_secret'), 'hasHook'=>(bool)Sms::secret('pathao_webhook_secret'), 'hasPass'=>(bool)Sms::secret('pathao_password'), 'cid'=>Sms::secret('pathao_client_id'), 'user'=>Sms::secret('pathao_username'), 'storeId'=>Pathao::storeId(), 'weight'=>Sms::secret('pathao_weight') ?: '0.5', 'stores'=>$stores,
      'sent'=>DB::table('orders')->whereNotNull('consignment_id')->count(), 'recent'=>DB::table('orders')->whereNotNull('consignment_id')->orderByDesc('courier_sent_at')->limit(15)->get(['id','invoice','customer_name','consignment_id','courier_status','courier_fee','courier_sent_at'])]);
  }
  public function save(Request $r) {
    $d = $r->validate(['env'=>'required|in:live,sandbox','client_id'=>'nullable|string|max:120','client_secret'=>'nullable|string|max:200','username'=>'nullable|string|max:150','password'=>'nullable|string|max:200','store_id'=>'nullable|integer','weight'=>'nullable|numeric|min:0.5|max:10','webhook_secret'=>'nullable|string|max:200','enabled'=>'nullable|boolean']);
    Sms::setSecret('pathao_env', $d['env']);
    foreach (['client_id','client_secret','username','password'] as $k) if (!empty($d[$k])) Sms::setSecret('pathao_'.$k, trim($d[$k]));
    if (!empty($d['store_id'])) Sms::setSecret('pathao_store_id', (string)$d['store_id']);
    if (!empty($d['weight'])) Sms::setSecret('pathao_weight', (string)$d['weight']);
    if (!empty($d['webhook_secret'])) Sms::setSecret('pathao_webhook_secret', trim($d['webhook_secret']));
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
    @ignore_user_abort(true); @set_time_limit(240);
    $d = $r->validate(['ids'=>'required|array|max:100', 'ids.*'=>'integer']);
    $okN = 0; $fail = [];
    foreach ($d['ids'] as $id) { [$ok, $msg] = $this->book((int)$id); if ($ok) $okN++; else $fail[] = '#'.$id.': '.$msg; }
    $m = "$okN order(s) sent to Pathao.".($fail ? ' Not sent: '.implode(' | ', array_slice($fail, 0, 12)) : '');
    return back()->with($fail ? 'bad' : 'ok', $m);
  }
  public function refresh(Request $r, $id) {
    $o = DB::table('orders')->find($id); abort_unless($o && $o->consignment_id, 404);
    $i = Pathao::info($o->consignment_id);
    if (!$i) return back()->with('bad', 'Could not read the status from Pathao.');
    \App\Support\CourierSync::apply($o, $i);
    return back()->with('ok', 'Pathao status: '.($i['order_status'] ?? $i['order_status_slug'] ?? 'unknown'));
  }
  /** Never lets an exception escape: any failure comes back as a readable message instead of a 500/502 page. */
  public function book(int $id): array {
    @ignore_user_abort(true); @set_time_limit(90);
    try { return $this->bookRaw($id); }
    catch (\Throwable $e) { report($e); return [false, '⚠ Could not send this order to the courier right now ('.mb_substr($e->getMessage(), 0, 140).'). Nothing was changed — please try again or check the Pathao panel.']; }
  }
  private function bookRaw(int $id): array {
    if ($sm = \App\Support\StockGuard::shipBlock($id)) return [false, $sm];
    $o = DB::table('orders')->find($id); if (!$o) return [false, 'Order not found'];
    $items = DB::table('order_items')->where('order_id', $id)->get();
    [$ok, $msg, $data] = Pathao::send($o, $items);
    if (!$ok) return [false, $msg];
    DB::transaction(function () use ($o, $id, $data) {
      DB::table('orders')->where('id', $id)->update(['consignment_id'=>$data['consignment_id'], 'courier'=>'Pathao', 'courier_status'=>$data['order_status'] ?? 'Pending', 'courier_fee'=>$data['delivery_fee'] ?? null, 'courier_sent_at'=>now(), 'courier_stage'=>'Pending', 'courier_cod'=>\App\Support\PaymentSettle::cod($o), 'settlement_status'=>'Pending', 'status'=>'Shipment', 'updated_at'=>now()]);
      DB::table('order_status_logs')->insert(['order_id'=>$id,'user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'from_status'=>$o->status,'to_status'=>'Shipment','note'=>'Sent to Pathao, consignment '.$data['consignment_id'],'created_at'=>now(),'updated_at'=>now()]);
    });
    return [true, $msg];
  }
  private function audit(Request $r, string $a, string $s): void { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$s,'ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]); }

  /** Read-only audit: why do Shipment orders not match the Pathao panel? */
  public function shipAudit(Request $r) {
    $by = DB::table('orders')->where('status', 'Shipment')->selectRaw("coalesce(courier,'(none)') c, (consignment_id is not null) has_cid, (tracking_code is not null and tracking_code <> '') has_trk, count(*) n")->groupBy('c', 'has_cid', 'has_trk')->get();
    $sample = DB::table('orders')->where('status', 'Shipment')->whereNull('consignment_id')->whereNotNull('tracking_code')->where('tracking_code', '<>', '')->orderByDesc('id')->limit(5)->get(['id', 'invoice', 'courier', 'tracking_code', 'courier_status', 'created_at']);
    $out = ['shipment_total' => DB::table('orders')->where('status', 'Shipment')->count(), 'groups' => $by, 'sample' => $sample, 'with_cid_total' => DB::table('orders')->whereNotNull('consignment_id')->count(), 'last_sync' => \Illuminate\Support\Facades\Cache::get('courier.last_sync'), 'pathao_ready' => Pathao::configured(), 'webhook_secret_set' => (bool)Sms::secret('pathao_webhook_secret')];
    if ($r->query('probe')) { $out['probe'] = []; foreach ($sample->take(3) as $o) { try { $i = Pathao::info((string)$o->tracking_code); $out['probe'][] = ['tracking' => $o->tracking_code, 'found' => !empty($i), 'status' => $i['order_status'] ?? null, 'consignment' => $i['consignment_id'] ?? null]; } catch (\Throwable $e) { $out['probe'][] = ['tracking' => $o->tracking_code, 'error' => substr($e->getMessage(), 0, 120)]; } } }
    return response()->json($out);
  }
  public function sync() { $n = \App\Support\CourierSync::run(60); return back()->with('ok', "Sync finished. $n parcel(s) changed status."); }
  /** Pathao pushes here; we never trust the payload, we re-read the parcel from the Pathao API. */
  public function webhook(Request $r) {
    $cid = $r->input('consignment_id'); $sec = Sms::secret('pathao_webhook_secret');
    if ($sec && !hash_equals($sec, (string)$r->header('X-PATHAO-Signature', $r->query('key', '')))) return response()->json(['ok'=>false], 403);
    if ($cid && ($o = DB::table('orders')->where('consignment_id', (string)$cid)->first())) { $i = Pathao::info($o->consignment_id);
      // The event name and the collected amount come from the push; the parcel itself is always re-read from Pathao.
      $ev = trim(str_replace(['order.', '-', '_'], ' ', strtolower((string)$r->input('event', ''))));
      foreach (['collected_amount', 'delivery_fee'] as $k) if ($r->filled($k) && is_numeric($r->input($k))) $i[$k] = $r->input($k);
      $evRow = $ev !== '' ? \App\Support\PathaoStatusMapper::peek($ev) : null; $curRow = \App\Support\PathaoStatusMapper::peek($i['order_status'] ?? '');
      if ($evRow && $evRow->action !== 'none' && (!$curRow || $curRow->action === 'none')) $i['order_status'] = $evRow->raw_label;   // the push names a mapped return/partial/exchange event the parcel read has not caught up with
      $i['_event'] = $ev ?: null;
      if ($i) \App\Support\CourierSync::apply($o, $i); }
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
    $oc = DB::table('orders')->whereNotNull('pathao_outcome')->selectRaw('pathao_outcome o, status s, count(*) c')->groupBy('pathao_outcome', 'status')->get();
    $map = ['Partial Return' => [['Return Pending'], ['Returned']], 'Exchange' => [[], []], 'Regular Return' => [['Return Pending'], ['Returned']], 'Paid Return' => [[], []]];
    $outcomes = []; foreach ($map as $lab => [$pend, $recv]) { $g = $oc->where('o', $lab); $outcomes[] = ['label' => $lab, 'total' => (int)$g->sum('c'), 'pend' => (int)$g->whereIn('s', $pend)->sum('c'), 'recv' => (int)$g->whereIn('s', $recv)->sum('c'), 'pend_status' => $pend[0] ?? null, 'recv_status' => $recv[0] ?? null]; }
    $flagged = (int)DB::table('orders')->whereNotNull('courier_flag')->count();
    return view('courier_dashboard', ['rows'=>$rows->take(200), 'cards'=>$cards, 'outcomes'=>$outcomes, 'flagged'=>$flagged, 'perf'=>$perf, 'stages'=>\App\Support\CourierSync::STAGES, 'couriers'=>DB::table('orders')->whereNotNull('consignment_id')->distinct()->pluck('courier'), 'stores'=>DB::table('stores')->orderBy('name')->get(['id','name']), 'last'=>\Illuminate\Support\Facades\Cache::get('courier.last_sync'), 'ready'=>Pathao::configured()]);
  }
  public function export(Request $r, $fmt) {
    [$rows, , $perf] = $this->dash($r);
    $head = ['Invoice','Customer','Phone','Store','Courier','Consignment','Stage','Courier status','COD','Delivery fee','Return fee','Collected','Settlement','Settled on','Reference','Sent','Delivered'];
    $data = $rows->map(fn($o) => [\App\Support\Inv::show($o->invoice),$o->customer_name,$o->phone,$o->store_name,$o->courier,$o->consignment_id,$o->courier_stage,$o->courier_status,$o->courier_cod,$o->courier_fee,$o->courier_return_fee,$o->collected_amount,$o->settlement_status,$o->settlement_date,$o->settlement_ref,$o->courier_sent_at,$o->courier_delivered_at])->all();
    $title = 'Courier parcels';
    if ($fmt === 'csv') return response()->streamDownload(function () use ($head, $data) { $f = fopen('php://output', 'w'); fputcsv($f, $head); foreach ($data as $d) fputcsv($f, $d); fclose($f); }, 'courier-parcels.csv', ['Content-Type'=>'text/csv']);
    $h = '<meta charset="utf-8"><h2>'.$title.'</h2><table border="1" cellpadding="4" style="border-collapse:collapse;font:12px sans-serif"><tr>'.collect($head)->map(fn($c) => '<th style="background:#FFF1E8">'.e($c).'</th>')->implode('').'</tr>'.collect($data)->map(fn($d) => '<tr>'.collect($d)->map(fn($c) => '<td>'.e((string)$c).'</td>')->implode('').'</tr>')->implode('').'</table>';
    if ($fmt === 'xls') return response($h, 200, ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8', 'Content-Disposition'=>'attachment; filename="courier-parcels.xls"']);
    if ($fmt === 'doc') return response($h, 200, ['Content-Type'=>'application/msword; charset=utf-8', 'Content-Disposition'=>'attachment; filename="courier-parcels.doc"']);
    return response($h.'<script>window.onload=function(){window.print()}</script>');
  }
}
