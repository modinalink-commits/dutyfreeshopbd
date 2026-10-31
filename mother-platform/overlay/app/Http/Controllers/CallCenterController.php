<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
/** Web softphone (SIP over WSS via the browser), call history and call notes. SIP password stays encrypted on the server and is only handed to the logged-in admin's browser when connecting. */
class CallCenterController extends Controller {
  private function acct() { return DB::table('sip_accounts')->orderBy('id')->first(); }
  public function phone(Request $r) { return view('softphone', ['has'=>(bool)($this->acct()->wss_url ?? null), 'n'=>preg_replace('/[^0-9+*#]/', '', (string)$r->query('n', ''))]); }
  public function account() {
    $a = $this->acct(); return view('sip_account', ['a'=>$a, 'hasPw'=>(bool)($a->password_enc ?? null)]);
  }
  public function saveAccount(Request $r) {
    $d = $r->validate(['account_name'=>'nullable|string|max:120', 'sip_server'=>'nullable|string|max:255', 'wss_url'=>['nullable', 'string', 'max:400', 'regex:#^wss://[^\s]+$#i'], 'username'=>'required|string|max:120', 'auth_username'=>'nullable|string|max:120', 'password'=>'nullable|string|max:200', 'domain'=>'required|string|max:255', 'display_name'=>'nullable|string|max:120', 'outbound_proxy'=>'nullable|string|max:255', 'dial_prefix'=>'nullable|string|max:20', 'caller_id'=>'nullable|string|max:60', 'stun_server'=>'nullable|string|max:255'], ['wss_url.regex'=>'The WebSocket URL must start with wss:// (secure WebSocket), for example wss://pbx.example.com:8089/ws']);
    $row = ['account_name'=>$d['account_name'] ?? null, 'sip_server'=>$d['sip_server'] ?? null, 'wss_url'=>$d['wss_url'] ?? null, 'username'=>$d['username'], 'auth_username'=>$d['auth_username'] ?? null, 'domain'=>$d['domain'], 'display_name'=>$d['display_name'] ?? null, 'outbound_proxy'=>$d['outbound_proxy'] ?? null, 'dial_prefix'=>$d['dial_prefix'] ?? null, 'caller_id'=>$d['caller_id'] ?? null, 'stun_server'=>$d['stun_server'] ?? null, 'updated_at'=>now()];
    if (!empty($d['password'])) $row['password_enc'] = Crypt::encryptString($d['password']);
    $a = $this->acct(); if ($a) DB::table('sip_accounts')->where('id', $a->id)->update($row); else DB::table('sip_accounts')->insert($row + ['created_at'=>now()]);
    return back()->with('ok', 'SIP account saved');
  }
  /** Connection details for the signed-in admin's browser (never embedded in page HTML). */
  public function creds() {
    $a = $this->acct(); if (!$a || !$a->wss_url || !$a->password_enc) return response()->json(['ok'=>false, 'error'=>'SIP account is not set up yet.'], 200);
    try { $pw = Crypt::decryptString($a->password_enc); } catch (\Throwable $e) { return response()->json(['ok'=>false, 'error'=>'Saved SIP password could not be read. Please save the password again.']); }
    return response()->json(['ok'=>true, 'wss'=>$a->wss_url, 'user'=>$a->username, 'auth'=>$a->auth_username ?: $a->username, 'pw'=>$pw, 'domain'=>$a->domain, 'name'=>$a->display_name ?: ($a->caller_id ?: $a->username), 'prefix'=>$a->dial_prefix, 'stun'=>$a->stun_server, 'ext'=>$a->username, 'staff'=>Auth::user()->name])->header('Cache-Control', 'no-store');
  }
  public function lookup(Request $r) {
    $ph = preg_replace('/\D/', '', (string)$r->query('phone', '')); $ph = preg_match('/(01\d{9})$/', $ph, $m) ? $m[1] : $ph;
    if (strlen($ph) < 6) return response()->json(['found'=>false]);
    $c = DB::table('customers')->where('phone', $ph)->first(); $n = DB::table('orders')->where('phone', $ph)->count();
    $last = DB::table('orders')->where('phone', $ph)->orderByDesc('id')->first(['id', 'invoice', 'status', 'total', 'customer_name']);
    $p = \App\Support\OrderDiscount::profile($ph);
    return response()->json(['found'=>(bool)($c || $n), 'phone'=>$ph, 'name'=>$c->name ?? ($last->customer_name ?? null), 'customer_id'=>$c->id ?? null, 'orders'=>$n, 'last'=>$last, 'category'=>$p['category'] ?? null, 'membership'=>$p['membership'] ?? null]);
  }
  public function logStart(Request $r) {
    $d = $r->validate(['direction'=>'required|in:in,out', 'phone'=>'required|string|max:40', 'extension'=>'nullable|string|max:40']);
    $ph = preg_replace('/\D/', '', $d['phone']); $ph = preg_match('/(01\d{9})$/', $ph, $m) ? $m[1] : $ph;
    $c = DB::table('customers')->where('phone', $ph)->first(); $o = DB::table('orders')->where('phone', $ph)->orderByDesc('id')->first(['id', 'customer_name']);
    $id = DB::table('call_logs')->insertGetId(['direction'=>$d['direction'], 'phone'=>$ph ?: $d['phone'], 'customer_id'=>$c->id ?? null, 'customer_name'=>$c->name ?? ($o->customer_name ?? null), 'extension'=>$d['extension'] ?? null, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'started_at'=>now(), 'status'=>'failed', 'order_id'=>$o->id ?? null, 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['id'=>$id]);
  }
  public function logEnd(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:answered,missed,busy,failed,rejected', 'duration'=>'nullable|integer|min:0|max:86400']);
    DB::table('call_logs')->where('id', $id)->update(['status'=>$d['status'], 'duration'=>$d['duration'] ?? 0, 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function logNote(Request $r, $id) {
    $d = $r->validate(['note'=>'required|string|max:1000']); $l = DB::table('call_logs')->find($id); abort_unless($l, 404);
    DB::table('call_logs')->where('id', $id)->update(['note'=>$d['note'], 'updated_at'=>now()]);
    if ($l->order_id && \Illuminate\Support\Facades\Schema::hasTable('order_staff_comments')) DB::table('order_staff_comments')->insert(['order_id'=>$l->order_id, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'category'=>'Call note', 'body'=>'Call note ('.($l->direction === 'in' ? 'incoming' : 'outgoing').', '.gmdate('i:s', (int)$l->duration).'): '.$d['note'], 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }
  public function history(Request $r) {
    $f = $r->query('f', 'all'); $q = trim((string)$r->query('q', '')); $view = $r->query('view', 'list');
    $base = DB::table('call_logs')->when($f !== 'all', fn($w) => $w->where('status', $f))->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('phone', 'like', '%'.$q.'%')->orWhere('customer_name', 'ilike', '%'.$q.'%')));
    $rows = (clone $base)->orderByDesc('id')->paginate(\App\Support\Pg::per(24))->withQueryString();
    $rep = null;
    if ($view === 'report') {
      $from = now()->subDays(29)->startOfDay();
      $rep = ['tot'=>DB::table('call_logs')->where('created_at', '>=', $from)->selectRaw("count(*) n, sum(case when status='answered' then 1 else 0 end) ans, sum(case when status='missed' then 1 else 0 end) mis, coalesce(sum(duration),0) dur, coalesce(avg(case when status='answered' then duration end),0) avgd, sum(case when direction='in' then 1 else 0 end) inc")->first(),
        'staff'=>DB::table('call_logs')->where('created_at', '>=', $from)->selectRaw("coalesce(user_name,'—') u, count(*) n, sum(case when status='answered' then 1 else 0 end) ans, coalesce(sum(duration),0) dur")->groupBy('user_name')->orderByDesc('n')->get(),
        'days'=>DB::table('call_logs')->where('created_at', '>=', $from)->selectRaw("to_char(created_at at time zone 'Asia/Dhaka','DD Mon') d, count(*) n, sum(case when status='answered' then 1 else 0 end) ans")->groupByRaw("to_char(created_at at time zone 'Asia/Dhaka','DD Mon'), date_trunc('day', created_at at time zone 'Asia/Dhaka')")->orderByRaw("date_trunc('day', created_at at time zone 'Asia/Dhaka') desc")->limit(14)->get()];
    }
    return view('call_history', compact('rows', 'f', 'q', 'view', 'rep'));
  }
}
