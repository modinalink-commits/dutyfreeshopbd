<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
/** Web softphone (SIP over WSS via the browser), call history and call notes. SIP password stays encrypted on the server and is only handed to the logged-in admin's browser when connecting. */
class CallCenterController extends Controller {
  private function acct() { return DB::table('sip_accounts')->orderBy('id')->first(); }
  public function phone(Request $r) { return view('softphone', ['embed'=>$r->boolean('embed'), 'has'=>(bool)($this->acct()->wss_url ?? null), 'n'=>preg_replace('/[^0-9+*#]/', '', (string)$r->query('n', ''))]); }
  public function account() {
    $a = $this->acct(); return view('sip_account', ['a'=>$a, 'hasPw'=>(bool)($a->password_enc ?? null)]);
  }
  public function saveAccount(Request $r) {
    $d = $r->validate(['account_name'=>'nullable|string|max:120', 'sip_server'=>'nullable|string|max:255', 'wss_url'=>['nullable', 'string', 'max:400', 'regex:#^wss://[^\s]+$#i'], 'username'=>'required|string|max:120', 'auth_username'=>'nullable|string|max:120', 'password'=>'nullable|string|max:200', 'domain'=>'required|string|max:255', 'display_name'=>'nullable|string|max:120', 'outbound_proxy'=>'nullable|string|max:255', 'dial_prefix'=>'nullable|string|max:20', 'caller_id'=>'nullable|string|max:60', 'stun_server'=>'nullable|string|max:255'], ['wss_url.regex'=>'The WebSocket URL must start with wss:// (secure WebSocket), for example wss://pbx.example.com:8089/ws']);
    $row = ['account_name'=>$d['account_name'] ?? null, 'sip_server'=>$d['sip_server'] ?? null, 'wss_url'=>$d['wss_url'] ?? null, 'username'=>$d['username'], 'auth_username'=>$d['auth_username'] ?? null, 'domain'=>$d['domain'], 'display_name'=>$d['display_name'] ?? null, 'outbound_proxy'=>$d['outbound_proxy'] ?? null, 'dial_prefix'=>$d['dial_prefix'] ?? null, 'caller_id'=>$d['caller_id'] ?? null, 'stun_server'=>$d['stun_server'] ?? null, 'updated_at'=>now()];
    if (!empty($d['password'])) $row['password_enc'] = Crypt::encryptString($d['password']);
    DB::table('settings')->updateOrInsert(['key'=>'cc_recording_on'], ['value'=>$r->boolean('recording_on') ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    $a = $this->acct(); if ($a) DB::table('sip_accounts')->where('id', $a->id)->update($row); else DB::table('sip_accounts')->insert($row + ['created_at'=>now()]);
    return back()->with('ok', 'SIP account saved');
  }
  /** Connection details for the signed-in admin's browser (never embedded in page HTML). */
  public function creds() {
    $a = $this->acct(); if (!$a || !$a->wss_url || !$a->password_enc) return response()->json(['ok'=>false, 'error'=>'SIP account is not set up yet.'], 200);
    try { $pw = Crypt::decryptString($a->password_enc); } catch (\Throwable $e) { return response()->json(['ok'=>false, 'error'=>'Saved SIP password could not be read. Please save the password again.']); }
    return response()->json(['ok'=>true, 'wss'=>$a->wss_url, 'user'=>$a->username, 'auth'=>$a->auth_username ?: $a->username, 'pw'=>$pw, 'domain'=>$a->domain, 'name'=>$a->display_name ?: ($a->caller_id ?: $a->username), 'prefix'=>$a->dial_prefix, 'stun'=>$a->stun_server, 'ext'=>$a->username, 'rec'=>$this->recOn(), 'staff'=>Auth::user()->name])->header('Cache-Control', 'no-store');
  }
  private function norm(string $v): string { $d = preg_replace('/\D/', '', $v); return preg_match('/(01\d{9})$/', $d, $m) ? $m[1] : $d; }
  private function cat(string $ph, int $n): string { try { $p = \App\Support\OrderDiscount::profile($ph); if (!empty($p['category'])) return (string)$p['category']; } catch (\Throwable $e) {} return $n > 0 ? 'Regular' : 'New'; }
  public function lookup(Request $r) {
    $ph = $this->norm((string)$r->query('phone', ''));
    if (strlen($ph) < 6) return response()->json(['found'=>false]);
    $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$ph])->first(); $n = DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->count();
    $last = DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->orderByDesc('id')->first(['id', 'invoice', 'status', 'total', 'customer_name']);
    $p = []; try { $p = \App\Support\OrderDiscount::profile($ph) ?: []; } catch (\Throwable $e) {}
    $fr = null; try { $f = DB::table('fraud_checks')->where('phone', $ph)->where('created_at', '>', now()->subDays(7))->orderByDesc('id')->first(); if ($f) { $L = \App\Support\FraudCheck::LEVELS[$f->risk_level] ?? null; $fr = ['level'=>$f->risk_level, 'label'=>$L[0] ?? $f->risk_level, 'color'=>$L[3] ?? '#4B5563', 'bg'=>$L[4] ?? '#F3F4F6', 'total'=>(int)$f->total]; } } catch (\Throwable $e) {}
    $calls = DB::table('call_logs')->whereRaw('right(phone,11) = ?', [$ph])->count();
    return response()->json(['found'=>(bool)($c || $n), 'phone'=>$ph, 'name'=>$c->name ?? ($last->customer_name ?? null), 'customer_id'=>$c->id ?? null, 'orders'=>$n, 'last'=>$last, 'category'=>$p['category'] ?? ($n > 0 ? 'Regular' : 'New'), 'membership'=>$p['membership'] ?? null, 'fraud'=>$fr, 'calls'=>$calls]);
  }
  public function contacts(Request $r) {
    $q = trim((string)$r->query('q', '')); $b = DB::table('customers'); $dg = preg_replace('/\D/', '', $q);
    if ($q !== '') $b->where(fn($w) => $w->where('name', 'ilike', '%'.$q.'%')->orWhere('phone', 'like', '%'.$dg.'%')->when(ctype_digit($q), fn($x) => $x->orWhere('id', (int)$q)));
    $rows = $b->orderByDesc('id')->limit(15)->get(['id', 'name', 'phone', 'membership'])->map(function ($c) { $n = DB::table('orders')->whereRaw('right(phone,11) = ?', [$this->norm((string)$c->phone)])->count(); return ['id'=>$c->id, 'name'=>$c->name, 'phone'=>$c->phone, 'orders'=>$n, 'category'=>$c->membership ?: ($n > 0 ? 'Regular' : 'New')]; });
    return response()->json($rows);
  }
  public function recent() {
    $rows = DB::table('call_logs')->orderByDesc('id')->limit(25)->get()->map(fn($l) => ['id'=>$l->id, 'dir'=>$l->direction, 'phone'=>$l->phone, 'name'=>$l->customer_name, 'status'=>$l->status, 'dur'=>(int)$l->duration, 'outcome'=>$l->outcome, 'rec'=>$l->recording_status ?? 'none',
      'at'=>$l->started_at ? \Carbon\Carbon::parse($l->started_at, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A') : '']);
    return response()->json($rows);
  }
  /** One call with everything the details popup needs. */
  public function detail($id) {
    $l = DB::table('call_logs')->find($id); abort_unless($l, 404); $tz = fn($v) => $v ? \Carbon\Carbon::parse($v, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i:s A') : null;
    $o = $l->order_id ? DB::table('orders')->where('id', $l->order_id)->first(['id', 'invoice', 'status']) : null;
    return response()->json(['id'=>$l->id, 'phone'=>$l->phone, 'name'=>$l->customer_name, 'customer_id'=>$l->customer_id, 'agent'=>$l->user_name, 'ext'=>$l->extension, 'dir'=>$l->direction, 'status'=>$l->status, 'dur'=>(int)$l->duration, 'dial'=>$tz($l->dial_started_at ?? $l->started_at), 'conn'=>$tz($l->connected_at ?? null), 'end'=>$tz($l->ended_at ?? null),
      'outcome'=>$l->outcome, 'response'=>$l->response, 'cancel'=>$l->cancel_reason, 'note'=>$l->note, 'rec'=>$l->recording_status ?? 'none', 'rec_url'=>$l->recording_url ?? null, 'order'=>$o]);
  }
  public function logStart(Request $r) {
    $d = $r->validate(['direction'=>'required|in:in,out', 'phone'=>'required|string|max:40', 'extension'=>'nullable|string|max:40']);
    $ph = $this->norm($d['phone']);
    $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$ph])->first(); $o = DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->orderByDesc('id')->first(['id', 'customer_name']);
    $id = DB::table('call_logs')->insertGetId(['direction'=>$d['direction'], 'phone'=>$ph ?: $d['phone'], 'customer_id'=>$c->id ?? null, 'customer_name'=>$c->name ?? ($o->customer_name ?? null), 'extension'=>$d['extension'] ?? null, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'started_at'=>now(), 'dial_started_at'=>now(), 'status'=>'failed', 'order_id'=>$o->id ?? null, 'recording_status'=>$this->recOn() ? 'recording' : 'none', 'created_at'=>now(), 'updated_at'=>now()]);
    return response()->json(['id'=>$id]);
  }
  private function recOn(): bool { return DB::table('settings')->where('key', 'cc_recording_on')->value('value') === '1'; }
  /** The call really connected: the talk-time clock starts here (not when dialing began). */
  public function logConnected(Request $r, $id) {
    $upd = ['connected_at'=>now(), 'status'=>'answered', 'updated_at'=>now()]; if ($r->filled('pbx_call_id')) $upd['pbx_call_id'] = mb_substr((string)$r->input('pbx_call_id'), 0, 120);
    DB::table('call_logs')->where('id', $id)->update($upd); return response()->json(['ok'=>true]);
  }
  public function logEnd(Request $r, $id) {
    $d = $r->validate(['status'=>'required|in:answered,missed,busy,failed,rejected', 'duration'=>'nullable|integer|min:0|max:86400', 'fail_reason'=>'nullable|string|max:160']);
    $l = DB::table('call_logs')->find($id); abort_unless($l, 404);
    $dur = $d['duration'] ?? 0; if ($l->connected_at ?? null) $dur = max($dur, (int)\Carbon\Carbon::parse($l->connected_at)->diffInSeconds(now()));
    $rec = ($d['status'] === 'answered' && $this->recOn()) ? 'processing' : 'none';
    DB::table('call_logs')->where('id', $id)->update(['status'=>$d['status'], 'duration'=>$dur, 'ended_at'=>now(), 'recording_status'=>($l->recording_url ?? null) ? 'available' : $rec, 'fail_reason'=>$d['fail_reason'] ?? null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'duration'=>$dur]);
  }
  /** Call Summary popup: outcome, customer response, cancel reason and note. */
  public function logSummary(Request $r, $id) {
    $d = $r->validate(['outcome'=>'nullable|in:confirmed,pending,call_later,no_answer,cancelled', 'response'=>'nullable|string|max:40', 'cancel_reason'=>'nullable|string|max:40', 'note'=>'nullable|string|max:1000', 'order_id'=>'nullable|integer']);
    $l = DB::table('call_logs')->find($id); abort_unless($l, 404);
    if (($d['outcome'] ?? '') === 'cancelled' && ($d['cancel_reason'] ?? '') === 'Other' && trim((string)($d['note'] ?? '')) === '') return response()->json(['ok'=>false, 'error'=>'Other কারণ হলে নোট লিখতে হবে।'], 422);
    $upd = ['outcome'=>$d['outcome'] ?? null, 'response'=>$d['response'] ?? null, 'cancel_reason'=>($d['outcome'] ?? '') === 'cancelled' ? ($d['cancel_reason'] ?? null) : null, 'summary_at'=>now(), 'updated_at'=>now()];
    if (array_key_exists('note', $d)) $upd['note'] = trim((string)$d['note']) ?: null;
    if (!empty($d['order_id']) && DB::table('orders')->where('id', $d['order_id'])->exists()) $upd['order_id'] = (int)$d['order_id'];
    DB::table('call_logs')->where('id', $id)->update($upd);
    $oid = $upd['order_id'] ?? $l->order_id;
    if ($oid && \Illuminate\Support\Facades\Schema::hasTable('order_staff_comments')) {
      $lab = ['confirmed'=>'Order Confirmed', 'pending'=>'Pending', 'call_later'=>'Call Later', 'no_answer'=>'No Answer', 'cancelled'=>'Order Cancelled'][$d['outcome'] ?? ''] ?? '—';
      $body = 'Call ('.($l->direction === 'in' ? 'incoming' : 'outgoing').', '.gmdate('i:s', (int)$l->duration).') — '.$lab.(!empty($d['response']) ? ' · '.$d['response'] : '').(!empty($upd['cancel_reason']) ? ' · '.$upd['cancel_reason'] : '').(!empty($upd['note']) ? ': '.$upd['note'] : '');
      DB::table('order_staff_comments')->insert(['order_id'=>$oid, 'user_id'=>Auth::id(), 'user_name'=>Auth::user()->name, 'category'=>'Call note', 'body'=>mb_substr($body, 0, 1000), 'created_at'=>now(), 'updated_at'=>now()]);
    }
    return response()->json(['ok'=>true]);
  }
  /** PBX integration / manual: attach the recording link for a call. */
  public function logRecording(Request $r, $id) {
    $d = $r->validate(['url'=>['required', 'url', 'max:600']]); DB::table('call_logs')->where('id', $id)->update(['recording_url'=>$d['url'], 'recording_status'=>'available', 'updated_at'=>now()]); return response()->json(['ok'=>true]);
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

  /** Rows for the Call History block inside Customer profile / Order details. */
  public static function callRows(?string $phone = null, ?int $orderId = null) {
    $q = DB::table('call_logs')->orderByDesc('id')->limit(60);
    if ($orderId) $q->where('order_id', $orderId); elseif ($phone) { $p = preg_replace('/\D/', '', $phone); $q->whereRaw('right(phone,11) = ?', [substr($p, -11)]); } else return collect();
    return $q->get();
  }
}
