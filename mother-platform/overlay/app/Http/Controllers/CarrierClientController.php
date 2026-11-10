<?php
namespace App\Http\Controllers;
use App\Support\Voice;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Call Center > Call Clients (TEST MODE). Admin adds a client's IP, port and prefix; the page generates the client document and the FreeSWITCH config. It never pushes anything to the PBX. */
class CarrierClientController extends Controller {
  private function guard() { abort_unless(\App\Support\ProfitFloor::admin(), 403); }
  private static function set(string $k, string $v) { DB::table('settings')->updateOrInsert(['key' => 'vc_' . $k], ['value' => $v]); }
  public function index() {
    $this->guard();
    $rows = DB::table('voice_carrier_clients')->orderBy('id')->get();
    $gw = Voice::set('gateway', '');
    $cfg = ['ip' => Voice::set('carrier_ip', ''), 'port' => Voice::set('carrier_port', '5060'), 'gateway' => $gw];
    $exts = array_filter(array_map('trim', explode("\n", (string)Sms::secret('vc_relay_exts')))); $names = array_map(fn($l) => explode(':', $l, 2)[0], $exts);
    $relay = ['host' => Voice::set('relay_host', '103.125.255.33'), 'port' => Voice::set('relay_port', '5080'), 'transport' => Voice::set('relay_transport', 'tcp'), 'realm' => Voice::set('relay_realm', ''), 'caller_id' => Voice::set('relay_caller_id', ''), 'mode' => Voice::set('relay_mode', 'test'), 'exts' => implode("\n", $names)];
    return view('carrier_clients', ['rows' => $rows, 'cfg' => $cfg, 'relay' => $relay, 'pbx' => self::pbxConfig($rows, $gw), 'usage' => $rows->mapWithKeys(fn($c) => [$c->id => self::usage($c)])->all()]);
  }
  /** Billing cycle window containing today: starts at cycle_start (or the day the client was added) and repeats every cycle_days. */
  public static function cycleWindow($c): array {
    $days = max(1, (int)($c->cycle_days ?? 30)); $tz = 'Asia/Dhaka';
    $start = \Illuminate\Support\Carbon::parse($c->cycle_start ?: ($c->created_at ?? now($tz)), $tz)->startOfDay();
    $now = now($tz);
    if ($now->greaterThanOrEqualTo($start)) $start = $start->copy()->addDays(intdiv((int)$start->diffInDays($now), $days) * $days);
    return [$start, $start->copy()->addDays($days)];
  }
  /** Connected (answered) call usage for the client's current cycle, from relay CDRs. Billed minutes round each call up to a whole minute. */
  public static function usage($c): array {
    [$from, $to] = self::cycleWindow($c);
    $r = DB::table('voice_call_cdrs')->where('client_id', $c->id)->where('ended_at', '>=', $from->copy()->utc())->where('ended_at', '<', $to->copy()->utc())
      ->selectRaw('COUNT(*) AS calls, COALESCE(SUM(billsec),0) AS secs, COALESCE(SUM(CEIL(billsec / 60.0)),0) AS bmin')->first();
    $billed = (int)$r->bmin; $pre = $c->prepaid_minutes === null ? null : (int)$c->prepaid_minutes;
    return ['from' => $from->format('d M Y'), 'to' => $to->copy()->subDay()->format('d M Y'), 'calls' => (int)$r->calls, 'secs' => (int)$r->secs, 'billed_min' => $billed,
      'prepaid' => $pre, 'remaining' => $pre === null ? null : $pre - $billed, 'amount' => $c->rate_per_min === null ? null : round($billed * (float)$c->rate_per_min, 2)];
  }
  /** Live-refresh JSON for the billing card. */
  public function billing() {
    $this->guard();
    $out = []; foreach (DB::table('voice_carrier_clients')->orderBy('id')->get() as $c) $out[$c->id] = self::usage($c);
    return response()->json(['ok' => true, 'usage' => $out, 'at' => now('Asia/Dhaka')->format('H:i:s')]);
  }
  /** Connected minutes between two Dhaka date-times (any client), for the filter on the billing card. */
  public function billingRange(Request $r) {
    $this->guard();
    $d = $r->validate(['from' => 'required|date', 'to' => 'required|date']);
    $tz = 'Asia/Dhaka'; $f = \Illuminate\Support\Carbon::parse($d['from'], $tz); $t = \Illuminate\Support\Carbon::parse($d['to'], $tz);
    if ($t->lessThanOrEqualTo($f)) return response()->json(['ok' => false, 'msg' => 'End time must be after start time'], 422);
    $rows = DB::table('voice_call_cdrs')->where('ended_at', '>=', $f->copy()->utc())->where('ended_at', '<', $t->copy()->utc())->groupBy('client_id')
      ->selectRaw('client_id, COUNT(*) AS calls, COALESCE(SUM(billsec),0) AS secs, COALESCE(SUM(CEIL(billsec / 60.0)),0) AS bmin')->get();
    $names = DB::table('voice_carrier_clients')->pluck('name', 'id');
    $out = $rows->map(fn($x) => ['client' => $names[$x->client_id] ?? '—', 'calls' => (int)$x->calls, 'secs' => (int)$x->secs, 'billed_min' => (int)$x->bmin])->values();
    return response()->json(['ok' => true, 'rows' => $out, 'calls' => (int)$out->sum('calls'), 'secs' => (int)$out->sum('secs'), 'billed_min' => (int)$out->sum('billed_min')]);
  }
  public function billingSave(Request $r, $id) {
    $this->guard(); abort_unless(DB::table('voice_carrier_clients')->where('id', $id)->exists(), 404);
    $d = $r->validate(['cycle_start' => 'nullable|date', 'cycle_days' => 'required|integer|min:1|max:366', 'prepaid_minutes' => 'nullable|integer|min:0|max:100000000', 'rate_per_min' => 'nullable|numeric|min:0|max:100000']);
    DB::table('voice_carrier_clients')->where('id', $id)->update(['cycle_start' => $d['cycle_start'] ?: null, 'cycle_days' => (int)$d['cycle_days'],
      'prepaid_minutes' => ($d['prepaid_minutes'] ?? '') === '' ? null : (int)$d['prepaid_minutes'], 'rate_per_min' => ($d['rate_per_min'] ?? '') === '' ? null : $d['rate_per_min'], 'updated_at' => now()]);
    \App\Support\ProfitFloor::audit('voice.client_billing', 'Call client #' . $id . ' billing cycle saved (' . $d['cycle_days'] . ' days, prepaid ' . ($d['prepaid_minutes'] ?? '-') . ' min)', null, ['id' => $id]);
    return back()->with('ok', 'Billing cycle saved.');
  }
  /** Public, token-protected: the relay reports each answered call here (uuid, billable seconds, dialled digits). Idempotent per uuid. */
  public function relayCdr(Request $r) {
    $tok = (string)Sms::secret('vc_relay_token'); $given = (string)$r->bearerToken();
    if ($tok === '' || !hash_equals($tok, $given)) return response('forbidden', 403)->header('Content-Type', 'text/plain');
    $d = $r->validate(['uuid' => ['required', 'regex:/^[0-9a-fA-F-]{8,40}$/'], 'billsec' => 'required|integer|min:1|max:86400', 'dest' => ['required', 'regex:/^\d{6,20}$/']]);
    $client = null;
    foreach (DB::table('voice_carrier_clients')->orderByRaw('LENGTH(prefix) DESC')->get() as $c) if (str_starts_with($d['dest'], $c->prefix)) { $client = $c; break; }
    $client = $client ?: DB::table('voice_carrier_clients')->where('status', 'active')->orderBy('id')->first();
    DB::table('voice_call_cdrs')->insertOrIgnore(['uuid' => strtolower($d['uuid']), 'client_id' => $client->id ?? null, 'dest' => $d['dest'], 'billsec' => (int)$d['billsec'], 'ended_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    return response('ok', 200)->header('Content-Type', 'text/plain');
  }
  public static function pbxConfig($rows, string $gw): string {
    $act = $rows->where('status', 'active');
    if ($act->isEmpty()) return '(no active clients)';
    $acl = '<list name="vos_clients" default="deny">' . "\n";
    foreach ($act->pluck('ip')->unique() as $ip) $acl .= '  <node type="allow" cidr="' . (str_contains($ip, '/') ? $ip : $ip . '/32') . '"/>' . "\n";
    $acl .= "</list>   <!-- autoload_configs/acl.conf.xml → <network-lists> -->\n\n";
    $dp = '';
    foreach ($act as $c) {
      $ip = explode('/', $c->ip)[0];
      $dp .= '<extension name="client_' . $c->id . '_' . preg_replace('/\W+/', '_', $c->name) . "\">\n"
        . '  <condition field="network_addr" expression="^' . preg_quote($ip) . '$"/>' . "\n"
        . '  <condition field="destination_number" expression="^' . $c->prefix . '(0?1\d{9})$">' . "\n"
        . '    <action application="limit" data="hash vos_in ' . $ip . ' ' . (int)$c->max_calls . ' !USER_BUSY"/>' . "\n"
        . '    <action application="set" data="effective_caller_id_number=${caller_id_number}"/>' . "\n"
        . '    <action application="bridge" data="sofia/gateway/' . ($gw ?: '<GATEWAY_UUID>') . '/$1"/>' . "\n"
        . "  </condition>\n</extension>\n";
    }
    return $acl . $dp;
  }
  public static function document(array $c, array $cfg): string {
    $ip = $cfg['ip'] ?: '<our server IP>'; $port = $cfg['port'] ?: '5060';
    return "Call Termination Details — DutyFreeBD\n"
      . "Client            : {$c['name']}\n"
      . "Our SIP IP        : {$ip}\n"
      . "Our SIP Port      : {$port} (UDP)\n"
      . "Your IP (allowed) : {$c['ip']}  port {$c['port']}\n"
      . "Authentication    : IP based (no username/password)\n"
      . "Tech Prefix       : {$c['prefix']}\n"
      . "Dial format       : {$c['prefix']} + 01XXXXXXXXX   (example: {$c['prefix']}01712345678)\n"
      . "Codec             : G.729 / G.711a (PCMA) / G.711u (PCMU)\n"
      . "DTMF              : RFC2833\n"
      . "Max concurrent    : {$c['max_calls']} calls\n"
      . "Route             : Bangladesh mobile (01X) only\n"
      . "Note              : Calls from any other IP or without this prefix are rejected.";
  }
  private function rules(Request $r, ?int $id = null): array {
    $d = $r->validate([
      'name' => 'required|string|max:120', 'ip' => ['required', 'string', 'max:64', 'regex:/^(\d{1,3}\.){3}\d{1,3}(\/(\d|[12]\d|3[0-2]))?$/'],
      'port' => 'required|integer|min:1|max:65535', 'prefix' => ['required', 'regex:/^\d{2,10}$/', 'unique:voice_carrier_clients,prefix' . ($id ? ',' . $id : '')],
      'max_calls' => 'required|integer|min:1|max:1000', 'note' => 'nullable|string|max:300']);
    foreach (explode('/', explode('/', $d['ip'])[0])[0] === '' ? [] : explode('.', explode('/', $d['ip'])[0]) as $o) if ((int)$o > 255) abort(422, 'Invalid IP.');
    return $d;
  }
  public function store(Request $r) {
    $this->guard(); $d = $this->rules($r); $d['created_by'] = $r->user()->id; $d['created_at'] = $d['updated_at'] = now();
    $id = DB::table('voice_carrier_clients')->insertGetId($d);
    \App\Support\ProfitFloor::audit('voice.client_added', 'Call client ' . $d['name'] . ' ' . $d['ip'] . ' prefix ' . $d['prefix'], null, ['id' => $id]);
    return back()->with('ok', 'Client added.');
  }
  public function update(Request $r, $id) {
    $this->guard(); abort_unless(DB::table('voice_carrier_clients')->where('id', $id)->exists(), 404);
    $d = $this->rules($r, (int)$id); $d['updated_at'] = now();
    DB::table('voice_carrier_clients')->where('id', $id)->update($d);
    \App\Support\ProfitFloor::audit('voice.client_updated', 'Call client #' . $id . ' updated', null, ['id' => $id]);
    return back()->with('ok', 'Saved.');
  }
  public function toggle($id) {
    $this->guard(); $c = DB::table('voice_carrier_clients')->where('id', $id)->first(); abort_unless($c, 404);
    $n = $c->status === 'active' ? 'paused' : 'active';
    DB::table('voice_carrier_clients')->where('id', $id)->update(['status' => $n, 'updated_at' => now()]);
    \App\Support\ProfitFloor::audit('voice.client_' . $n, 'Call client #' . $id . ' ' . $n, null, ['id' => $id]);
    return back()->with('ok', $n === 'active' ? 'Client switched ON.' : 'Client switched OFF.');
  }
  public function destroy($id) {
    $this->guard(); DB::table('voice_carrier_clients')->where('id', $id)->delete();
    \App\Support\ProfitFloor::audit('voice.client_deleted', 'Call client #' . $id . ' deleted', null, ['id' => $id]);
    return back()->with('ok', 'Client deleted.');
  }
  /** Live calls from the PBX (Event Socket "show channels"), grouped by client IP. Read-only. */
  public function live() {
    $this->guard();
    [$ok, $out] = Voice::esl('show channels as json', 8);
    if (!$ok) return response()->json(['ok' => false, 'error' => $out]);
    $j = json_decode($out, true); $rows = is_array($j) ? ($j['rows'] ?? []) : [];
    $cl = DB::table('voice_carrier_clients')->get(); $byIp = [];
    foreach ($cl as $c) $byIp[explode('/', $c->ip)[0]] = $c;
    $calls = []; $per = []; $unk = [];
    foreach ($rows as $r) {
      if (($r['direction'] ?? '') !== 'inbound') continue;
      $c = $byIp[$r['ip_addr'] ?? ''] ?? null; if (!$c) { if (count($unk) < 20) $unk[] = ['ip' => $r['ip_addr'] ?? '', 'to' => $r['dest'] ?? '', 'from' => $r['cid_num'] ?? '', 'state' => $r['callstate'] ?? '']; continue; }
      $dest = preg_replace('/^' . preg_quote($c->prefix, '/') . '/', '', (string)($r['dest'] ?? ''));
      // Duration counts only after the customer answers (callstate ACTIVE); ringing shows no timer. First ACTIVE sighting is remembered per call.
      $age = null;
      if (strtoupper((string)($r['callstate'] ?? '')) === 'ACTIVE' && !empty($r['uuid'])) {
        $k = 'vc_ans_' . preg_replace('/[^0-9a-f-]/i', '', (string)$r['uuid']);
        $t0 = \Illuminate\Support\Facades\Cache::get($k); if (!$t0) { $t0 = time(); \Illuminate\Support\Facades\Cache::put($k, $t0, 21600); }
        $age = max(0, time() - (int)$t0);
      }
      $calls[] = ['client' => $c->name, 'from' => $r['cid_num'] ?? '', 'to' => $dest, 'state' => $r['callstate'] ?? '', 'secs' => $age];
      $per[$c->id] = ($per[$c->id] ?? 0) + 1;
    }
    return response()->json(['ok' => true, 'total' => count($calls), 'calls' => array_slice($calls, 0, 100), 'per' => $per, 'channels' => count($rows), 'unknown' => $unk, 'at' => now('Asia/Dhaka')->format('H:i:s')]);
  }
  /** Read-only PBX diagnostics (whitelisted Event Socket commands). */
  public function diag() {
    $this->guard(); $o = [];
    foreach (['sofia status', 'sofia xmlstatus profile external', 'status'] as $cmd) { [$ok, $t] = Voice::esl($cmd, 8); if ($ok && str_contains($cmd, 'xmlstatus')) { $k = []; foreach (['calls-in', 'calls-out', 'failed-calls-in', 'failed-calls-out', 'bind-url', 'ext-sip-ip', 'sip-port', 'auth-calls', 'apply-inbound-acl', 'context', 'dialplan'] as $tag) if (preg_match('/<' . $tag . '>([^<]*)<\/' . $tag . '>/', $t, $m)) $k[$tag] = $m[1]; $o[$cmd] = $k; } else $o[$cmd] = $ok ? mb_substr($t, 0, 1500) : 'ERR: ' . $t; }
    $tp = []; foreach ([5060, 5061, 5080, 5081, 8090, 7443, 443, 8021] as $pt) { $f = @stream_socket_client('tcp://103.125.255.33:' . $pt, $e1, $e2, 3); $tp[$pt] = $f ? 'OPEN' : 'closed'; if ($f) fclose($f); } $o['tcp_ports'] = $tp;
    $o['sip_probe_5080'] = self::sipProbe('103.125.255.33', 5080); $o['sip_probe_5060'] = self::sipProbe('103.125.255.33', 5060);
    return response()->json($o);
  }
  /** SIP OPTIONS ping over UDP from this server to a host:port; shows whether the PBX answers us. */
  private static function sipProbe(string $h, int $port): string {
    $fp = @stream_socket_client('udp://' . $h . ':' . $port, $en, $es, 3); if (!$fp) return 'ERR ' . $es;
    $id = bin2hex(random_bytes(6)); $msg = "OPTIONS sip:$h:$port SIP/2.0\r\nVia: SIP/2.0/UDP 0.0.0.0:5099;branch=z9hG4bK$id\r\nMax-Forwards: 70\r\nFrom: <sip:probe@dutyfreebd.com>;tag=$id\r\nTo: <sip:$h:$port>\r\nCall-ID: $id@probe\r\nCSeq: 1 OPTIONS\r\nContent-Length: 0\r\n\r\n";
    fwrite($fp, $msg); stream_set_timeout($fp, 3); $r = fread($fp, 1500); fclose($fp);
    return $r === '' || $r === false ? 'NO RESPONSE (3s)' : strtok($r, "\r\n");
  }
  public function saveEndpoint(Request $r) {
    $this->guard(); $d = $r->validate(['ip' => ['required', 'regex:/^[A-Za-z0-9.\-]{3,100}$/'], 'port' => 'required|integer|min:1|max:65535']);
    self::set('carrier_ip', $d['ip']); self::set('carrier_port', (string)$d['port']);
    return back()->with('ok', 'Endpoint IP/Port saved.');
  }

  /** Relay PBX line settings (the FreeSWITCH relay registers as these PBX extensions in live mode). Passwords are stored encrypted and never shown again. */
  public function relaySave(Request $r) {
    $this->guard();
    $d = $r->validate(['host' => ['required', 'regex:/^[A-Za-z0-9.\-]{3,100}$/'], 'port' => 'required|integer|min:1|max:65535', 'transport' => 'required|in:tcp,udp',
      'realm' => ['nullable', 'regex:/^[A-Za-z0-9.\-]{0,100}$/'], 'caller_id' => ['nullable', 'regex:/^\d{0,20}$/'], 'mode' => 'required|in:test,live', 'exts' => 'nullable|string|max:2000']);
    $old = array_filter(array_map(fn($l) => trim($l), explode("\n", (string)Sms::secret('vc_relay_exts'))));
    $oldMap = []; foreach ($old as $l) { [$u, $p] = array_pad(explode(':', $l, 2), 2, ''); $oldMap[$u] = $p; }
    $lines = []; $seen = [];
    foreach (preg_split('/\r?\n/', (string)($d['exts'] ?? '')) as $l) {
      $l = trim($l); if ($l === '') continue;
      [$u, $p] = array_pad(explode(':', $l, 2), 2, '');
      abort_unless(preg_match('/^[A-Za-z0-9_.\-]{1,40}$/', $u), 422, 'Invalid extension number: ' . $u);
      if ($p === '' && isset($oldMap[$u])) $p = $oldMap[$u]; // blank password = keep the saved one
      abort_unless($p !== '' && preg_match('/^[^\s,]{1,80}$/', $p), 422, 'Enter a password (no spaces or commas): ' . $u);
      if (isset($seen[$u])) continue; $seen[$u] = 1; $lines[] = $u . ':' . $p;
    }
    self::set('relay_host', $d['host']); self::set('relay_port', (string)$d['port']); self::set('relay_transport', $d['transport']);
    self::set('relay_realm', (string)($d['realm'] ?? '')); self::set('relay_caller_id', (string)($d['caller_id'] ?? '')); self::set('relay_mode', $d['mode']);
    Sms::setSecret('vc_relay_exts', implode("\n", $lines));
    \App\Support\ProfitFloor::audit('voice.relay_config', 'Relay PBX line saved: ' . $d['host'] . ':' . $d['port'] . ' ' . $d['transport'] . ' mode ' . $d['mode'] . ' ext ' . implode(',', array_keys($seen)), null, ['mode' => $d['mode']]);
    return back()->with('ok', 'Relay line saved (' . count($lines) . ' extensions). The relay picks it up within 1 minute.');
  }
  /** One-time token the relay uses to fetch its config. Shown once. */
  public function relayToken() {
    $this->guard(); $t = bin2hex(random_bytes(20));
    Sms::setSecret('vc_relay_token', $t);
    \App\Support\ProfitFloor::audit('voice.relay_token', 'Relay token regenerated', null, []);
    return back()->with('ok', 'New relay token: ' . $t . ' — copy it now (it will not be shown again).');
  }
  /** Public, token-protected: the relay pulls its settings here (plain text KEY=VALUE). */
  public function relayConfig(Request $r) {
    $tok = (string)Sms::secret('vc_relay_token'); $given = (string)$r->bearerToken();
    if ($tok === '' || !hash_equals($tok, $given)) return response('forbidden', 403)->header('Content-Type', 'text/plain');
    $exts = implode(',', array_filter(array_map('trim', explode("\n", (string)Sms::secret('vc_relay_exts')))));
    $rows = DB::table('voice_carrier_clients')->where('status', 'active')->get();
    $o = "MODE=" . (Voice::set('relay_mode', 'test') === 'live' ? 'live' : 'test') . "\nPBX_HOST=" . Voice::set('relay_host', '') . "\nPBX_PORT=" . Voice::set('relay_port', '5080')
      . "\nPBX_TRANSPORT=" . Voice::set('relay_transport', 'tcp') . "\nPBX_REALM=" . Voice::set('relay_realm', '') . "\nCALLER_ID=" . Voice::set('relay_caller_id', '') . "\nPBX_EXTS=" . $exts . "\n";
    if ($rows->isNotEmpty()) $o .= "CLIENT_IP=" . explode('/', $rows[0]->ip)[0] . "\nMAX_CALLS=" . (int)$rows[0]->max_calls . "\n";
    else $o .= "CLIENT_IP=192.0.2.1\nMAX_CALLS=0\n"; // every client is OFF: allow only an unroutable TEST-NET address so no real client is accepted
    return response($o, 200)->header('Content-Type', 'text/plain')->header('Cache-Control', 'no-store');
  }

  /** Copy an existing agent extension (its encrypted softphone credentials) into the relay line, without showing the password. */
  public function relayFromAgent(Request $r) {
    $this->guard(); $d = $r->validate(['ext' => ['required', 'regex:/^[A-Za-z0-9_.\-]{1,40}$/']]);
    $a = DB::table('sip_accounts')->where('username', $d['ext'])->first();
    abort_unless($a && $a->password_enc, 422, 'Extension not found or has no password.');
    try { $pw = \Illuminate\Support\Facades\Crypt::decryptString($a->password_enc); } catch (\Throwable $e) { abort(422, 'Could not read the password.'); }
    abort_unless(preg_match('/^[^\s,]{1,80}$/', $pw), 422, 'Password contains a comma or space; enter it manually.');
    $map = []; foreach (array_filter(array_map('trim', explode("\n", (string)Sms::secret('vc_relay_exts')))) as $l) { [$u, $p] = array_pad(explode(':', $l, 2), 2, ''); $map[$u] = $p; }
    $map[$d['ext']] = $pw; $lines = []; foreach ($map as $u => $p) $lines[] = $u . ':' . $p;
    Sms::setSecret('vc_relay_exts', implode("\n", $lines));
    if (Voice::set('relay_realm', '') === '' && !empty($a->domain)) self::set('relay_realm', (string)$a->domain);
    \App\Support\ProfitFloor::audit('voice.relay_ext_added', 'Relay line: agent extension ' . $d['ext'] . ' copied (password not shown)', null, ['ext' => $d['ext']]);
    return back()->with('ok', 'Extension ' . $d['ext'] . ' added to the relay line. (realm: ' . (Voice::set('relay_realm', '') ?: 'host') . ')');
  }
}
