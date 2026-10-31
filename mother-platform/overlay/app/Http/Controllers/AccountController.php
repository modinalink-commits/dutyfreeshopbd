<?php
namespace App\Http\Controllers;
use App\Support\Chat;
use App\Support\CustomerIntel;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
/** Customer accounts: one customers table shared by checkout, orders, chat, wishlist and the admin CRM. Matching key = normalised mobile 01XXXXXXXXX. */
class AccountController extends StoreController {
  const GROUPS = [
    'Pending'    => ['New', 'Incomplete', 'Pre Order', 'AI Order', 'Assign', 'Pending'],
    'Confirmed'  => ['Approved', 'Scheduled', 'WFP'],
    'Processing' => ['Packaging'],
    'Shipment'   => ['Shipment', 'Late Delivery', 'Partial Delivered'],
    'Delivered'  => ['Delivered'],
    'Cancelled'  => ['Cancel'],
    'Returned'   => ['Return Pending', 'Returned', 'Exchange Received'],
  ];
  const STEPS = ['Order Placed', 'Confirmed', 'Processing', 'Packaging', 'Shipment', 'Delivered'];
  const STEP_OF = ['New'=>0, 'Incomplete'=>0, 'Pre Order'=>0, 'AI Order'=>0, 'Assign'=>1, 'Pending'=>1, 'Approved'=>1, 'Scheduled'=>2, 'WFP'=>2, 'Packaging'=>3, 'Shipment'=>4, 'Late Delivery'=>4, 'Partial Delivered'=>4, 'Delivered'=>5];

  private function norm($v): ?string { $p = Chat::phone((string)$v); return $p && preg_match('/^01[3-9]\d{8}$/', $p) ? $p : null; }
  private function me(Request $r): ?object {
    $tok = (string)$r->cookie('dfa'); if (strlen($tok) < 30) return null;
    $s = DB::table('customer_sessions')->where('token_hash', hash('sha256', $tok))->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->first();
    if (!$s) return null;
    $c = DB::table('customers')->find($s->customer_id); if (!$c || $c->blocked) return null;
    if (!$s->last_at || abs(now()->diffInMinutes($s->last_at)) > 10) DB::table('customer_sessions')->where('id', $s->id)->update(['last_at'=>now()]);
    $c->sid = $s->id; return $c;
  }
  private function startSession(Request $r, int $cid, bool $remember) {
    $tok = bin2hex(random_bytes(24));
    DB::table('customer_sessions')->insert(['customer_id'=>$cid, 'token_hash'=>hash('sha256', $tok), 'device'=>mb_substr((string)$r->userAgent(), 0, 120), 'ip'=>$r->ip(), 'last_at'=>now(),
      'expires_at'=>now()->addDays($remember ? 60 : 1), 'created_at'=>now(), 'updated_at'=>now()]);
    return cookie('dfa', $tok, $remember ? 60 * 24 * 60 : 0, '/', null, $r->isSecure(), true, false, 'lax');
  }
  private function cid($c) { return 'DFC'.str_pad((string)$c->id, 6, '0', STR_PAD_LEFT); }
  /** Customer-facing category. "Watchlist" is an internal risk label and is never shown. */
  private function category(string $phone, ?object $c): string {
    $cat = 'New';
    try { $first = DB::table('orders')->where('phone', $phone)->orderByDesc('id')->first(['id', 'phone', 'total']); if ($first) $cat = CustomerIntel::forOrders([$first])[$first->id]['cat'] ?? 'Regular'; elseif ($c && (int)$c->legacy_orders > 0) $cat = 'Regular'; } catch (\Throwable $e) {}
    return $cat === 'Watchlist' ? 'Regular' : $cat;
  }
  private function otpKey($purpose, $p) { return "ac.otp.$purpose.$p"; }
  private function otpCheck(string $purpose, string $p, string $code): bool {
    $s = Cache::get($this->otpKey($purpose, $p)); $code = preg_replace('/\D/', '', $code);
    if (!$s || $s['t'] >= 5) return false;
    if (!hash_equals($s['h'], hash('sha256', $code.$p.config('app.key')))) { $s['t']++; Cache::put($this->otpKey($purpose, $p), $s, 300); return false; }
    Cache::forget($this->otpKey($purpose, $p)); return true;
  }
  private function throttle(Request $r, string $k, int $max = 8): bool {
    $key = 'ac.fail.'.md5($k.$r->ip()); Cache::add($key, 0, 900); return Cache::get($key, 0) >= $max;
  }
  private function hit(Request $r, string $k) { $key = 'ac.fail.'.md5($k.$r->ip()); Cache::add($key, 0, 900); Cache::increment($key); }
  private function view(string $v, array $d, int $status = 200) { return response()->view($v, $this->base($d + ['noindex'=>true, 'desc'=>'', 'canon'=>url('/account')]), $status)->header('Cache-Control', 'no-store'); }

  /* ---------------- sign in / register ---------------- */
  public function authForm(Request $r) {
    if ($this->me($r)) return redirect('/account');
    return $this->view('store.account_auth', ['title'=>'Login / Register | DutyFree Shop BD', 'mode'=>$r->query('mode') === 'register' ? 'register' : 'login', 'smsOn'=>Sms::configured()]);
  }
  public function otpSend(Request $r) {
    $d = $r->validate(['phone'=>'required|string|max:20', 'purpose'=>'required|in:login,register,reset']);
    $p = $this->norm($d['phone']); if (!$p) return response()->json(['sent'=>false, 'error'=>'সঠিক মোবাইল নম্বর দিন'], 422);
    if (!Sms::configured()) return response()->json(['sent'=>false, 'error'=>'এই মুহূর্তে SMS কোড পাঠানো যাচ্ছে না। পাসওয়ার্ড দিয়ে লগইন করুন অথবা কল করুন।'], 503);
    $exists = DB::table('customers')->where('phone', $p)->whereNotNull('password')->exists();
    if ($d['purpose'] !== 'register' && !$exists) return response()->json(['sent'=>true]); // do not reveal which numbers have accounts
    if ($d['purpose'] === 'register' && $exists) return response()->json(['sent'=>false, 'error'=>'এই নম্বরে ইতিমধ্যে অ্যাকাউন্ট আছে। লগইন করুন।'], 422);
    if (Cache::has("ac.otp.cool.$p")) return response()->json(['sent'=>false, 'error'=>'১ মিনিট পরে আবার চেষ্টা করুন'], 429);
    $c = (string)random_int(100000, 999999);
    Cache::put($this->otpKey($d['purpose'], $p), ['h'=>hash('sha256', $c.$p.config('app.key')), 't'=>0], 300); Cache::put("ac.otp.cool.$p", 1, 60);
    Sms::queue(Sms::number($p), "DutyFree Shop BD: আপনার কোড {$c} । কোডটি কাউকে দেবেন না।", 'account_otp');
    return response()->json(['sent'=>true]);
  }
  public function login(Request $r) {
    $d = $r->validate(['id'=>'required|string|max:120', 'password'=>'required|string|max:100']);
    $id = trim($d['id']); if ($this->throttle($r, $id)) return response()->json(['ok'=>false, 'error'=>'অনেকবার ভুল হয়েছে। ১৫ মিনিট পরে চেষ্টা করুন।'], 429);
    $p = $this->norm($id);
    $c = $p ? DB::table('customers')->where('phone', $p)->whereNotNull('password')->first() : DB::table('customers')->whereRaw('lower(email) = ?', [mb_strtolower($id)])->whereNotNull('password')->first();
    if (!$c || !Hash::check($d['password'], $c->password)) { $this->hit($r, $id); return response()->json(['ok'=>false, 'error'=>'মোবাইল/ইমেইল বা পাসওয়ার্ড ঠিক নয়'], 422); }
    if ($c->blocked) return response()->json(['ok'=>false, 'error'=>'এই অ্যাকাউন্টে লগইন করা যাচ্ছে না। আমাদের সাথে যোগাযোগ করুন।'], 403);
    return response()->json(['ok'=>true, 'go'=>$r->input('next') && str_starts_with((string)$r->input('next'), '/') && !str_starts_with((string)$r->input('next'), '//') ? $r->input('next') : '/account'])->withCookie($this->startSession($r, $c->id, $r->boolean('remember')));
  }
  public function otpLogin(Request $r) {
    $d = $r->validate(['phone'=>'required|string|max:20', 'code'=>'required|string|max:10']);
    $p = $this->norm($d['phone']); if (!$p || $this->throttle($r, 'otp'.$p)) return response()->json(['ok'=>false, 'error'=>'কোড ঠিক নয়'], 422);
    $c = DB::table('customers')->where('phone', $p)->whereNotNull('password')->first();
    if (!$c || $c->blocked || !$this->otpCheck('login', $p, $d['code'])) { $this->hit($r, 'otp'.$p); return response()->json(['ok'=>false, 'error'=>'কোড ঠিক নয় বা মেয়াদ শেষ'], 422); }
    DB::table('customers')->where('id', $c->id)->update(['phone_verified_at'=>$c->phone_verified_at ?? now()]);
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $c->id, true));
  }
  public function register(Request $r) {
    $d = $r->validate(['name'=>'required|string|min:2|max:80', 'phone'=>'required|string|max:20', 'password'=>'required|string|min:6|max:100|confirmed', 'email'=>'nullable|email|max:120', 'ref'=>'nullable|string|max:40', 'code'=>'nullable|string|max:10']);
    $p = $this->norm($d['phone']); if (!$p) return response()->json(['ok'=>false, 'error'=>'সঠিক মোবাইল নম্বর দিন'], 422);
    if (DB::table('customers')->where('phone', $p)->whereNotNull('password')->exists()) return response()->json(['ok'=>false, 'error'=>'এই নম্বরে ইতিমধ্যে অ্যাকাউন্ট আছে। লগইন করুন।'], 422);
    $row = DB::table('customers')->where('phone', $p)->orderBy('id')->first();
    $hasHistory = $row || DB::table('orders')->where('phone', $p)->exists();
    $verified = false;
    if (Sms::configured()) { // the number must be proven: it gives access to existing order history
      if (empty($d['code']) || !$this->otpCheck('register', $p, $d['code'])) return response()->json(['ok'=>false, 'error'=>'মোবাইলে পাঠানো কোডটি দিন (বা কোড ঠিক নয়)', 'need_code'=>true], 422);
      $verified = true;
    } elseif ($hasHistory) return response()->json(['ok'=>false, 'error'=>'এই নম্বরে আগের অর্ডার আছে, তাই নিরাপত্তার জন্য কোড লাগবে। কল করুন: '.('0'.ltrim((string)(self::set()['call_number'] ?? '09613828081'), '0'))], 422);
    if (!empty($d['email']) && DB::table('customers')->whereRaw('lower(email) = ?', [mb_strtolower($d['email'])])->where('phone', '!=', $p)->whereNotNull('password')->exists()) return response()->json(['ok'=>false, 'error'=>'এই ইমেইল অন্য অ্যাকাউন্টে ব্যবহৃত'], 422);
    $set = ['password'=>Hash::make($d['password']), 'registered_at'=>now(), 'phone_verified_at'=>$verified ? now() : null, 'referred_by'=>$d['ref'] ?? null, 'updated_at'=>now()];
    if ($row) { DB::table('customers')->where('id', $row->id)->update($set + ['name'=>$row->name ?: $d['name']] + (!empty($d['email']) && !$row->email ? ['email'=>$d['email']] : [])); $cid = $row->id; }
    else $cid = DB::table('customers')->insertGetId($set + ['name'=>$d['name'], 'phone'=>$p, 'email'=>$d['email'] ?? null, 'type'=>'retail', 'ip'=>$r->ip(), 'created_at'=>now()]);
    DB::table('orders')->where('phone', $p)->whereNull('customer_id')->update(['customer_id'=>$cid]);
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $cid, true));
  }
  public function reset(Request $r) {
    $d = $r->validate(['phone'=>'required|string|max:20', 'code'=>'required|string|max:10', 'password'=>'required|string|min:6|max:100|confirmed']);
    $p = $this->norm($d['phone']); $c = $p ? DB::table('customers')->where('phone', $p)->whereNotNull('password')->first() : null;
    if (!$c || !$this->otpCheck('reset', $p, $d['code'])) return response()->json(['ok'=>false, 'error'=>'কোড ঠিক নয় বা মেয়াদ শেষ'], 422);
    DB::table('customers')->where('id', $c->id)->update(['password'=>Hash::make($d['password']), 'phone_verified_at'=>$c->phone_verified_at ?? now(), 'updated_at'=>now()]);
    DB::table('customer_sessions')->where('customer_id', $c->id)->delete();
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $c->id, true));
  }
  public function logout(Request $r) {
    if ($t = (string)$r->cookie('dfa')) DB::table('customer_sessions')->where('token_hash', hash('sha256', $t))->delete();
    return redirect('/account/login?out=1')->withCookie(cookie()->forget('dfa'));
  }

  /* ---------------- dashboard ---------------- */
  private function orders($c) {
    return DB::table('orders')->where(fn($q) => $q->where('phone', $c->phone)->orWhere('customer_id', $c->id));
  }
  private function group(string $st): string { foreach (self::GROUPS as $g => $l) if (in_array($st, $l)) return $g; return 'Pending'; }
  private function thumbs(array $ids): array {
    if (!$ids) return [];
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->whereIn('i.order_id', $ids)->orderBy('i.id')->get(['i.order_id', 'i.name', 'i.qty', 'i.price', 'i.product_id', 'p.image']);
    return $items->groupBy('order_id')->map(fn($g) => $g->all())->all();
  }
  public function index(Request $r, $tab = 'dashboard', $arg = null) {
    $c = $this->me($r); if (!$c) return redirect('/account/login'.($r->path() !== 'account' ? '?next=/'.$r->path() : ''));
    $tabs = ['dashboard', 'profile', 'orders', 'track', 'addresses', 'wishlist', 'coupons', 'reviews', 'support', 'notifications', 'security'];
    if (!in_array($tab, $tabs)) return $this->notFound();
    if ($tab === 'track') return redirect('/track');
    $all = $this->orders($c)->orderByDesc('id')->get(['id', 'invoice', 'status', 'total', 'shipping', 'discount', 'created_at', 'payment_method', 'pay_status', 'courier', 'consignment_id', 'tracking_code', 'courier_status']);
    $cnt = array_fill_keys(array_keys(self::GROUPS), 0); $spend = 0;
    foreach ($all as $o) { $g = $this->group($o->status); $cnt[$g]++; if (!in_array($g, ['Cancelled', 'Returned'])) $spend += (float)$o->total; }
    $d = ['c'=>$c, 'tab'=>$tab, 'cid'=>$this->cid($c), 'cat'=>$this->category($c->phone, $c), 'cnt'=>$cnt, 'spend'=>$spend + (float)$c->legacy_spent, 'totalOrders'=>$all->count() + (int)$c->legacy_orders,
      'title'=>'My Account | DutyFree Shop BD', 'groups'=>array_keys(self::GROUPS)];
    if ($tab === 'dashboard') {
      $recent = $all->take(5); $d['recent'] = $recent; $d['items'] = $this->thumbs($recent->pluck('id')->all());
      $d['wishN'] = DB::table('customer_wishlist')->where('customer_id', $c->id)->count(); $d['addrN'] = DB::table('customer_addresses')->where('customer_id', $c->id)->count();
      $d['couponN'] = $this->publicCoupons()->count();
      $d['chatN'] = DB::table('chat_threads')->where(fn($q) => $q->where('customer_id', $c->id)->orWhere('phone', $c->phone))->where('channel', 'web')->count();
    }
    if ($tab === 'orders') {
      if ($arg) return $this->orderDetail($c, $arg, $d);
      $f = (string)$r->query('status', 'All'); $q = trim((string)$r->query('q', ''));
      $list = $all->filter(fn($o) => $f === 'All' || $this->group($o->status) === $f);
      if ($q !== '') {
        $hit = DB::table('order_items')->whereIn('order_id', $all->pluck('id'))->whereRaw('lower(name) like ?', ['%'.mb_strtolower($q).'%'])->pluck('order_id')->all();
        $list = $list->filter(fn($o) => stripos($o->invoice, $q) !== false || in_array($o->id, $hit));
      }
      $list = $list->values()->take(60); $d += ['list'=>$list, 'items'=>$this->thumbs($list->pluck('id')->all()), 'f'=>$f, 'q'=>$q];
    }
    if ($tab === 'addresses') $d['addrs'] = DB::table('customer_addresses')->where('customer_id', $c->id)->orderByDesc('is_default')->orderBy('id')->get();
    if ($tab === 'wishlist') {
      $d['wish'] = DB::table('customer_wishlist as w')->join('products as p', 'p.id', '=', 'w.product_id')->where('w.customer_id', $c->id)->where('p.published', true)->orderByDesc('w.id')
        ->get(['p.id', 'p.ext_id', 'p.name', 'p.price', 'p.offer_price', 'p.stock', 'p.image', 'p.category_id', 'p.sub', 'p.path_slug'])->map(function ($p) { $p->url = self::url($p); return $p; });
    }
    if ($tab === 'coupons') $d['coupons'] = $this->publicCoupons();
    if ($tab === 'reviews') {
      $d['mine'] = DB::table('reviews as r')->leftJoin('products as p', 'p.id', '=', 'r.product_id')->where('r.customer_id', $c->id)->orderByDesc('r.id')->get(['r.rating', 'r.body', 'r.status', 'r.reply', 'r.created_at', 'p.name as pname']);
      $done = DB::table('reviews')->where('customer_id', $c->id)->pluck('product_id')->all();
      $d['todo'] = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->whereIn('o.id', $all->filter(fn($o) => $o->status === 'Delivered')->pluck('id'))
        ->whereNotNull('i.product_id')->whereNotIn('i.product_id', $done ?: [0])->groupBy('i.product_id', 'i.name', 'p.image')->select('i.product_id', 'i.name', 'p.image', DB::raw('max(o.id) as oid'))->limit(12)->get();
    }
    if ($tab === 'support') {
      $th = DB::table('chat_threads')->where('channel', 'web')->where(fn($q) => $q->where('customer_id', $c->id)->orWhere('phone', $c->phone))->orderByDesc('last_at')->limit(5)->get();
      $d['threads'] = $th->map(fn($t) => ['t'=>$t, 'msgs'=>DB::table('chat_messages')->where('thread_id', $t->id)->orderByDesc('id')->limit(30)->get()->reverse()->values()]);
    }
    if ($tab === 'notifications') $d['notes'] = $this->notes($c, $all);
    if ($tab === 'security') $d['sessions'] = DB::table('customer_sessions')->where('customer_id', $c->id)->orderByDesc('last_at')->get();
    return $this->view('store.account', $d);
  }
  private function publicCoupons() {
    $today = now('Asia/Dhaka')->toDateString();
    return DB::table('coupons')->where('active', true)->where('is_public', true)->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
      ->where(fn($q) => $q->whereNull('usage_limit')->orWhereRaw('used_count < usage_limit'))->orderBy('valid_to')->get(['code', 'type', 'value', 'min_order', 'max_discount', 'valid_to']);
  }
  private function notes($c, $all): array {
    $out = [];
    $ids = $all->pluck('id')->all(); $inv = $all->pluck('invoice', 'id');
    if ($ids) foreach (DB::table('order_status_logs')->whereIn('order_id', $ids)->orderByDesc('id')->limit(25)->get(['order_id', 'to_status', 'created_at']) as $l) {
      $g = $this->group($l->to_status);
      $msg = ['Pending'=>'অর্ডার গ্রহণ করা হয়েছে', 'Confirmed'=>'অর্ডার কনফার্ম হয়েছে', 'Processing'=>'অর্ডার প্রসেসিং হচ্ছে', 'Shipment'=>'অর্ডার শিপমেন্টে দেওয়া হয়েছে', 'Delivered'=>'অর্ডার ডেলিভারি সম্পন্ন', 'Cancelled'=>'অর্ডার বাতিল হয়েছে', 'Returned'=>'অর্ডার রিটার্ন প্রক্রিয়ায়'][$g] ?? 'স্ট্যাটাস আপডেট';
      $out[] = ['at'=>$l->created_at, 'icon'=>$g === 'Delivered' ? '✅' : ($g === 'Shipment' ? '🚚' : ($g === 'Cancelled' ? '❌' : '📦')), 'text'=>$msg.' · '.($inv[$l->order_id] ?? ''), 'href'=>'/account/orders/'.($inv[$l->order_id] ?? '')];
    }
    foreach ($all as $o) if ($o->pay_status === 'Verified') $out[] = ['at'=>$o->created_at, 'icon'=>'💳', 'text'=>'পেমেন্ট যাচাই হয়েছে · '.$o->invoice, 'href'=>'/account/orders/'.$o->invoice];
    foreach (DB::table('chat_messages as m')->join('chat_threads as t', 't.id', '=', 'm.thread_id')->where('t.channel', 'web')->where(fn($q) => $q->where('t.customer_id', $c->id)->orWhere('t.phone', $c->phone))->where('m.dir', 'out')->orderByDesc('m.id')->limit(5)->get(['m.body', 'm.created_at']) as $m)
      $out[] = ['at'=>$m->created_at, 'icon'=>'💬', 'text'=>'সাপোর্ট রিপ্লাই: '.mb_substr((string)$m->body, 0, 80), 'href'=>'/account/support'];
    usort($out, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
    return array_slice($out, 0, 30);
  }
  private function orderDetail($c, string $invoice, array $d) {
    $o = $this->orders($c)->where('invoice', strtoupper($invoice))->first(); if (!$o) return $this->notFound();
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $o->id)->get(['i.name', 'i.qty', 'i.price', 'i.product_id', 'p.image']);
    $logs = DB::table('order_status_logs')->where('order_id', $o->id)->orderBy('id')->get(['to_status', 'created_at']);
    $at = []; foreach ($logs as $l) { $s = self::STEP_OF[$l->to_status] ?? null; if ($s !== null && !isset($at[$s])) $at[$s] = $l->created_at; }
    $at[0] = $at[0] ?? $o->created_at;
    $inv = URL::signedRoute('order.invoice', ['invoice'=>$o->invoice]);
    return $this->view('store.account', $d + ['o'=>$o, 'oi'=>$items, 'step'=>self::STEP_OF[$o->status] ?? 0, 'at'=>$at, 'bad'=>in_array($o->status, ['Cancel', 'Return Pending', 'Returned', 'Exchange Received']), 'grp'=>$this->group($o->status), 'inv'=>$inv, 'title'=>$o->invoice.' | My Account']);
  }
  public function reorder(Request $r, $invoice) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $o = $this->orders($c)->where('invoice', strtoupper($invoice))->first(['id']); if (!$o) return response()->json([], 404);
    $rows = DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $o->id)->where('p.published', true)->where('p.stock', '>', 0)->get(['p.id', 'p.price', 'p.offer_price', 'i.qty']);
    return response()->json($rows->map(fn($p) => ['id'=>$p->id, 'q'=>(int)$p->qty, 'price'=>(float)($p->offer_price ?: $p->price)]));
  }

  /* ---------------- profile & data ---------------- */
  public function saveProfile(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $d = $r->validate(['name'=>'required|string|min:2|max:80', 'email'=>'nullable|email|max:120', 'dob'=>'nullable|date|before:today']);
    if (!empty($d['email']) && DB::table('customers')->whereRaw('lower(email) = ?', [mb_strtolower($d['email'])])->where('id', '!=', $c->id)->whereNotNull('password')->exists()) return response()->json(['ok'=>false, 'error'=>'এই ইমেইল অন্য অ্যাকাউন্টে ব্যবহৃত'], 422);
    DB::table('customers')->where('id', $c->id)->update(['name'=>$d['name'], 'email'=>$d['email'] ?: null, 'dob'=>$d['dob'] ?: null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function saveAddress(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $d = $r->validate(['id'=>'nullable|integer', 'label'=>'required|in:Home,Office,Other', 'receiver'=>'required|string|max:80', 'phone'=>'required|string|max:20', 'district'=>'required|string|max:60', 'thana'=>'nullable|string|max:60', 'area'=>'nullable|string|max:80', 'road'=>'required|string|max:200', 'landmark'=>'nullable|string|max:120']);
    $p = $this->norm($d['phone']); if (!$p) return response()->json(['ok'=>false, 'error'=>'সঠিক মোবাইল নম্বর দিন'], 422);
    $row = ['label'=>$d['label'], 'receiver'=>$d['receiver'], 'phone'=>$p, 'district'=>$d['district'], 'thana'=>$d['thana'] ?? null, 'area'=>$d['area'] ?? null, 'road'=>$d['road'], 'landmark'=>$d['landmark'] ?? null, 'updated_at'=>now()];
    if (!empty($d['id'])) { DB::table('customer_addresses')->where('id', $d['id'])->where('customer_id', $c->id)->update($row); $id = $d['id']; }
    else { $first = !DB::table('customer_addresses')->where('customer_id', $c->id)->exists(); $id = DB::table('customer_addresses')->insertGetId($row + ['customer_id'=>$c->id, 'is_default'=>$first, 'created_at'=>now()]); }
    if ($r->boolean('is_default')) { DB::table('customer_addresses')->where('customer_id', $c->id)->update(['is_default'=>false]); DB::table('customer_addresses')->where('id', $id)->update(['is_default'=>true]); }
    return response()->json(['ok'=>true]);
  }
  public function deleteAddress(Request $r, $id) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $a = DB::table('customer_addresses')->where('id', $id)->where('customer_id', $c->id)->first(); if (!$a) return response()->json(['ok'=>false], 404);
    DB::table('customer_addresses')->where('id', $id)->delete();
    if ($a->is_default && ($n = DB::table('customer_addresses')->where('customer_id', $c->id)->orderBy('id')->first())) DB::table('customer_addresses')->where('id', $n->id)->update(['is_default'=>true]);
    return response()->json(['ok'=>true]);
  }
  public function defaultAddress(Request $r, $id) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    if (DB::table('customer_addresses')->where('id', $id)->where('customer_id', $c->id)->exists()) { DB::table('customer_addresses')->where('customer_id', $c->id)->update(['is_default'=>false]); DB::table('customer_addresses')->where('id', $id)->update(['is_default'=>true]); }
    return response()->json(['ok'=>true]);
  }
  /** Merge the browser's wishlist into the account and return the account list so both stay in step. */
  public function wishSync(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json(['ids'=>[]]);
    $ids = collect((array)$r->input('ids'))->map(fn($v) => (int)$v)->filter(fn($v) => $v > 0)->unique()->take(100)->all();
    if ($ids) { $ok = DB::table('products')->whereIn('id', $ids)->pluck('id')->all(); foreach ($ok as $pid) DB::table('customer_wishlist')->updateOrInsert(['customer_id'=>$c->id, 'product_id'=>$pid], ['updated_at'=>now(), 'created_at'=>now()]); }
    return response()->json(['ids'=>DB::table('customer_wishlist')->where('customer_id', $c->id)->pluck('product_id')]);
  }
  public function wishRemove(Request $r, $pid) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    DB::table('customer_wishlist')->where('customer_id', $c->id)->where('product_id', $pid)->delete(); return response()->json(['ok'=>true]);
  }
  public function wishToggle(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json(['ok'=>false]);
    $pid = (int)$r->input('id'); if ($pid < 1) return response()->json(['ok'=>false]);
    $q = DB::table('customer_wishlist')->where('customer_id', $c->id)->where('product_id', $pid);
    if ($q->exists()) { $q->delete(); return response()->json(['ok'=>true, 'on'=>false]); }
    if (DB::table('products')->where('id', $pid)->exists()) DB::table('customer_wishlist')->insert(['customer_id'=>$c->id, 'product_id'=>$pid, 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'on'=>true]);
  }
  public function review(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $d = $r->validate(['product_id'=>'required|integer', 'rating'=>'required|integer|min:1|max:5', 'body'=>'nullable|string|max:1000']);
    $bought = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $d['product_id'])->where('o.status', 'Delivered')->where(fn($q) => $q->where('o.phone', $c->phone)->orWhere('o.customer_id', $c->id))->orderByDesc('o.id')->value('o.id');
    if (!$bought) return response()->json(['ok'=>false, 'error'=>'শুধু ডেলিভারি হওয়া প্রোডাক্টে রিভিউ দেওয়া যায়'], 422);
    if (DB::table('reviews')->where('customer_id', $c->id)->where('product_id', $d['product_id'])->exists()) return response()->json(['ok'=>false, 'error'=>'আপনি ইতিমধ্যে রিভিউ দিয়েছেন'], 422);
    DB::table('reviews')->insert(['product_id'=>$d['product_id'], 'customer_id'=>$c->id, 'order_id'=>$bought, 'name'=>$c->name, 'rating'=>$d['rating'], 'body'=>strip_tags((string)($d['body'] ?? '')), 'verified_buyer'=>true, 'status'=>'pending', 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function password(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $d = $r->validate(['current'=>'required|string|max:100', 'password'=>'required|string|min:6|max:100|confirmed']);
    if ($this->throttle($r, 'pw'.$c->id, 6)) return response()->json(['ok'=>false, 'error'=>'অনেকবার ভুল হয়েছে, পরে চেষ্টা করুন'], 429);
    if (!Hash::check($d['current'], (string)$c->password)) { $this->hit($r, 'pw'.$c->id); return response()->json(['ok'=>false, 'error'=>'বর্তমান পাসওয়ার্ড ঠিক নয়'], 422); }
    DB::table('customers')->where('id', $c->id)->update(['password'=>Hash::make($d['password']), 'updated_at'=>now()]);
    DB::table('customer_sessions')->where('customer_id', $c->id)->where('id', '!=', $c->sid)->delete();
    return response()->json(['ok'=>true]);
  }
  public function endSession(Request $r, $id) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    if ($id === 'others') DB::table('customer_sessions')->where('customer_id', $c->id)->where('id', '!=', $c->sid)->delete();
    else DB::table('customer_sessions')->where('customer_id', $c->id)->where('id', (int)$id)->where('id', '!=', $c->sid)->delete();
    return response()->json(['ok'=>true]);
  }
  /** Used by the storefront (checkout, chat) to pre-fill a logged-in customer. */
  public function whoami(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json(['in'=>false])->header('Cache-Control', 'no-store');
    $a = DB::table('customer_addresses')->where('customer_id', $c->id)->orderByDesc('is_default')->orderBy('id')->first();
    $lastO = DB::table('orders')->where('phone', $c->phone)->whereNotNull('address')->orderByDesc('id')->first(['address', 'district']);
    $addr = $a ? trim(implode(', ', array_filter([$a->road, $a->area, $a->thana]))) : ($lastO->address ?? '');
    return response()->json(['in'=>true, 'p'=>$c->phone, 'n'=>$c->name, 'e'=>$c->email ?: '', 'a'=>$addr, 'd'=>$a->district ?? ($lastO->district ?? '')])->header('Cache-Control', 'no-store');
  }
}
