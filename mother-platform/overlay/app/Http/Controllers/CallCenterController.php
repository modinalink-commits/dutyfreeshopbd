<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
/** Web softphone (SIP over WSS via the browser), call history and call notes. SIP password stays encrypted on the server and is only handed to the logged-in admin's browser when connecting. */
class CallCenterController extends Controller {
  /** Shared PBX server settings (WSS URL, domain, STUN, prefix). */
  private function acct() { return DB::table('sip_accounts')->whereNull('user_id')->orderBy('id')->first(); }
  /** The signed-in user's own extension. */
  private function mine() { return DB::table('sip_accounts')->where('user_id', Auth::id())->first(); }
  /** Agent availability (server-side): available | busy. Busy agents get no incoming calls; outbound stays allowed. */
  public static function agentStatus(int $uid): string {
    try { return (string)(DB::table('agent_availability')->where('user_id', $uid)->value('agent_status') ?: 'available'); } catch (\Throwable $e) { return 'available'; }
  }
  public static function availableAgents(): array {
    try { return DB::table('agent_availability')->where('agent_status', 'busy')->pluck('user_id')->all(); } catch (\Throwable $e) { return []; }
  }
  public function myStatus(Request $r) { return response()->json(['status' => self::agentStatus((int)$r->user()->id)]); }
  public function setStatus(Request $r) {
    $v = $r->input('status') === 'busy' ? 'busy' : 'available'; $uid = (int)$r->user()->id;
    try { DB::table('agent_availability')->updateOrInsert(['user_id' => $uid], ['agent_status' => $v, 'status_updated_at' => now(), 'updated_at' => now(), 'created_at' => now()]); } catch (\Throwable $e) { return response()->json(['ok' => false, 'status' => 'available'], 500); }
    return response()->json(['ok' => true, 'status' => $v]);
  }
  public function phone(Request $r) { return view('softphone', ['embed'=>$r->boolean('embed'), 'has'=>(bool)($this->mine()->username ?? null) && (bool)($this->acct()->wss_url ?? null), 'n'=>preg_replace('/[^0-9+*#]/', '', (string)$r->query('n', '')), 'agst'=>self::agentStatus((int)$r->user()->id), 'agname'=>(string)$r->user()->name]); }
  public function account() {
    $a = $this->acct(); return view('sip_account', ['a'=>$a, 'hasPw'=>(bool)($a->password_enc ?? null), 'pwPlain'=>self::pwPlain($a)]);
  }
  public function saveAccount(Request $r) {
    $d = $r->validate(['account_name'=>'nullable|string|max:120', 'sip_server'=>'nullable|string|max:255', 'wss_url'=>['nullable', 'string', 'max:400', 'regex:#^wss://[^\s]+$#i'], 'username'=>'required|string|max:120', 'auth_username'=>'nullable|string|max:120', 'password'=>'nullable|string|max:200', 'domain'=>'required|string|max:255', 'display_name'=>'nullable|string|max:120', 'outbound_proxy'=>'nullable|string|max:255', 'dial_prefix'=>'nullable|string|max:20', 'caller_id'=>'nullable|string|max:60', 'stun_server'=>'nullable|string|max:255'], ['wss_url.regex'=>'The WebSocket URL must start with wss:// (secure WebSocket), for example wss://pbx.example.com:8089/ws']);
    $nh = fn($v) => preg_replace('/:\d+$/', '', preg_replace('#^[a-z]+://#i', '', trim((string)$v))); $d['domain'] = $nh($d['domain']) ?: 'childcity.pbx.com.bd'; if (!empty($d['sip_server'])) $d['sip_server'] = $nh($d['sip_server']); if (empty($d['wss_url'])) $d['wss_url'] = 'wss://childcity.pbx.com.bd/wss/'; if (empty($d['stun_server'])) $d['stun_server'] = 'stun.l.google.com:19302';
    $row = ['account_name'=>$d['account_name'] ?? null, 'sip_server'=>$d['sip_server'] ?? null, 'wss_url'=>$d['wss_url'] ?? null, 'username'=>$d['username'], 'auth_username'=>$d['auth_username'] ?? null, 'domain'=>$d['domain'], 'display_name'=>$d['display_name'] ?? null, 'outbound_proxy'=>$d['outbound_proxy'] ?? null, 'dial_prefix'=>$d['dial_prefix'] ?? null, 'caller_id'=>$d['caller_id'] ?? null, 'stun_server'=>$d['stun_server'] ?? null, 'updated_at'=>now()];
    $rp = self::rawPost($r, 'password'); if ($rp !== null && $rp !== '') $row['password_enc'] = Crypt::encryptString($rp);
    DB::table('settings')->updateOrInsert(['key'=>'cc_recording_on'], ['value'=>$r->boolean('recording_on') ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    $a = $this->acct(); if ($a) DB::table('sip_accounts')->where('id', $a->id)->update($row); else DB::table('sip_accounts')->insert($row + ['created_at'=>now()]);
    return back()->with('ok', 'SIP account saved');
  }
  /** Connection details for the signed-in admin's browser (never embedded in page HTML). */
  public function creds() {
    $b = $this->acct(); $a = $this->mine();
    if (!$b || !$b->wss_url) return response()->json(['ok'=>false, 'error'=>'PBX server is not set up yet.'], 200);
    if (!$a || !$a->username || !$a->password_enc) return response()->json(['ok'=>false, 'error'=>'No extension is set for you. Ask an admin to add one in Call Center → Extensions.'], 200);
    try { $pw = Crypt::decryptString($a->password_enc); } catch (\Throwable $e) { return response()->json(['ok'=>false, 'error'=>'Saved extension password could not be read. Ask admin to save it again.']); }
    return response()->json(['ok'=>true, 'wss'=>$b->wss_url, 'user'=>$a->username, 'auth'=>$a->auth_username ?: $a->username, 'pw'=>$pw, 'domain'=>$b->domain, 'name'=>$a->display_name ?: Auth::user()->name, 'prefix'=>$b->dial_prefix, 'stun'=>$b->stun_server, 'ext'=>$a->username, 'rec'=>$this->recOn(), 'staff'=>Auth::user()->name])->header('Cache-Control', 'no-store');
  }
  /** Saved SIP password in plain text (admin-only screens). Empty string if none / unreadable. */
  public static function pwPlain($row): string { if (!$row || empty($row->password_enc)) return ''; try { return Crypt::decryptString($row->password_enc); } catch (\Throwable $e) { return ''; } }
  /** Raw posted value, byte-for-byte (Laravel's TrimStrings would strip spaces). */
  public static function rawPost(Request $r, string $k): ?string { return array_key_exists($k, $_POST) && is_string($_POST[$k]) ? $_POST[$k] : ($r->has($k) ? (string)$r->input($k) : null); }
  /** Used by the Admin-users form: set / change / remove the extension of one user. Returns an error text or null. */
  public static function setExt(int $uid, ?string $ext, ?string $pw): ?string {
    $ext = trim((string)$ext); $ex = DB::table('sip_accounts')->where('user_id', $uid)->first();
    if ($ext === '') return null;
    if (!preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $ext)) return 'Extension must be letters/numbers only.';
    if (DB::table('sip_accounts')->where('username', $ext)->whereNotNull('user_id')->where('user_id', '!=', $uid)->exists()) return 'Extension '.$ext.' is already given to another user.';
    $row = ['username'=>$ext, 'updated_at'=>now()];
    if ($pw !== null && $pw !== '') $row['password_enc'] = Crypt::encryptString($pw); elseif (!$ex || !$ex->password_enc) return 'Extension password is required.';
    if ($ex) DB::table('sip_accounts')->where('id', $ex->id)->update($row);
    else { $b = DB::table('sip_accounts')->whereNull('user_id')->orderBy('id')->first(); DB::table('sip_accounts')->insert($row + ['user_id'=>$uid, 'domain'=>$b->domain ?? '', 'created_at'=>now()]); }
    return null;
  }
  /** BACKUP calling path (no browser audio): the PBX rings the agent first (own mobile, else own extension), then bridges the customer. Same Event Socket + gateway the voice offers use. */
  public function backupCall(Request $r) {
    $d = $r->validate(['phone'=>'required|string|max:40']); $cust = \App\Support\Voice::norm($d['phone']);
    if (!\App\Support\Voice::validPhone($cust)) return response()->json(['ok'=>false, 'error'=>'কাস্টমারের নম্বর সঠিক নয় (01XXXXXXXXX)।']);
    $b = $this->acct(); $a = $this->mine(); $gw = \App\Support\Voice::set('gateway'); if (!$gw) return response()->json(['ok'=>false, 'error'=>'PBX গেটওয়ে সেট করা নেই।']);
    $mob = \App\Support\Voice::norm((string)(Auth::user()->phone ?? '')); $legA = \App\Support\Voice::validPhone($mob) ? 'sofia/gateway/'.$gw.'/'.\App\Support\Voice::dialNumber($mob) : (($a && $a->username) ? 'user/'.$a->username.'@'.($b->domain ?? '') : '');
    if ($legA === '') return response()->json(['ok'=>false, 'error'=>'আপনার মোবাইল নম্বর বা এক্সটেনশন সেট নেই।']);
    $cid = \App\Support\Voice::set('caller_id') ?: '0000';
    $cmd = 'bgapi originate {origination_caller_id_number='.$cid.',origination_caller_id_name=DutyFreeBD,originate_timeout=30,ignore_early_media=true,hangup_after_bridge=true}'.$legA.' &bridge({origination_caller_id_number='.$cid.',originate_timeout=40,ignore_early_media=true}sofia/gateway/'.$gw.'/'.\App\Support\Voice::dialNumber($cust).')';
    [$ok, $out] = \App\Support\Voice::esl($cmd, 15);
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? '', 'action'=>'Backup call '.($ok ? 'started' : 'failed'), 'subject'=>$cust.' via '.(str_starts_with($legA, 'user/') ? 'extension' : 'mobile').($ok ? '' : ' · '.mb_substr((string)$out, 0, 80)), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json(['ok'=>$ok, 'via'=>str_starts_with($legA, 'user/') ? 'ext' : 'mobile', 'error'=>$ok ? null : (string)$out]);
  }
  /** Agent scoreboard: who answers first, talks most and confirms most. */
  public function agents(Request $r) {
    $p = in_array($r->query('p'), ['today', '7', '30', 'all']) ? $r->query('p') : '7';
    $from = match ($p) { 'today' => now('Asia/Dhaka')->startOfDay()->utc(), '7' => now()->subDays(7), '30' => now()->subDays(30), default => null };
    $q = DB::table('call_logs')->whereNotNull('user_id')->when($from, fn($x) => $x->where('started_at', '>=', $from))->groupBy('user_id')
      ->selectRaw("user_id, max(user_name) as name,
        count(*) filter (where direction='in') as in_total,
        count(*) filter (where direction='in' and status='answered') as in_ans,
        count(*) filter (where direction='in' and status='answered' and connected_at is not null and dial_started_at is not null and extract(epoch from (connected_at - dial_started_at)) <= 10) as fast,
        avg(extract(epoch from (connected_at - dial_started_at))) filter (where direction='in' and status='answered' and connected_at is not null and dial_started_at is not null) as avg_ans,
        count(*) filter (where direction='out') as out_total,
        count(*) filter (where direction='out' and status='answered') as out_ans,
        coalesce(sum(duration) filter (where status='answered'), 0) as talk,
        count(*) filter (where outcome='confirmed') as conf,
        count(*) filter (where outcome='cancelled') as canc");
    $rows = $q->get()->map(function ($x) { $x->score = $x->in_ans * 3 + $x->fast * 2 + $x->out_ans + $x->conf * 5; return $x; })->sortByDesc('score')->values();
    return view('call_agents', ['rows'=>$rows, 'p'=>$p]);
  }
  /** Admin: one extension per user. */
  /** HTML chips (built server-side, no Blade logic) showing each extension's PBX registration state; empty when the relay is not reporting. */
  private static function regTags(array $reg): array {
    $o = []; foreach ($reg as $ext => $st) { $ok = $st === 'REGED'; $o[(string)$ext] = '<span title="PBX registration of the relay line" style="margin-left:6px;font-size:11px;font-weight:600;color:'.($ok ? '#047857' : '#B91C1C').'">&#9679; '.e($ok ? 'Registered' : ($st ?: 'Not registered')).'</span>'; }
    return $o;
  }
  public function extensions() {
    $users = DB::table('users')->orderBy('id')->get(['id', 'name', 'email', 'role', 'active']); $rows = DB::table('sip_accounts')->whereNotNull('user_id')->get()->keyBy('user_id');
    $used = $rows->pluck('username')->filter()->all(); $next = 101; while (in_array((string)$next, $used)) $next++;
    $allPool = DB::table('pbx_extensions')->orderBy('id')->get(); $pool = $allPool->where('company_id', 0)->values(); $companies = DB::table('pbx_companies')->orderBy('id')->get(); $poolBy = $allPool->where('company_id', '>', 0)->groupBy('company_id');
    // Which extensions the Call Clients relay uses (client calls) — same rule as the relay config; everything else is agent/staff.
    $relay = array_map('trim', preg_split('/[\s,]+/', (string)\App\Support\Voice::set('relay_client_exts', '*')));
    foreach (explode("\n", (string)\App\Support\Sms::secret('vc_relay_exts')) as $l) { $u = trim(explode(':', $l, 2)[0]); if ($u !== '') $relay[] = $u; }
    if (in_array('*', $relay, true)) { foreach ($rows as $rr) { if (!empty($rr->username)) $relay[] = (string)$rr->username; } foreach ($pool as $pp) { $relay[] = (string)$pp->extension; if (!empty($pp->auth_username)) $relay[] = (string)$pp->auth_username; } }
    $d = ['companies'=>$companies, 'poolBy'=>$poolBy, 'pbxs'=>[$this->acct() ?: (object)['account_name'=>'FusionPBX']], 'users'=>$users, 'rows'=>$rows, 'next'=>$next, 'pool'=>$pool, 'relay'=>array_values(array_filter(array_unique($relay)))];
    try { return response(view('sip_extensions', $d + ['regTag'=>self::regTags(\App\Http\Controllers\CarrierClientController::relayGwStates()), 'regErr'=>''])->render()); }
    catch (\Throwable $e) { \Log::error('extensions page: '.$e->getMessage()); return view('sip_extensions', $d + ['regTag'=>[], 'regErr'=>substr($e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 0, 300)]); }
  }
  public function saveExtension(Request $r, $uid) {
    abort_unless(DB::table('users')->where('id', $uid)->exists(), 404);
    $d = $r->validate(['username'=>'nullable|string|max:40|regex:/^[A-Za-z0-9_.-]+$/', 'auth_username'=>'nullable|string|max:60', 'display_name'=>'nullable|string|max:120', 'password'=>'nullable|string|max:200']);
    $rawPw = self::rawPost($r, 'password'); if ($rawPw !== null && mb_strlen($rawPw) > 200) return back()->withErrors(['password'=>'Password too long.']);
    $ex = DB::table('sip_accounts')->where('user_id', $uid)->first();
    if (empty($d['username'])) { if ($ex) DB::table('sip_accounts')->where('id', $ex->id)->delete(); return back()->with('ok', 'Extension removed'); }
    if (DB::table('sip_accounts')->where('username', $d['username'])->whereNotNull('user_id')->where('user_id', '!=', $uid)->exists()) return back()->withErrors(['username'=>'Extension '.$d['username'].' is already given to another user.']);
    $row = ['username'=>$d['username'], 'auth_username'=>$d['auth_username'] ?? null, 'display_name'=>$d['display_name'] ?? null, 'updated_at'=>now()];
    if ($rawPw !== null && $rawPw !== '') $row['password_enc'] = Crypt::encryptString($rawPw); elseif (!$ex || !$ex->password_enc) return back()->withErrors(['password'=>'Password is required for a new extension.']);
    if ($ex) DB::table('sip_accounts')->where('id', $ex->id)->update($row); else { $b = $this->acct(); DB::table('sip_accounts')->insert($row + ['user_id'=>$uid, 'domain'=>$b->domain ?? '', 'created_at'=>now()]); }
    try { if ($rawPw !== null && $rawPw !== '') DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'admin', 'action'=>'Extension password saved', 'subject'=>'ext '.$d['username'].' · user#'.$uid.' · '.mb_strlen($rawPw).' chars', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Extension saved — password '.($rawPw !== null && $rawPw !== '' ? 'updated ('.mb_strlen($rawPw).' characters)' : 'unchanged'));
  }
  /** Extension pool: one form, many rows. Blank extension on a saved row removes it; blank password keeps the old one. */
  /** Add another PBX company (its own PBX details + its own extension table). */
  public function addCompany(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120', 'sip_server'=>'nullable|string|max:255', 'wss_url'=>['nullable', 'string', 'max:400', 'regex:#^wss://[^\s]+$#i'], 'domain'=>'nullable|string|max:255']);
    $id = DB::table('pbx_companies')->insertGetId(['name'=>trim($d['name']), 'sip_server'=>trim((string)($d['sip_server'] ?? '')) ?: null, 'wss_url'=>trim((string)($d['wss_url'] ?? '')) ?: null, 'domain'=>trim((string)($d['domain'] ?? '')) ?: null, 'created_at'=>now(), 'updated_at'=>now()]);
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'admin', 'action'=>'PBX company added', 'subject'=>'#'.$id.' '.$d['name'], 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Company added. Add its extensions in its table.');
  }
  public function deleteCompany(Request $r, $id) {
    $id = (int)$id; if (DB::table('pbx_extensions')->where('company_id', $id)->exists()) return back()->withErrors(['Remove this company\'s extensions first.']);
    DB::table('pbx_companies')->where('id', $id)->delete();
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'admin', 'action'=>'PBX company deleted', 'subject'=>'#'.$id, 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Company deleted.');
  }
  public function savePool(Request $r) {
    $in = $r->input('rows', []); if (!is_array($in)) $in = []; $raw = $_POST['rows'] ?? []; $err = []; $seen = []; $n = 0;
    $cid = (int)$r->input('company_id', 0); if ($cid > 0 && !DB::table('pbx_companies')->where('id', $cid)->exists()) return back()->withErrors(['Company not found.']);
    $userExt = DB::table('sip_accounts')->whereNotNull('user_id')->pluck('username')->map(fn($v) => (string)$v)->all();
    foreach (array_slice($in, 0, 60, true) as $i => $row) {
      $id = (int)($row['id'] ?? 0); $ex = trim((string)($row['extension'] ?? '')); $cur = $id ? DB::table('pbx_extensions')->where('company_id', $cid)->where('id', $id)->first() : null;
      if ($ex === '') { if ($cur) { DB::table('pbx_extensions')->where('id', $id)->delete(); $n++; } continue; }
      $line = 'Row '.((int)$i + 1).': ';
      if (!preg_match('/^[A-Za-z0-9_.-]{1,40}$/', $ex)) { $err[] = $line.'extension must be letters/numbers only.'; continue; }
      if (isset($seen[$ex]) || ($cid === 0 && in_array($ex, $userExt, true))) { $err[] = $line.'extension '.$ex.' is already in use.'; continue; }
      $seen[$ex] = 1;
      $pw = (isset($raw[$i]['password']) && is_string($raw[$i]['password'])) ? $raw[$i]['password'] : (string)($row['password'] ?? '');
      if (mb_strlen($pw) > 200) { $err[] = $line.'password too long.'; continue; }
      if ($pw === '' && (!$cur || !$cur->password_enc)) { $err[] = $line.'password is required.'; continue; }
      if (DB::table('pbx_extensions')->where('company_id', $cid)->where('extension', $ex)->when($cur, fn($q) => $q->where('id', '!=', $cur->id))->exists()) { $err[] = $line.'extension '.$ex.' already exists.'; continue; }
      $d = ['extension'=>$ex, 'company_id'=>$cid, 'auth_username'=>trim((string)($row['auth_username'] ?? '')) ?: null, 'display_name'=>trim((string)($row['display_name'] ?? '')) ?: null, 'updated_at'=>now()];
      if ($pw !== '') $d['password_enc'] = Crypt::encryptString($pw);
      if ($cur) DB::table('pbx_extensions')->where('id', $cur->id)->update($d); else DB::table('pbx_extensions')->insert($d + ['created_at'=>now()]);
      $n++;
    }
    try { if ($n) DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? 'admin', 'action'=>'Extension pool saved', 'subject'=>$n.' row(s) changed', 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    $back = back()->with('ok', $n.' extension row(s) saved.');
    return $err ? $back->withErrors($err) : $back;
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
    $dl = 0; $th = null;
    try { $dl = $n ? (int)DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->where('status', 'Delivered')->count() : 0; } catch (\Throwable $e) {}
    try { $th = DB::table('chat_threads')->whereRaw('right(phone,11) = ?', [$ph])->orderByDesc('id')->value('id'); } catch (\Throwable $e) {}
    return response()->json(['found'=>(bool)($c || $n), 'delivered'=>$dl, 'thread_id'=>$th ? (int)$th : null, 'phone'=>$ph, 'name'=>$c->name ?? ($last->customer_name ?? null), 'customer_id'=>$c->id ?? null, 'orders'=>$n, 'last'=>$last, 'category'=>$p['category'] ?? ($n > 0 ? 'Regular' : 'New'), 'membership'=>$p['membership'] ?? null, 'fraud'=>$fr, 'calls'=>$calls]);
  }
  public function contacts(Request $r) {
    $q = trim((string)$r->query('q', '')); $b = DB::table('customers'); $dg = preg_replace('/\D/', '', $q);
    if ($q !== '') $b->where(fn($w) => $w->where('name', 'ilike', '%'.$q.'%')->orWhere('phone', 'like', '%'.$dg.'%')->when(ctype_digit($q), fn($x) => $x->orWhere('id', (int)$q)));
    $rows = $b->orderByDesc('id')->limit(15)->get(['id', 'name', 'phone', 'membership'])->map(function ($c) { $n = DB::table('orders')->whereRaw('right(phone,11) = ?', [$this->norm((string)$c->phone)])->count(); return ['id'=>$c->id, 'name'=>$c->name, 'phone'=>$c->phone, 'orders'=>$n, 'category'=>$c->membership ?: ($n > 0 ? 'Regular' : 'New')]; });
    return response()->json($rows);
  }
  /** Dialer "Up next": who should be called now. New orders nobody has called yet, customers who asked for a call back, orders whose call was not answered, and missed incoming calls nobody returned.
   *  Read-only list built from orders + call_logs; it never changes an order. The staff member still decides and presses Call. */
  public function upNext() {
    $rows = \Illuminate\Support\Facades\Cache::remember('cc.upnext', 20, function () {
      $out = [];
      try {
        $since = now()->subDays(2); $last = [];
        foreach (DB::table('call_logs')->where('started_at', '>=', $since)->orderBy('id')->get(['id', 'direction', 'phone', 'customer_name', 'status', 'outcome', 'note', 'started_at', 'user_name']) as $c) $last[$this->norm((string)$c->phone)] = $c;
        $mins = fn($ts) => max(0, (int)\Carbon\Carbon::parse($ts)->diffInMinutes(now())); $seen = [];
        $orders = DB::table('orders')->where('status', 'New')->where('created_at', '<', now()->subMinutes(10))->where('created_at', '>', $since)->orderBy('id')->limit(60)->get(['id', 'invoice', 'phone', 'customer_name', 'created_at', 'pay_status']);
        foreach ($orders as $o) {
          $p = $this->norm((string)$o->phone); if (strlen($p) < 10 || isset($seen[$p]) || (string)($o->pay_status ?? '') === 'Awaiting Payment') continue;
          $lc = $last[$p] ?? null; $after = $lc && \Carbon\Carbon::parse($lc->started_at)->gte(\Carbon\Carbon::parse($o->created_at));
          $base = ['name'=>(string)($o->customer_name ?: ''), 'phone'=>$p, 'invoice'=>\App\Support\Inv::show($o->invoice), 'order_id'=>(int)$o->id, 'note'=>''];
          $seen[$p] = 1;
          if (!$after) { $out[] = $base + ['type'=>'new', 'mins'=>$mins($o->created_at), 'pr'=>2]; continue; }
          if (in_array((string)$lc->outcome, ['confirmed', 'cancelled', 'pending'], true)) continue;
          if ((string)$lc->outcome === 'call_later') { $out[] = ['note'=>mb_substr((string)($lc->note ?? ''), 0, 80)] + $base + ['type'=>'callback', 'mins'=>$mins($lc->started_at), 'pr'=>3]; continue; }
          if ($mins($lc->started_at) >= 20) $out[] = $base + ['type'=>'noanswer', 'mins'=>$mins($lc->started_at), 'pr'=>4];
        }
        foreach ($last as $p => $lc) {
          $p = (string)$p; if (isset($seen[$p]) || strlen($p) < 10) continue;
          if ((string)$lc->outcome === 'call_later') $out[] = ['type'=>'callback', 'name'=>(string)($lc->customer_name ?: ''), 'phone'=>$p, 'invoice'=>'', 'order_id'=>null, 'note'=>mb_substr((string)($lc->note ?? ''), 0, 80), 'mins'=>$mins($lc->started_at), 'pr'=>3];
          elseif ($lc->direction === 'in' && $lc->status === 'missed' && \Carbon\Carbon::parse($lc->started_at)->gt(now()->subDay())) $out[] = ['type'=>'missed', 'name'=>(string)($lc->customer_name ?: ''), 'phone'=>$p, 'invoice'=>'', 'order_id'=>null, 'note'=>'', 'mins'=>$mins($lc->started_at), 'pr'=>1];
        }
        usort($out, fn($a, $b) => [$a['pr'], -$a['mins']] <=> [$b['pr'], -$b['mins']]);
      } catch (\Throwable $e) { report($e); }
      return array_slice($out, 0, 25);
    });
    return response()->json(['items'=>$rows, 'count'=>count($rows)]);
  }
  public function recent() {
    $rows = DB::table('call_logs')->orderByDesc('id')->limit(25)->get()->map(fn($l) => ['id'=>$l->id, 'dir'=>$l->direction, 'phone'=>$l->phone, 'name'=>$l->customer_name, 'status'=>$l->status, 'dur'=>(int)$l->duration, 'outcome'=>$l->outcome, 'rec'=>$l->recording_status ?? 'none',
      'at'=>$l->started_at ? \Carbon\Carbon::parse($l->started_at, 'UTC')->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '']);
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
    if (($d['outcome'] ?? '') === 'cancelled' && ($d['cancel_reason'] ?? '') === 'Other' && trim((string)($d['note'] ?? '')) === '') return response()->json(['ok'=>false, 'error'=>'Write a note when the reason is Other.'], 422);
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
    $f = (string)$r->query('f', 'all'); $q = trim((string)$r->query('q', '')); $view = $r->query('view', 'list'); $d = in_array($r->query('d'), ['in', 'out'], true) ? (string)$r->query('d') : '';
    if (!in_array($f, ['all', 'answered', 'missed', 'busy', 'failed', 'rejected', 'noback'], true)) $f = 'all';
    // "Not called back": a missed incoming call with no later outgoing call to the same number
    $noBack = fn($w) => $w->where('call_logs.direction', 'in')->where('call_logs.status', 'missed')->whereNotExists(fn($x) => $x->selectRaw('1')->from('call_logs as c2')->whereRaw('right(c2.phone,11) = right(call_logs.phone,11)')->where('c2.direction', 'out')->whereColumn('c2.id', '>', 'call_logs.id'));
    $base = DB::table('call_logs')->when($f === 'noback', $noBack)->when(!in_array($f, ['all', 'noback'], true), fn($w) => $w->where('status', $f))->when($d !== '', fn($w) => $w->where('direction', $d))
      ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('phone', 'like', '%'.$q.'%')->orWhere('customer_name', 'ilike', '%'.$q.'%')->orWhere('user_name', 'ilike', '%'.$q.'%')->orWhere('note', 'ilike', '%'.$q.'%')));
    $rows = (clone $base)->orderByDesc('id')->paginate(\App\Support\Pg::per(24))->withQueryString();
    $kpi = null; $noback = 0; $back = [];
    if ($view !== 'report') {
      try {
        $ms = now('Asia/Dhaka')->startOfMonth()->utc();
        $kpi = DB::table('call_logs')->where('created_at', '>=', $ms)->selectRaw("count(*) n, sum(case when status='answered' then 1 else 0 end) ans, sum(case when status='missed' then 1 else 0 end) mis, coalesce(sum(duration),0) dur, coalesce(avg(case when status='answered' then duration end),0) avgd, sum(case when direction='in' then 1 else 0 end) inc")->first();
        $noback = (int)DB::table('call_logs')->where('created_at', '>=', now()->subDays(7))->where($noBack)->count();
        $miss = collect($rows->items())->filter(fn($x) => $x->direction === 'in' && $x->status === 'missed');
        if ($miss->isNotEmpty()) {
          $ph = $miss->map(fn($x) => substr(preg_replace('/\D/', '', (string)$x->phone), -11))->unique()->values()->all();
          $outs = DB::table('call_logs')->where('direction', 'out')->where('id', '>', $miss->min('id'))->whereIn(DB::raw('right(phone,11)'), $ph)->orderBy('id')->get(['id', 'phone', 'user_name', 'started_at']);
          foreach ($miss as $m) { $p = substr(preg_replace('/\D/', '', (string)$m->phone), -11); $o = $outs->first(fn($x) => $x->id > $m->id && substr(preg_replace('/\D/', '', (string)$x->phone), -11) === $p); if ($o) $back[$m->id] = trim(($o->user_name ?: '').' · '.\Carbon\Carbon::parse($o->started_at)->timezone('Asia/Dhaka')->format('d M, h:i A'), ' ·'); }
        }
      } catch (\Throwable $e) { report($e); }
    }
    $rep = null;
    if ($view === 'report') {
      $from = now()->subDays(29)->startOfDay();
      $rep = ['tot'=>DB::table('call_logs')->where('created_at', '>=', $from)->selectRaw("count(*) n, sum(case when status='answered' then 1 else 0 end) ans, sum(case when status='missed' then 1 else 0 end) mis, coalesce(sum(duration),0) dur, coalesce(avg(case when status='answered' then duration end),0) avgd, sum(case when direction='in' then 1 else 0 end) inc")->first(),
        'staff'=>DB::table('call_logs')->where('created_at', '>=', $from)->selectRaw("coalesce(user_name,'—') u, count(*) n, sum(case when status='answered' then 1 else 0 end) ans, coalesce(sum(duration),0) dur")->groupBy('user_name')->orderByDesc('n')->get(),
        'days'=>DB::table('call_logs')->where('created_at', '>=', $from)->selectRaw("to_char(created_at at time zone 'Asia/Dhaka','DD Mon') d, count(*) n, sum(case when status='answered' then 1 else 0 end) ans")->groupByRaw("to_char(created_at at time zone 'Asia/Dhaka','DD Mon'), date_trunc('day', created_at at time zone 'Asia/Dhaka')")->orderByRaw("date_trunc('day', created_at at time zone 'Asia/Dhaka') desc")->limit(14)->get()];
    }
    return view('call_history', compact('rows', 'f', 'q', 'view', 'rep', 'd', 'kpi', 'noback', 'back'));
  }

  /** Rows for the Call History block inside Customer profile / Order details. */
  public static function callRows(?string $phone = null, ?int $orderId = null) {
    $q = DB::table('call_logs')->orderByDesc('id')->limit(60);
    if ($orderId) $q->where('order_id', $orderId); elseif ($phone) { $p = preg_replace('/\D/', '', $phone); $q->whereRaw('right(phone,11) = ?', [substr($p, -11)]); } else return collect();
    return $q->get();
  }
}
