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
    'Returned'   => ['Return Pending', 'Returned'],
  ];
  const STEPS = ['Order Placed', 'Confirmed', 'Processing', 'Packaging', 'Shipment', 'Delivered'];
  const STEP_OF = ['New'=>0, 'Incomplete'=>0, 'Pre Order'=>0, 'AI Order'=>0, 'Assign'=>1, 'Pending'=>1, 'Approved'=>1, 'Scheduled'=>2, 'WFP'=>2, 'Packaging'=>3, 'Shipment'=>4, 'Late Delivery'=>4, 'Partial Delivered'=>4, 'Delivered'=>5];

  private function norm($v): ?string { $p = Chat::phone((string)$v); return $p && preg_match('/^01\d{9}$/', $p) ? $p : null; }
  public function me(Request $r): ?object {
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
  private function custCategory(?string $phone, ?object $c): string {
    $cat = 'New';
    try { $first = ($phone !== null && $phone !== '') ? DB::table('orders')->where('phone', $phone)->orderByDesc('id')->first(['id', 'phone', 'total']) : null; if ($first) $cat = CustomerIntel::forOrders([$first])[$first->id]['cat'] ?? 'Regular'; elseif ($c && (int)$c->legacy_orders > 0) $cat = 'Regular'; } catch (\Throwable $e) {}
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
    return $this->view('store.account_auth', ['title'=>'Login / Register | DutyFree Shop BD', 'mode'=>$r->query('mode') === 'register' ? 'register' : 'login', 'smsOn'=>Sms::configured(), 'fbOn'=>\App\Support\CustAuth::fbOn(), 'gOn'=>\App\Support\CustAuth::googleOn(), 'fbErr'=>$r->query('fb') === 'off' ? 'off' : ($r->query('fb') === 'err')]);
  }
  public function otpSend(Request $r) {
    \App\Support\PhoneRule::trim($r);
    $d = $r->validate(['phone'=>['required','string',new \App\Support\PhoneRule], 'purpose'=>'required|in:login,register,reset']);
    $p = $this->norm($d['phone']); if (!$p) return response()->json(['sent'=>false, 'error'=>'সঠিক মোবাইল নম্বর দিন'], 422);
    if (!Sms::configured()) return response()->json(['sent'=>false, 'error'=>'এই মুহূর্তে SMS কোড পাঠানো যাচ্ছে না। পাসওয়ার্ড দিয়ে লগইন করুন অথবা কল করুন।'], 503);
    $exists = DB::table('customers')->where('phone', $p)->whereNotNull('password')->exists();
    if ($d['purpose'] !== 'register' && !$exists) return response()->json(['sent'=>true]); // do not reveal which numbers have accounts
    if ($d['purpose'] === 'register' && $exists) return response()->json(['sent'=>false, 'error'=>'এই নম্বরে ইতিমধ্যে অ্যাকাউন্ট আছে। লগইন করুন।'], 422);
    if (Cache::has("ac.otp.cool.$p")) return response()->json(['sent'=>false, 'error'=>'কোড একটু আগেই পাঠানো হয়েছে। মোবাইলের SMS দেখুন, না এলে ১ মিনিট পরে আবার চেষ্টা করুন'], 429);
    $hr = "ac.otp.hr.$p"; Cache::add($hr, 0, 3600);
    if ((int)Cache::get($hr, 0) >= 8) return response()->json(['sent'=>false, 'error'=>'এই নম্বরে অনেকবার কোড চাওয়া হয়েছে। ১ ঘণ্টা পরে আবার চেষ্টা করুন অথবা কল করুন'], 429);
    Cache::increment($hr);
    Cache::add('sms.otp.day', 0, 86400); if ((int)Cache::get('sms.otp.day', 0) >= 3000) return response()->json(['sent'=>false, 'error'=>'এই মুহূর্তে SMS কোড পাঠানো যাচ্ছে না। পাসওয়ার্ড দিয়ে লগইন করুন অথবা কল করুন।'], 503);
    Cache::increment('sms.otp.day');
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
    if (!$c || !Hash::check($d['password'], $c->password)) { $this->hit($r, $id); \App\Support\CustAuth::log($r, $c->id ?? null, 'email', 'failed'); return response()->json(['ok'=>false, 'error'=>'মোবাইল/ইমেইল বা পাসওয়ার্ড ঠিক নয়'], 422); }
    if ($c->blocked) return response()->json(['ok'=>false, 'error'=>'এই অ্যাকাউন্টে লগইন করা যাচ্ছে না। আমাদের সাথে যোগাযোগ করুন।'], 403);
    \App\Support\CustAuth::log($r, (int)$c->id, 'email');
    return response()->json(['ok'=>true, 'go'=>$r->input('next') && str_starts_with((string)$r->input('next'), '/') && !str_starts_with((string)$r->input('next'), '//') ? $r->input('next') : '/account'])->withCookie($this->startSession($r, $c->id, $r->boolean('remember')));
  }
  public function otpLogin(Request $r) {
    \App\Support\PhoneRule::trim($r);
    $d = $r->validate(['phone'=>['required','string',new \App\Support\PhoneRule], 'code'=>'required|string|max:10']);
    $p = $this->norm($d['phone']); if (!$p || $this->throttle($r, 'otp'.$p)) return response()->json(['ok'=>false, 'error'=>'কোড ঠিক নয়'], 422);
    $c = DB::table('customers')->where('phone', $p)->whereNotNull('password')->first();
    if (!$c || $c->blocked || !$this->otpCheck('login', $p, $d['code'])) { $this->hit($r, 'otp'.$p); return response()->json(['ok'=>false, 'error'=>'কোড ঠিক নয় বা মেয়াদ শেষ'], 422); }
    DB::table('customers')->where('id', $c->id)->update(['phone_verified_at'=>$c->phone_verified_at ?? now()]);
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $c->id, true));
  }
  public function register(Request $r) {
    \App\Support\PhoneRule::trim($r);
    $d = $r->validate(['name'=>'required|string|min:2|max:80', 'phone'=>['required','string',new \App\Support\PhoneRule], 'password'=>'required|string|min:8|max:100|confirmed', 'email'=>'nullable|email|max:120', 'ref'=>'nullable|string|max:40', 'code'=>'nullable|string|max:10']);
    $p = $this->norm($d['phone']); if (!$p) return response()->json(['ok'=>false, 'error'=>'সঠিক মোবাইল নম্বর দিন'], 422);
    if (DB::table('customers')->where('phone', $p)->whereNotNull('password')->exists()) return response()->json(['ok'=>false, 'error'=>'এই নম্বরে ইতিমধ্যে অ্যাকাউন্ট আছে। লগইন করুন।'], 422);
    $row = DB::table('customers')->where('phone', $p)->orderBy('id')->first();
    $hasHistory = $row || DB::table('orders')->where('phone', $p)->exists();
    $verified = false;
    if (Sms::configured()) { // the number must be proven: it gives access to existing order history
      if (empty($d['code']) || !$this->otpCheck('register', $p, $d['code'])) return response()->json(['ok'=>false, 'error'=>'মোবাইলে পাঠানো কোডটি দিন (বা কোড ঠিক নয়)', 'need_code'=>true], 422);
      $verified = true;
    } elseif ($hasHistory) return response()->json(['ok'=>false, 'error'=>'এই নম্বরে আগের অর্ডার আছে, তাই নিরাপত্তার জন্য কোড লাগবে। কল করুন: '.\App\Support\Biz::hotline()], 422);
    if (!empty($d['email']) && DB::table('customers')->whereRaw('lower(email) = ?', [mb_strtolower($d['email'])])->where('phone', '!=', $p)->whereNotNull('password')->exists()) return response()->json(['ok'=>false, 'error'=>'এই ইমেইল অন্য অ্যাকাউন্টে ব্যবহৃত'], 422);
    $set = ['password'=>Hash::make($d['password']), 'registered_at'=>now(), 'phone_verified_at'=>$verified ? now() : null, 'referred_by'=>(($d['ref'] ?? null) ?: $r->cookie('dfref')) ?: null, 'updated_at'=>now()];
    if ($row) { DB::table('customers')->where('id', $row->id)->update($set + ['name'=>$row->name ?: $d['name']] + (!empty($d['email']) && !$row->email ? ['email'=>$d['email']] : [])); $cid = $row->id; }
    else $cid = DB::table('customers')->insertGetId($set + ['name'=>$d['name'], 'phone'=>$p, 'email'=>$d['email'] ?? null, 'type'=>'retail', 'ip'=>$r->ip(), 'created_at'=>now()]);
    DB::table('orders')->where('phone', $p)->whereNull('customer_id')->update(['customer_id'=>$cid]);
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $cid, true));
  }
  public function reset(Request $r) {
    \App\Support\PhoneRule::trim($r);
    $d = $r->validate(['phone'=>['required','string',new \App\Support\PhoneRule], 'code'=>'required|string|max:10', 'password'=>'required|string|min:8|max:100|confirmed']);
    $p = $this->norm($d['phone']); $c = $p ? DB::table('customers')->where('phone', $p)->whereNotNull('password')->first() : null;
    if (!$c || !$this->otpCheck('reset', $p, $d['code'])) return response()->json(['ok'=>false, 'error'=>'কোড ঠিক নয় বা মেয়াদ শেষ'], 422);
    DB::table('customers')->where('id', $c->id)->update(['password'=>Hash::make($d['password']), 'phone_verified_at'=>$c->phone_verified_at ?? now(), 'updated_at'=>now()]);
    DB::table('customer_sessions')->where('customer_id', $c->id)->delete();
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $c->id, true));
  }

  /* ---------------- Email registration (Full name, Email, Password) ---------------- */
  public function registerEmail(Request $r) {
    if (!\App\Support\CustAuth::emailOn()) return response()->json(['ok'=>false, 'error'=>'এই মুহূর্তে ইমেইল দিয়ে রেজিস্ট্রেশন বন্ধ আছে।'], 403);
    $d = $r->validate(['name'=>'required|string|min:2|max:80', 'email'=>'required|email|max:120', 'password'=>'required|string|min:8|max:100|confirmed', 'ref'=>'nullable|string|max:40']);
    $em = mb_strtolower(trim($d['email'])); if ($this->throttle($r, 'reg'.$em, 10)) return response()->json(['ok'=>false, 'error'=>'অনেকবার চেষ্টা করা হয়েছে। পরে চেষ্টা করুন।'], 429); $this->hit($r, 'reg'.$em);
    $ex = DB::table('customers')->whereRaw('lower(email) = ?', [$em])->orderBy('id')->get();
    if ($ex->contains(fn($x) => $x->password)) return response()->json(['ok'=>false, 'error'=>'এই ইমেইলে ইতিমধ্যে অ্যাকাউন্ট আছে। লগইন করুন।'], 422);
    $guest = $ex->first();
    if ($guest && ($guest->phone || DB::table('orders')->where('customer_id', $guest->id)->exists())) return response()->json(['ok'=>false, 'error'=>'এই ইমেইলে আগের অর্ডারের তথ্য আছে। Facebook দিয়ে লগইন করুন অথবা সাপোর্টে যোগাযোগ করুন।'], 422);
    $hash = Hash::make($d['password']);
    if ($guest) { DB::table('customers')->where('id', $guest->id)->update(['password'=>$hash, 'registered_at'=>now(), 'name'=>$guest->name ?: $d['name'], 'updated_at'=>now()]); $cid = (int)$guest->id; }
    else { $cid = \App\Support\CustAuth::create($d['name'], $em, null, false, $hash); }
    if (!empty($d['ref']) || $r->cookie('dfref')) DB::table('customers')->where('id', $cid)->update(['referred_by'=>(($d['ref'] ?? null) ?: $r->cookie('dfref')) ?: null]);
    \App\Support\CustAuth::link($cid, 'email', $em, $em, $d['name']); \App\Support\CustAuth::log($r, $cid, 'email');
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $cid, true));
  }
  /* ---------------- Facebook ---------------- */
  public function fbStart(Request $r) { return $this->oauthStart($r, 'facebook'); }
  public function googleStart(Request $r) { return $this->oauthStart($r, 'google'); }
  public function fbCallback(Request $r) { return $this->oauthCallback($r, 'facebook'); }
  public function googleCallback(Request $r) { return $this->oauthCallback($r, 'google'); }
  private function oauthStart(Request $r, string $prov) {
    if (!\App\Support\CustAuth::on($prov)) return redirect('/account/login?fb=off');
    $mode = $r->query('mode') === 'connect' ? 'connect' : 'login'; $c = $mode === 'connect' ? $this->me($r) : null; if ($mode === 'connect' && !$c) return redirect('/account/login');
    $st = bin2hex(random_bytes(20)); $next = (string)$r->query('next'); $next = ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//')) ? $next : '';
    Cache::put('oa.st.' . $st, ['prov'=>$prov, 'mode'=>$mode, 'cid'=>$c->id ?? null, 'next'=>$next], 600);
    $ru = \App\Support\CustAuth::redirectFor($prov);
    $url = $prov === 'google'
      ? 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(['client_id'=>Sms::secret('g_client_id'), 'redirect_uri'=>$ru, 'response_type'=>'code', 'scope'=>'openid email profile', 'state'=>$st, 'prompt'=>'select_account'])
      : 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query(['client_id'=>Sms::secret('fb_app_id'), 'redirect_uri'=>$ru, 'state'=>$st, 'scope'=>'email,public_profile', 'response_type'=>'code']);
    return redirect()->away($url)->withCookie(cookie('dffs', $st, 10, '/', null, $r->isSecure(), true, false, 'lax'));
  }
  private function oauthCallback(Request $r, string $prov) {
    $fail = fn() => redirect('/account/login?fb=err')->withCookie(cookie()->forget('dffs'));
    $st = (string)$r->query('state'); $ctx = $st !== '' ? Cache::pull('oa.st.' . $st) : null;
    if (!$ctx || ($ctx['prov'] ?? '') !== $prov || !hash_equals($st, (string)$r->cookie('dffs')) || $r->query('error') || !$r->query('code')) return $fail();
    $ru = \App\Support\CustAuth::redirectFor($prov); $H = \Illuminate\Support\Facades\Http::class;
    try {
      if ($prov === 'google') {
        $tk = $H::timeout(12)->asForm()->post('https://oauth2.googleapis.com/token', ['code'=>$r->query('code'), 'client_id'=>Sms::secret('g_client_id'), 'client_secret'=>Sms::secret('g_client_secret'), 'redirect_uri'=>$ru, 'grant_type'=>'authorization_code'])->json('access_token');
        if (!$tk) return $fail();
        $g = $H::timeout(12)->withToken($tk)->get('https://openidconnect.googleapis.com/v1/userinfo')->json();
        $me = ['id'=>(string)($g['sub'] ?? ''), 'email'=>!empty($g['email_verified']) ? (string)($g['email'] ?? '') : '', 'name'=>(string)($g['name'] ?? ''), 'avatar'=>$g['picture'] ?? null];
      } else {
        $tk = $H::timeout(12)->get('https://graph.facebook.com/v19.0/oauth/access_token', ['client_id'=>Sms::secret('fb_app_id'), 'client_secret'=>Sms::secret('fb_app_secret'), 'redirect_uri'=>$ru, 'code'=>$r->query('code')])->json('access_token');
        if (!$tk) return $fail();
        $f = $H::timeout(12)->get('https://graph.facebook.com/v19.0/me', ['fields'=>'id,name,email,picture.width(200).height(200)', 'access_token'=>$tk])->json();
        $me = ['id'=>(string)($f['id'] ?? ''), 'email'=>(string)($f['email'] ?? ''), 'name'=>(string)($f['name'] ?? ''), 'avatar'=>$f['picture']['data']['url'] ?? null];
      }
    } catch (\Throwable $e) { return $fail(); }
    if ($me['id'] === '') return $fail();
    $go = $ctx['next'] ?: '/account'; $forget = cookie()->forget('dffs');
    if ($ctx['mode'] === 'connect' && $ctx['cid']) {
      $ex = DB::table('customer_auth_providers')->where('provider', $prov)->where('provider_user_id', $me['id'])->first();
      if ($ex && (int)$ex->customer_id !== (int)$ctx['cid']) return redirect('/account/security?fb=used')->withCookie($forget);
      \App\Support\CustAuth::link((int)$ctx['cid'], $prov, $me['id'], $me['email'] ?: null, $me['name'], $me['avatar']);
      return redirect('/account/security?fb=ok')->withCookie($forget);
    }
    [$cid, $state] = \App\Support\CustAuth::resolveSocial($prov, $me['id'], $me['email'], $me['name'], $me['avatar']);
    if ($state === 'need_email') { $t = bin2hex(random_bytes(16)); Cache::put('fb.pend.' . $t, ['id'=>$me['id'], 'name'=>$me['name'], 'picture'=>['data'=>['url'=>$me['avatar']]], 'prov'=>$prov], 900); return redirect('/account/complete?t=' . $t)->withCookie($forget); }
    if (!$cid) return $fail();
    $c = DB::table('customers')->find($cid); if (!$c || $c->blocked) return redirect('/account/login?fb=err')->withCookie($forget);
    \App\Support\CustAuth::log($r, $cid, $prov);
    return redirect($go)->withCookie($forget)->withCookie($this->startSession($r, $cid, true));
  }
  /** Facebook gave no email: ask for one. */
  public function completeForm(Request $r) {
    $me = Cache::get('fb.pend.' . (string)$r->query('t')); if (!$me) return redirect('/account/login');
    return $this->view('store.account_complete', ['title'=>'অ্যাকাউন্ট সম্পূর্ণ করুন | DutyFree Shop BD', 't'=>(string)$r->query('t'), 'name'=>$me['name'] ?? '']);
  }
  public function completeSave(Request $r) {
    $t = (string)$r->input('t'); $me = Cache::get('fb.pend.' . $t); if (!$me) return response()->json(['ok'=>false, 'error'=>'সেশন শেষ হয়েছে। আবার চেষ্টা করুন।'], 422);
    $d = $r->validate(['email'=>'required|email|max:120']); $em = mb_strtolower(trim($d['email']));
    if (DB::table('customers')->whereRaw('lower(email) = ?', [$em])->exists()) return response()->json(['ok'=>false, 'error'=>'এই ইমেইলে আগে থেকে অ্যাকাউন্ট আছে। ওই ইমেইল দিয়ে লগইন করে Login & Security থেকে Facebook যুক্ত করুন।'], 422);
    $cid = \App\Support\CustAuth::create($me['name'] ?? 'Customer', $em, $me['picture']['data']['url'] ?? null, false);
    $pv = $me['prov'] ?? 'facebook'; \App\Support\CustAuth::link($cid, $pv, (string)$me['id'], $em, $me['name'] ?? null, $me['picture']['data']['url'] ?? null); Cache::forget('fb.pend.' . $t); \App\Support\CustAuth::log($r, $cid, $pv);
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, $cid, true));
  }
  public function fbDisconnect(Request $r, $p = 'facebook') {
    $c = $this->me($r); if (!$c) return response()->json([], 401); $p = $p === 'google' ? 'google' : 'facebook';
    $others = DB::table('customer_auth_providers')->where('customer_id', $c->id)->whereNotIn('provider', ['email', $p])->count();
    if (empty($c->password) && !$others) return response()->json(['ok'=>false, 'error'=>ucfirst($p) . '-ই আপনার একমাত্র লগইন পদ্ধতি। আগে Email + Password সেট করুন, তারপর বিচ্ছিন্ন করুন।'], 422);
    DB::table('customer_auth_providers')->where('customer_id', $c->id)->where('provider', $p)->delete(); return response()->json(['ok'=>true]);
  }
  /** Facebook-only customers set Email + Password (no current password needed). */
  public function setPassword(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    if (!empty($c->password)) return response()->json(['ok'=>false, 'error'=>'আপনার পাসওয়ার্ড আগে থেকেই সেট করা আছে।'], 422);
    $d = $r->validate(['password'=>'required|string|min:8|max:100|confirmed']); if (empty($c->email)) return response()->json(['ok'=>false, 'error'=>'আগে প্রোফাইলে ইমেইল যোগ করুন।'], 422);
    DB::table('customers')->where('id', $c->id)->update(['password'=>Hash::make($d['password']), 'updated_at'=>now()]); \App\Support\CustAuth::link((int)$c->id, 'email', mb_strtolower($c->email), $c->email, $c->name);
    return response()->json(['ok'=>true]);
  }
  /** Add / complete the mobile number from the profile. */
  public function addMobile(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401); \App\Support\PhoneRule::trim($r);
    if ($c->phone) return response()->json(['ok'=>false, 'error'=>'মোবাইল নম্বর বদলাতে সাপোর্টে যোগাযোগ করুন।'], 422);
    $d = $r->validate(['phone'=>['required','string',new \App\Support\PhoneRule], 'code'=>'nullable|string|max:10']); $p = $this->norm($d['phone']); if (!$p) return response()->json(['ok'=>false, 'error'=>'সঠিক মোবাইল নম্বর দিন'], 422);
    if ($this->throttle($r, 'am'.$c->id, 8)) return response()->json(['ok'=>false, 'error'=>'অনেকবার চেষ্টা হয়েছে, পরে চেষ্টা করুন'], 429); $this->hit($r, 'am'.$c->id);
    $ok = !empty($d['code']) && $this->otpCheck('register', $p, $d['code']) ? true : null;
    $e = \App\Support\CustAuth::addMobile($c, $p, $ok ? '1' : null);
    if ($e === 'NEED_CODE') return response()->json(['ok'=>false, 'need_code'=>true, 'error'=>'এই নম্বরে আগের তথ্য আছে। মোবাইলে পাঠানো কোড দিন।'], 422);
    if ($e) return response()->json(['ok'=>false, 'error'=>$e], 422);
    return response()->json(['ok'=>true]);
  }
  /** Forgot password by email (works when an email provider is configured). */
  public function forgotEmail(Request $r) {
    $d = $r->validate(['email'=>'required|email|max:120']); $em = mb_strtolower(trim($d['email']));
    if ($this->throttle($r, 'fe'.$em, 5)) return response()->json(['ok'=>false, 'error'=>'অনেকবার চেষ্টা হয়েছে, পরে চেষ্টা করুন'], 429); $this->hit($r, 'fe'.$em);
    if (!\App\Support\CustAuth::mailReady()) return response()->json(['ok'=>false, 'error'=>'ইমেইল পাঠানো এখনো চালু নেই। নিচে মোবাইল কোড দিয়ে পাসওয়ার্ড রিসেট করুন অথবা সাপোর্টে কল করুন।'], 503);
    $c = DB::table('customers')->whereRaw('lower(email) = ?', [$em])->whereNotNull('password')->first();
    if ($c) {
      $t = bin2hex(random_bytes(24)); DB::table('customer_password_resets')->insert(['customer_id'=>$c->id, 'token_hash'=>hash('sha256', $t), 'expires_at'=>now()->addMinutes(30), 'created_at'=>now(), 'updated_at'=>now()]);
      try { \Illuminate\Support\Facades\Mail::raw("DutyFree Shop BD — পাসওয়ার্ড রিসেট লিংক (৩০ মিনিট বৈধ):\n" . url('/account/reset-email/' . $t) . "\nআপনি অনুরোধ না করলে এটি উপেক্ষা করুন।", fn($m) => $m->to($c->email)->subject('Password reset — DutyFree Shop BD')); } catch (\Throwable $e) {}
    }
    return response()->json(['ok'=>true, 'msg'=>'ইমেইলটি আমাদের কাছে থাকলে রিসেট লিংক পাঠানো হয়েছে।']);
  }
  public function resetEmailForm(Request $r, $t) {
    $x = DB::table('customer_password_resets')->where('token_hash', hash('sha256', $t))->whereNull('used_at')->where('expires_at', '>', now())->first(); if (!$x) return redirect('/account/login');
    return $this->view('store.account_complete', ['title'=>'নতুন পাসওয়ার্ড | DutyFree Shop BD', 'reset'=>$t, 't'=>'', 'name'=>'']);
  }
  public function resetEmailSave(Request $r, $t) {
    $x = DB::table('customer_password_resets')->where('token_hash', hash('sha256', $t))->whereNull('used_at')->where('expires_at', '>', now())->first(); if (!$x) return response()->json(['ok'=>false, 'error'=>'লিংকের মেয়াদ শেষ'], 422);
    $d = $r->validate(['password'=>'required|string|min:8|max:100|confirmed']);
    DB::table('customers')->where('id', $x->customer_id)->update(['password'=>Hash::make($d['password']), 'email_verified_at'=>DB::raw('coalesce(email_verified_at, now())'), 'updated_at'=>now()]);
    DB::table('customer_password_resets')->where('id', $x->id)->update(['used_at'=>now()]); DB::table('customer_sessions')->where('customer_id', $x->customer_id)->delete();
    return response()->json(['ok'=>true, 'go'=>'/account'])->withCookie($this->startSession($r, (int)$x->customer_id, true));
  }
  public function logout(Request $r) {
    if ($t = (string)$r->cookie('dfa')) DB::table('customer_sessions')->where('token_hash', hash('sha256', $t))->delete();
    return redirect('/account/login?out=1')->withCookie(cookie()->forget('dfa'));
  }

  /* ---------------- dashboard ---------------- */
  private function orders($c) {
    return DB::table('orders')->where(fn($q) => $c->phone ? $q->where('phone', $c->phone)->orWhere('customer_id', $c->id) : $q->where('customer_id', $c->id));
  }
  private function group(string $st): string { foreach (self::GROUPS as $g => $l) if (in_array($st, $l)) return $g; return 'Pending'; }
  private function thumbs(array $ids): array {
    if (!$ids) return [];
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->whereIn('i.order_id', $ids)->orderBy('i.id')->get(['i.order_id', 'i.name', 'i.qty', 'i.price', 'i.product_id', 'p.image']);
    return $items->groupBy('order_id')->map(fn($g) => $g->all())->all();
  }
  public function index(Request $r, $tab = 'dashboard', $arg = null) {
    $c = $this->me($r); if (!$c) return redirect('/account/login'.($r->path() !== 'account' ? '?next=/'.$r->path() : ''));
    $tabs = ['dashboard', 'profile', 'orders', 'track', 'addresses', 'wishlist', 'coupons', 'reviews', 'support', 'notifications', 'security', 'refer'];
    if (!in_array($tab, $tabs)) return $this->notFound();
    if ($tab === 'track') return redirect('/track');
    $all = $this->orders($c)->orderByDesc('id')->get(['id', 'invoice', 'status', 'total', 'shipping', 'discount', 'created_at', 'payment_method', 'pay_status', 'courier', 'consignment_id', 'tracking_code', 'courier_status']);
    $cnt = array_fill_keys(array_keys(self::GROUPS), 0); $spend = 0;
    foreach ($all as $o) { $g = $this->group($o->status); $cnt[$g]++; if (!in_array($g, ['Cancelled', 'Returned'])) $spend += (float)$o->total; }
    $tier = null; try { $t0 = \App\Support\OrderDiscount::tier((string)$c->phone, $c); $lv0 = \App\Support\OrderDiscount::levelOf($c->customer_level ?? null, (int)($t0['stars'] ?? 0)); if ($lv0) $tier = ['name' => $lv0['name'], 'stars' => $lv0['stars']]; } catch (\Throwable $e) {}
    $d = ['c'=>$c, 'tier'=>$tier, 'tab'=>$tab, 'cid'=>$this->cid($c), 'cat'=>$this->custCategory($c->phone, $c), 'cnt'=>$cnt, 'spend'=>$spend + (float)$c->legacy_spent, 'totalOrders'=>$all->count() + (int)$c->legacy_orders,
      'title'=>'My Account | DutyFree Shop BD', 'groups'=>array_keys(self::GROUPS)];
    if ($tab === 'dashboard') {
      $recent = $all->take(5); $d['recent'] = $recent; $d['items'] = $this->thumbs($recent->pluck('id')->all());
      $d['wishN'] = DB::table('customer_wishlist')->where('customer_id', $c->id)->count(); $d['addrN'] = DB::table('customer_addresses')->where('customer_id', $c->id)->count();
      $d['couponN'] = $this->publicCoupons($c)->count();
      $d['chatN'] = DB::table('chat_threads')->where(fn($q) => $c->phone ? $q->where('customer_id', $c->id)->orWhere('phone', $c->phone) : $q->where('customer_id', $c->id))->where('channel', 'web')->count();
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
        ->get(['p.id', 'p.ext_id', 'p.name', 'p.price', 'p.offer_price', 'p.stock', 'p.image', 'p.category_id', 'p.sub', 'p.sub_sub', 'p.path_slug'])->map(function ($p) { $p->url = self::url($p); return $p; });
    }
    if ($tab === 'refer') {
      $code = \App\Support\Referral::codeFor($c); $d += ['rcode'=>$code, 'rlink'=>\App\Support\Referral::link($code), 'rs'=>\App\Support\Referral::stats((int)$c->id), 'ron'=>\App\Support\Referral::on(), 'rdisc'=>\App\Support\Referral::discount(), 'rrew'=>\App\Support\Referral::reward(),
        'rlist'=>DB::table('referrals as r')->leftJoin('customers as u', 'u.id', '=', 'r.referred_id')->where('r.referrer_id', $c->id)->orderByDesc('r.id')->limit(50)->get(['r.status', 'r.reward', 'r.created_at', 'r.referred_phone', 'u.name'])];
    }
    if ($tab === 'coupons') $d['coupons'] = $this->publicCoupons($c);
    if ($tab === 'reviews') {
      $d['mine'] = DB::table('reviews as r')->leftJoin('products as p', 'p.id', '=', 'r.product_id')->where('r.customer_id', $c->id)->orderByDesc('r.id')->get(['r.rating', 'r.body', 'r.status', 'r.reply', 'r.created_at', 'p.name as pname']);
      $done = DB::table('reviews')->where('customer_id', $c->id)->pluck('product_id')->all();
      $d['todo'] = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->whereIn('o.id', $all->filter(fn($o) => $o->status === 'Delivered')->pluck('id'))
        ->whereNotNull('i.product_id')->whereNotIn('i.product_id', $done ?: [0])->groupBy('i.product_id', 'i.name', 'p.image')->select('i.product_id', 'i.name', 'p.image', DB::raw('max(o.id) as oid'))->limit(12)->get();
    }
    if ($tab === 'support') {
      $th = DB::table('chat_threads')->where('channel', 'web')->where(fn($q) => $c->phone ? $q->where('customer_id', $c->id)->orWhere('phone', $c->phone) : $q->where('customer_id', $c->id))->orderByDesc('last_at')->limit(5)->get();
      $d['threads'] = $th->map(fn($t) => ['t'=>$t, 'msgs'=>DB::table('chat_messages')->where('thread_id', $t->id)->orderByDesc('id')->limit(30)->get()->reverse()->values()]);
    }
    if ($tab === 'notifications') $d['notes'] = $this->notes($c, $all);
    if ($tab === 'notifications') { $d['hist'] = DB::table('customer_notifications')->where('customer_id', $c->id)->orderByDesc('id')->limit(60)->get(); $d['prefs'] = \App\Support\Push::prefs((int)$c->id); }
    if ($tab === 'security') { $d['sessions'] = DB::table('customer_sessions')->where('customer_id', $c->id)->orderByDesc('last_at')->get(); $d['prov'] = DB::table('customer_auth_providers')->where('customer_id', $c->id)->pluck('provider')->all(); $d['fbOn'] = \App\Support\CustAuth::fbOn(); $d['gOn'] = \App\Support\CustAuth::googleOn(); $d['hist'] = DB::table('customer_login_history')->where('customer_id', $c->id)->orderByDesc('id')->limit(10)->get(); }
    return $this->view('store.account', $d);
  }
  private function publicCoupons($c = null) {
    $ph = $c ? (\App\Support\Chat::phone($c->phone) ?: null) : null;
    return DB::table('coupons')->where('active', true)->where('is_public', true)->where(fn($q) => $q->whereNull('usage_limit')->orWhereRaw('used_count < usage_limit'))->get()
      ->filter(fn($cp) => \App\Support\CouponRules::visibleTo($cp, $ph, $c))->sortBy(fn($cp) => (string)($cp->ends_at ?: $cp->valid_to ?: '9999'))->values()
      ->map(fn($cp) => (object)['code' => $cp->code, 'type' => $cp->type, 'value' => $cp->value, 'min_order' => $cp->min_order, 'max_discount' => $cp->max_discount, 'valid_to' => $cp->ends_at ? \App\Support\CouponRules::local($cp->ends_at, 'Y-m-d') : $cp->valid_to]);
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
    foreach (DB::table('chat_messages as m')->join('chat_threads as t', 't.id', '=', 'm.thread_id')->where('t.channel', 'web')->where(fn($q) => $c->phone ? $q->where('t.customer_id', $c->id)->orWhere('t.phone', $c->phone) : $q->where('t.customer_id', $c->id))->where('m.dir', 'out')->orderByDesc('m.id')->limit(5)->get(['m.body', 'm.created_at']) as $m)
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
    return $this->view('store.account', $d + ['o'=>$o, 'oi'=>$items, 'step'=>self::STEP_OF[$o->status] ?? 0, 'at'=>$at, 'bad'=>in_array($o->status, ['Cancel', 'Return Pending', 'Returned']), 'grp'=>$this->group($o->status), 'inv'=>$inv, 'title'=>$o->invoice.' | My Account']);
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
    $d = $r->validate(['name'=>'required|string|min:2|max:80', 'email'=>'nullable|email|max:120', 'dob'=>'nullable|string|max:10']);
    $dob = null;
    if (empty($c->dob) && trim((string)($d['dob'] ?? '')) !== '') { // DOB can be saved once; later corrections go through admin
      $v = trim($d['dob']); $dt = null;
      foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $fm) { $x = \DateTime::createFromFormat('!' . $fm, $v); if ($x && $x->format($fm) === $v) { $dt = $x; break; } }
      if (!$dt || $dt >= new \DateTime('today') || (int)$dt->format('Y') < 1900) return response()->json(['ok'=>false, 'error'=>'সঠিক জন্মতারিখ দিন (DD-MM-YYYY)'], 422);
      $dob = $dt->format('Y-m-d');
    }
    if (!empty($d['email']) && DB::table('customers')->whereRaw('lower(email) = ?', [mb_strtolower($d['email'])])->where('id', '!=', $c->id)->whereNotNull('password')->exists()) return response()->json(['ok'=>false, 'error'=>'এই ইমেইল অন্য অ্যাকাউন্টে ব্যবহৃত'], 422);
    DB::table('customers')->where('id', $c->id)->update(['name'=>$d['name'], 'email'=>$d['email'] ?: null, 'updated_at'=>now()] + ($dob ? ['dob'=>$dob] : []));
    return response()->json(['ok'=>true]);
  }
  /** DOB is saved once; a later correction is a request an admin approves. */
  public function dobRequest(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    if (empty($c->dob)) return response()->json(['ok'=>false, 'error'=>'আগে প্রোফাইলে জন্মতারিখ সেভ করুন।'], 422);
    $d = $r->validate(['dob'=>'required|string|max:10', 'reason'=>'required|string|min:5|max:300']); $v = trim($d['dob']); $dt = null;
    foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $fm) { $x = \DateTime::createFromFormat('!' . $fm, $v); if ($x && $x->format($fm) === $v) { $dt = $x; break; } }
    if (!$dt || $dt >= new \DateTime('today') || (int)$dt->format('Y') < 1900) return response()->json(['ok'=>false, 'error'=>'সঠিক জন্মতারিখ দিন (DD-MM-YYYY)'], 422);
    if ($dt->format('Y-m-d') === substr((string)$c->dob, 0, 10)) return response()->json(['ok'=>false, 'error'=>'এটি আপনার বর্তমান জন্মতারিখই।'], 422);
    if (DB::table('dob_change_requests')->where('customer_id', $c->id)->where('status', 'pending')->exists()) return response()->json(['ok'=>false, 'error'=>'আপনার একটি অনুরোধ ইতিমধ্যে পেন্ডিং আছে।'], 422);
    if ($this->throttle($r, 'dob'.$c->id, 5)) return response()->json(['ok'=>false, 'error'=>'অনেকবার চেষ্টা হয়েছে, পরে চেষ্টা করুন'], 429); $this->hit($r, 'dob'.$c->id);
    DB::table('dob_change_requests')->insert(['customer_id'=>$c->id, 'old_dob'=>$c->dob, 'new_dob'=>$dt->format('Y-m-d'), 'reason'=>trim($d['reason']), 'status'=>'pending', 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function saveAddress(Request $r) {
    \App\Support\PhoneRule::trim($r);
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $d = $r->validate(['id'=>'nullable|integer', 'label'=>'required|in:Home,Office,Other', 'receiver'=>'required|string|max:80', 'phone'=>['required','string',new \App\Support\PhoneRule], 'district'=>'required|string|max:60', 'thana'=>'nullable|string|max:60', 'area'=>'nullable|string|max:80', 'road'=>'required|string|max:200', 'landmark'=>'nullable|string|max:120']);
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
    $bought = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $d['product_id'])->where('o.status', 'Delivered')->where(fn($q) => $c->phone ? $q->where('o.phone', $c->phone)->orWhere('o.customer_id', $c->id) : $q->where('o.customer_id', $c->id))->orderByDesc('o.id')->value('o.id');
    if (!$bought) return response()->json(['ok'=>false, 'error'=>'শুধু ডেলিভারি হওয়া প্রোডাক্টে রিভিউ দেওয়া যায়'], 422);
    if (DB::table('reviews')->where('customer_id', $c->id)->where('product_id', $d['product_id'])->exists()) return response()->json(['ok'=>false, 'error'=>'আপনি ইতিমধ্যে রিভিউ দিয়েছেন'], 422);
    DB::table('reviews')->insert(['product_id'=>$d['product_id'], 'customer_id'=>$c->id, 'order_id'=>$bought, 'name'=>$c->name, 'rating'=>$d['rating'], 'body'=>strip_tags((string)($d['body'] ?? '')), 'verified_buyer'=>true, 'status'=>'pending', 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function password(Request $r) {
    $c = $this->me($r); if (!$c) return response()->json([], 401);
    $d = $r->validate(['current'=>'required|string|max:100', 'password'=>'required|string|min:8|max:100|confirmed']);
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
    $lastO = DB::table('orders')->where(fn($q) => $c->phone ? $q->where('phone', $c->phone)->orWhere('customer_id', $c->id) : $q->where('customer_id', $c->id))->whereNotNull('address')->orderByDesc('id')->first(['address', 'district']);
    $addr = $a ? trim(implode(', ', array_filter([$a->road, $a->area, $a->thana]))) : ($lastO->address ?? '');
    return response()->json(['in'=>true, 'p'=>$c->phone, 'n'=>$c->name, 'e'=>$c->email ?: '', 'a'=>$addr, 'd'=>$a->district ?? ($lastO->district ?? '')])->header('Cache-Control', 'no-store');
  }
}
