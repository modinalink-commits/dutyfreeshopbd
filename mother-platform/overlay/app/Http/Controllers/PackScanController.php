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
  private ?int $runId = null;
  private function log(?object $o, string $code, string $res, string $msg): void {
    try { if ($this->runId && $res !== 'run') { DB::table('order_scan_logs')->where('id', $this->runId)->update(['result'=>$res, 'message'=>mb_substr($msg, 0, 300)]); $this->runId = null; return; } } catch (\Throwable $e) {}
    try { if (Schema::hasTable('order_scan_logs')) $this->lastId = (int)DB::table('order_scan_logs')->insertGetId(['order_id'=>$o->id ?? null, 'invoice'=>$o ? Inv::show($o->invoice) : null, 'code'=>mb_substr($code, 0, 80), 'result'=>$res, 'message'=>mb_substr($msg, 0, 300), 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? null, 'created_at'=>now()], 'id'); } catch (\Throwable $e) {}
  }
  private int $lastId = 0;
  private function out(string $res, string $title, string $msg, ?object $o, string $code) {
    $this->log($o, $code, $res, $title.': '.$msg);
    $card = null;
    if ($o) { $its = DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get(['name', 'qty']);
      $card = ['invoice'=>Inv::show($o->invoice), 'name'=>(string)($o->customer_name ?? ''), 'phone'=>(string)($o->phone ?? ''), 'items'=>$its->map(fn($i) => $i->name.' × '.(int)$i->qty)->implode(', '), 'total'=>number_format((float)$o->total), 'status'=>(string)DB::table('orders')->where('id', $o->id)->value('status'), 'courier'=>(string)($o->courier ?? ''), 'tracking'=>(string)(DB::table('orders')->where('id', $o->id)->value('consignment_id') ?? '')]; }
    return response()->json(['result'=>$res, 'title'=>$title, 'message'=>$msg, 'order'=>$card] + $this->today());
  }
  /** Plain Bengali reason for packing staff; the system's own (English) message is kept at the end for the manager. */
  private static function why(string $m): string {
    $l = mb_strtolower($m); $d = trim(preg_replace('/^.*?stays in [A-Za-z ]+:\s*/u', '', $m));
    $bn = match (true) {
      str_contains($l, 'ai verification') => 'AI যাচাইয়ে এই অর্ডারটা ঝুঁকিপূর্ণ ধরা পড়েছে। ম্যানেজার বা অ্যাডমিন অর্ডারটা খুলে যাচাই করবেন, তারপর কারণ লিখে পাঠাবেন।',
      str_contains($l, 'insufficient stock') || str_contains($l, 'no stock') => 'এই পণ্যের স্টক নেই। আগে Stock In করতে হবে, তারপর আবার স্ক্যান করুন।',
      str_contains($l, 'duplicate') => 'একই গ্রাহকের আরেকটা অর্ডার আছে (ডুপ্লিকেট)। ম্যানেজার বা অ্যাডমিনের অনুমতি লাগবে।',
      str_contains($l, 'margin') || str_contains($l, 'profit') => 'এই অর্ডারে লাভ নির্ধারিত সীমার নিচে। ম্যানেজার বা অ্যাডমিন দেখে কারণ লিখে পাঠাবেন।',
      str_contains($l, 'override') => 'এই অর্ডারে একটা গুরুতর সতর্কতা আছে। ম্যানেজার বা অ্যাডমিন অর্ডারটা খুলে দেখে কারণ লিখে পাঠাবেন।',
      str_contains($l, 'address') => 'গ্রাহকের ঠিকানা অসম্পূর্ণ বা খুব ছোট। অর্ডারে ঠিকানা ঠিক করে আবার স্ক্যান করুন।',
      str_contains($l, 'phone') => 'গ্রাহকের ফোন নম্বর ঠিক নেই (১১ সংখ্যা হতে হবে)। অর্ডারে নম্বর ঠিক করে আবার স্ক্যান করুন।',
      str_contains($l, 'not connected') || str_contains($l, 'pickup store') || str_contains($l, 'access token') => 'কুরিয়ারের সংযোগ ঠিক নেই। অ্যাডমিনকে জানান।',
      str_contains($l, 'already in progress') => 'এই অর্ডারের বুকিং এখনই চলছে। একটু পরে অর্ডার লিস্টে স্ট্যাটাস দেখুন।',
      str_contains($l, 'does not allow') || str_contains($l, 'permission') => 'আপনার আইডিতে এই কাজের অনুমতি নেই। ম্যানেজার বা অ্যাডমিনকে বলুন।',
      str_contains($l, 'http ') || str_contains($l, 'pathao') || str_contains($l, 'courier') => 'কুরিয়ার এই পার্সেলটা নেয়নি। একটু পরে আবার স্ক্যান করুন; না হলে ম্যানেজারকে জানান।',
      default => 'সিস্টেম এই অর্ডারটা পাঠাতে দেয়নি। ম্যানেজারকে জানান।',
    };
    return $bn.($d !== '' ? ' [System: '.mb_substr($d, 0, 170).']' : '');
  }
  private function today(): array {
    try {
      if (!Schema::hasTable('order_scan_logs')) return ['rows'=>[], 'ok'=>0, 'stopped'=>0, 'waiting'=>0];
      $d0 = now('Asia/Dhaka')->startOfDay()->utc();
      $c = DB::table('order_scan_logs')->where('created_at', '>=', $d0)->selectRaw("count(*) filter (where result = 'ok') ok, count(*) filter (where result not in ('ok','run')) bad")->first();
      $rows = DB::table('order_scan_logs')->where('created_at', '>=', $d0)->orderByDesc('id')->limit(40)->get()->map(fn($r) => ['t'=>\Carbon\Carbon::parse($r->created_at, 'UTC')->timezone('Asia/Dhaka')->format('h:i:s A'), 'inv'=>$r->invoice ?: $r->code, 'res'=>$r->result, 'msg'=>(string)$r->message, 'by'=>(string)$r->user_name])->all();
      return ['rows'=>$rows, 'ok'=>(int)($c->ok ?? 0), 'stopped'=>(int)($c->bad ?? 0), 'waiting'=>(int)DB::table('orders')->where('status', 'Packaging')->count()];
    } catch (\Throwable $e) { return ['rows'=>[], 'ok'=>0, 'stopped'=>0, 'waiting'=>0]; }
  }
  public function list() { abort_unless($this->can(), 403); return response()->json($this->today()); }
  public function scan(Request $r) {
    if (!$this->can()) return response()->json(['result'=>'bad', 'title'=>'অনুমতি নেই', 'message'=>'আপনার আইডিতে অর্ডার আপডেট করার অনুমতি নেই। অ্যাডমিনকে বলুন।', 'order'=>null] + $this->today(), 403);
    $code = trim((string)$r->input('code')); if ($code === '') return response()->json(['result'=>'bad', 'title'=>'কিছু স্ক্যান হয়নি', 'message'=>'পার্সেলের লেবেলের বারকোড স্ক্যান করুন।', 'order'=>null] + $this->today());
    $cands = Inv::candidates($code);
    $o = $cands ? DB::table('orders')->whereIn('invoice', $cands)->orderByDesc('id')->first() : null;
    if (!$o) $o = DB::table('orders')->where('consignment_id', $code)->orderByDesc('id')->first();
    if (!$o) return $this->out('bad', 'অর্ডার পাওয়া যায়নি', mb_substr($code, 0, 40).' নম্বরের কোনো অর্ডার নেই। লেবেলটা আবার দেখুন। এই পার্সেল কুরিয়ারকে দেবেন না।', null, $code);
    if (!Cache::add('packscan.'.$o->id, 1, 20)) return $this->out('bad', 'দুইবার স্ক্যান হয়েছে', 'এই অর্ডারটা একটু আগেই স্ক্যান হয়েছে। হাতে যদি এই অর্ডারের আরেকটা পার্সেল থাকে, সেটা কুরিয়ারকে দেবেন না।', $o, $code);
    if (in_array($o->status, self::GONE, true)) {
      $l = DB::table('order_status_logs')->where('order_id', $o->id)->where('to_status', 'Shipment')->orderByDesc('id')->first();
      $when = $l ? \Carbon\Carbon::parse($l->created_at, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A').($l->user_name ? ', পাঠিয়েছেন '.$l->user_name : '') : 'আগে';
      return $this->out('bad', 'এই অর্ডার আগেই চলে গেছে', 'এই অর্ডারটা আগেই পাঠানো হয়েছে (এখন '.$o->status.'; '.$when.')। আপনার হাতের পার্সেলটা দ্বিতীয় পার্সেল। এটা কুরিয়ারকে দেবেন না।', $o, $code);
    }
    if (in_array($o->status, self::DEAD, true)) return $this->out('bad', 'থামুন: '.$o->status, 'এই অর্ডারটা '.$o->status.' অবস্থায় আছে। এটা পাঠানো যাবে না। পার্সেলটা রেখে দিন।', $o, $code);
    if ($o->status !== 'Packaging') return $this->out('warn', 'Packaging-এ নেই', 'অর্ডারটা এখন '.$o->status.' অবস্থায় আছে। শুধু Packaging-এর অর্ডার কুরিয়ারকে দেওয়া যায়। আগে এর লেবেল প্রিন্ট দিন (তাহলে নিজে থেকে Packaging-এ আসবে), তারপর আবার স্ক্যান করুন। পার্সেলটা রেখে দিন।', $o, $code);
    // a 'run' line is written before the courier is contacted, so a slow or lost booking is visible in the list
    $this->log($o, $code, 'run', 'কুরিয়ারে পাঠানো হচ্ছে…'); $this->runId = $this->lastId ?: null;
    // the normal status change does the work: duplicate permission, stock check, courier booking, logs
    $r2 = Request::create('/admin/orders/'.$o->id.'/quick-status', 'POST', ['status'=>'Shipment', 'note'=>'Packaging Scan (courier handover)', 'source'=>'Order List']);
    $r2->setUserResolver(fn() => Auth::user()); $r2->server->set('REMOTE_ADDR', $r->ip());
    try { $resp = app(AdminController::class)->orderQuickStatus($r2, $o->id); $j = json_decode((string)$resp->getContent(), true) ?: []; }
    catch (\Illuminate\Validation\ValidationException $e) { $j = ['success'=>false, 'message'=>implode(' ', \Illuminate\Support\Arr::flatten($e->errors()))]; }
    catch (\Throwable $e) { report($e); $j = ['success'=>false, 'message'=>'The order could not be updated.']; }
    if (empty($j['success'])) { Cache::forget('packscan.'.$o->id); return $this->out('warn', 'পাঠানো হয়নি', self::why((string)($j['message'] ?? '')).' অর্ডারটা Packaging-এই আছে। পার্সেলটা রেখে দিন, কুরিয়ারকে দেবেন না।', $o, $code); }
    return $this->out('ok', 'কুরিয়ারকে দিন', 'অর্ডারটা Shipment-এ চলে গেছে। পার্সেলটা কুরিয়ারের ব্যাগে দিন।', DB::table('orders')->find($o->id), $code);
  }
}
