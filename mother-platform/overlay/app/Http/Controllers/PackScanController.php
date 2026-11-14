<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Middleware\ModulePermission as MP;
use App\Support\Inv;

/**
 * Packaging Scan (owner rule 14 Nov 2026): when the courier collects, each finished parcel's label barcode is scanned.
 * A Packaging order goes to Shipment through the normal status change (same duplicate, stock and courier-booking rules);
 * a second parcel of an order that already left is stopped. Every scan is logged.
 */
class PackScanController extends Controller {
  const GONE = ['Shipment', 'Late Delivery', 'Partial Delivered', 'Delivered'];
  const DEAD = ['Cancel', 'Returned', 'Return Pending'];
  private function can(): bool { return MP::can(Auth::user(), 'orders', 'edit'); }
  public function page() { abort_unless($this->can(), 403); return view('pack_scan'); }
  private function log(?object $o, string $code, string $res, string $msg): void {
    try { if (Schema::hasTable('order_scan_logs')) DB::table('order_scan_logs')->insert(['order_id'=>$o->id ?? null, 'invoice'=>$o ? Inv::show($o->invoice) : null, 'code'=>mb_substr($code, 0, 80), 'result'=>$res, 'message'=>mb_substr($msg, 0, 300), 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? null, 'created_at'=>now()]); } catch (\Throwable $e) {}
  }
  private function out(string $res, string $title, string $msg, ?object $o, string $code) {
    $this->log($o, $code, $res, $title.': '.$msg);
    $card = null;
    if ($o) { $its = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get(['name', 'qty']);
      $card = ['invoice'=>Inv::show($o->invoice), 'name'=>(string)($o->customer_name ?? ''), 'phone'=>(string)($o->phone ?? ''), 'items'=>$its->map(fn($i) => $i->name.' × '.(int)$i->qty)->implode(', '), 'total'=>number_format((float)$o->total), 'status'=>(string)DB::table('orders')->where('id', $o->id)->value('status'), 'courier'=>(string)($o->courier ?? ''), 'tracking'=>(string)(DB::table('orders')->where('id', $o->id)->value('consignment_id') ?? '')]; }
    return response()->json(['result'=>$res, 'title'=>$title, 'message'=>$msg, 'order'=>$card] + $this->today());
  }
  private function today(): array {
    try {
      if (!Schema::hasTable('order_scan_logs')) return ['rows'=>[], 'ok'=>0, 'stopped'=>0, 'waiting'=>0];
      $d0 = now('Asia/Dhaka')->startOfDay()->utc();
      $c = DB::table('order_scan_logs')->where('created_at', '>=', $d0)->selectRaw("count(*) filter (where result = 'ok') ok, count(*) filter (where result <> 'ok') bad")->first();
      $rows = DB::table('order_scan_logs')->where('created_at', '>=', $d0)->orderByDesc('id')->limit(40)->get()->map(fn($r) => ['t'=>\Carbon\Carbon::parse($r->created_at, 'UTC')->timezone('Asia/Dhaka')->format('h:i:s A'), 'inv'=>$r->invoice ?: $r->code, 'res'=>$r->result, 'msg'=>(string)$r->message, 'by'=>(string)$r->user_name])->all();
      return ['rows'=>$rows, 'ok'=>(int)($c->ok ?? 0), 'stopped'=>(int)($c->bad ?? 0), 'waiting'=>(int)DB::table('orders')->where('status', 'Packaging')->count()];
    } catch (\Throwable $e) { return ['rows'=>[], 'ok'=>0, 'stopped'=>0, 'waiting'=>0]; }
  }
  public function list() { abort_unless($this->can(), 403); return response()->json($this->today()); }
  public function scan(Request $r) {
    if (!$this->can()) return response()->json(['result'=>'bad', 'title'=>'Not allowed', 'message'=>'Your role does not allow updating orders.', 'order'=>null] + $this->today(), 403);
    $code = trim((string)$r->input('code')); if ($code === '') return response()->json(['result'=>'bad', 'title'=>'Nothing scanned', 'message'=>'Scan the barcode on the parcel label.', 'order'=>null] + $this->today());
    $cands = Inv::candidates($code);
    $o = $cands ? DB::table('orders')->whereIn('invoice', $cands)->orderByDesc('id')->first() : null;
    if (!$o) $o = DB::table('orders')->where('consignment_id', $code)->orderByDesc('id')->first();
    if (!$o) return $this->out('bad', 'NOT FOUND', 'No order has the number '.mb_substr($code, 0, 40).'. Check the label. Do not hand this parcel over.', null, $code);
    if (!Cache::add('packscan.'.$o->id, 1, 20)) return $this->out('bad', 'SCANNED TWICE', 'This order was scanned a moment ago. If you hold a second parcel for it, do not hand it over.', $o, $code);
    if (in_array($o->status, self::GONE, true)) {
      $l = DB::table('order_status_logs')->where('order_id', $o->id)->where('to_status', 'Shipment')->orderByDesc('id')->first();
      $when = $l ? \Carbon\Carbon::parse($l->created_at, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A').($l->user_name ? ' by '.$l->user_name : '') : 'earlier';
      return $this->out('bad', 'ALREADY HANDED OVER', 'This order already left ('.$o->status.', sent '.$when.'). The parcel in your hand is a second one. Do not give it to the courier.', $o, $code);
    }
    if (in_array($o->status, self::DEAD, true)) return $this->out('bad', 'STOP: '.strtoupper($o->status), 'This order is '.$o->status.'. It must not be shipped.', $o, $code);
    if ($o->status !== 'Packaging') return $this->out('warn', 'NOT IN PACKAGING', 'Status is '.$o->status.'. Only a Packaging order can be handed over. Print its label (it then moves to Packaging) or correct the status first.', $o, $code);
    // the normal status change does the work: duplicate permission, stock check, courier booking, logs
    $r2 = Request::create('/admin/orders/'.$o->id.'/quick-status', 'POST', ['status'=>'Shipment', 'note'=>'Packaging Scan (courier handover)', 'source'=>'Order List']);
    $r2->setUserResolver(fn() => Auth::user()); $r2->server->set('REMOTE_ADDR', $r->ip());
    try { $resp = app(AdminController::class)->orderQuickStatus($r2, $o->id); $j = json_decode((string)$resp->getContent(), true) ?: []; }
    catch (\Illuminate\Validation\ValidationException $e) { $j = ['success'=>false, 'message'=>implode(' ', \Illuminate\Support\Arr::flatten($e->errors()))]; }
    catch (\Throwable $e) { report($e); $j = ['success'=>false, 'message'=>'The order could not be updated. Open the order and try there.']; }
    if (empty($j['success'])) { Cache::forget('packscan.'.$o->id); return $this->out('warn', 'NOT SENT', (string)($j['message'] ?? 'The order could not be moved to Shipment.').' The order stays in Packaging. Keep this parcel.', $o, $code); }
    return $this->out('ok', 'HAND OVER', 'Order moved to Shipment. Give this parcel to the courier.', DB::table('orders')->find($o->id), $code);
  }
}
