<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Owner 17 Nov 2026: two SMS providers behind the one central Sms service.
 *   alpha = Alpha SMS / sms.net.bd (new)      bulk = BulkSMSBD (the connection that was already here, untouched)
 * Every send in the system goes through Sms::send() -> SmsGate::route(), so the provider choice, the fall-back rules,
 * the per-attempt record and the duplicate guard live in one place.
 *
 * Fall-back rules (the reason is recorded on every attempt):
 *   balance / provider   -> the other provider may be used (when automatic fall-back is on)
 *   input                -> wrong number, empty / too long text, wrong parameter: failed, to be corrected, no fall-back
 *   blocked              -> blocked content or sender ID not approved: failed, never sent round through the other provider
 *   uncertain            -> timeout / connection lost / unreadable answer: the provider may already have accepted it,
 *                           so it is NOT sent again; kept as "uncertain" and the admin is told
 * "accepted" only means the provider took the request. Delivery comes from the provider's report (reportTick).
 */
class SmsGate {
  const NAMES = ['alpha' => 'Alpha SMS (sms.net.bd)', 'bulk' => 'BulkSMSBD'];
  const ALPHA_SEND = 'https://api.sms.net.bd/sendsms';
  const ALPHA_REPORT = 'https://api.sms.net.bd/report/request/';
  const ALPHA_BAL = 'https://api.sms.net.bd/user/balance/';
  const BULK_SEND = 'https://bulksmsbd.net/api/smsapi';
  const BULK_BAL = 'https://bulksmsbd.net/api/getBalanceApi';
  /** code => [class, text] */
  const ALPHA_CODES = [
    400 => ['input', 'Missing or invalid parameter'], 403 => ['provider', 'No permission for this request'], 404 => ['provider', 'Resource not found'],
    405 => ['provider', 'Authorization required (API key)'], 409 => ['provider', 'Unknown error at the provider'], 410 => ['provider', 'Account expired'],
    411 => ['provider', 'Reseller account expired or suspended'], 412 => ['input', 'Invalid schedule'], 413 => ['blocked', 'Invalid sender ID'],
    414 => ['input', 'Message is empty'], 415 => ['input', 'Message is too long'], 416 => ['input', 'No valid number found'],
    417 => ['balance', 'Insufficient balance'], 420 => ['blocked', 'Content blocked'],
  ];
  const BULK_CODES = [
    1001 => ['input', 'Invalid number'], 1002 => ['blocked', 'Sender ID incorrect or disabled'], 1003 => ['input', 'Required fields missing'],
    1005 => ['provider', 'Internal error at gateway'], 1006 => ['balance', 'Balance validity not available'], 1007 => ['balance', 'Insufficient balance'],
    1011 => ['provider', 'User not found'], 1012 => ['blocked', 'Masking SMS must be Bengali text'], 1013 => ['blocked', 'No gateway for this sender ID'],
    1014 => ['blocked', 'Sender type not found'], 1015 => ['provider', 'No valid gateway'], 1018 => ['provider', 'Account disabled'],
    1031 => ['provider', 'Account not verified'], 1032 => ['provider', 'Server IP not whitelisted in BulkSMSBD'],
  ];
  /** Test seam: fn(string $provider, string $url, ?array $fields): array{int,?string,int}. Used only by simulate(). */
  public static $fakeHttp = null;
  private static ?array $memo = null;

  // ---------- settings ----------
  public static function set(string $k, string $def = ''): string {
    if (self::$memo === null) { try { self::$memo = DB::table('settings')->where('key', 'like', 'smsg_%')->pluck('value', 'key')->all(); } catch (\Throwable $e) { self::$memo = []; } }
    $v = self::$memo['smsg_' . $k] ?? null; return $v === null ? $def : (string)$v;
  }
  public static function put(string $k, string $v): void {
    DB::table('settings')->updateOrInsert(['key' => 'smsg_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); self::$memo = null;
  }
  public static function cfg(): array {
    $p = self::set('primary', 'alpha'); if (!isset(self::NAMES[$p])) $p = 'alpha';
    return ['primary' => $p, 'secondary' => $p === 'alpha' ? 'bulk' : 'alpha', 'fallback' => self::set('fallback', '1') === '1',
      'on' => ['alpha' => self::set('alpha_on', '1') === '1', 'bulk' => self::set('bulk_on', '1') === '1'],
      'sender' => ['alpha' => trim(self::set('alpha_sender', '')), 'bulk' => (string)Sms::secret('sms_sender_id')],
      'content' => trim(self::set('alpha_content', '')),
      'tests' => array_values(array_filter(array_map(fn($x) => Sms::number(trim($x)), preg_split('/[,\s]+/', self::set('test_numbers', '01884808080'))))),
    ];
  }
  public static function hasKey(string $p): bool { return (bool)Sms::secret($p === 'alpha' ? 'sms_alpha_key' : 'sms_api_key'); }
  /** Bulk needs key + sender (as before). Alpha needs only the key: the sender ID is optional there. */
  public static function ready(string $p): bool { return $p === 'alpha' ? self::hasKey('alpha') : (self::hasKey('bulk') && (bool)Sms::secret('sms_sender_id')); }
  public static function usable(string $p, ?array $c = null): bool { $c = $c ?? self::cfg(); return ($c['on'][$p] ?? false) && self::ready($p); }
  /** Providers to try, in order, with the reason a provider was skipped. @return array{0:array<int,string>,1:?string} */
  public static function order(?array $c = null): array {
    $c = $c ?? self::cfg(); $p = $c['primary']; $s = $c['secondary']; $skip = null;
    if (!$c['fallback']) return [self::usable($p, $c) ? [$p] : [], self::usable($p, $c) ? null : self::NAMES[$p] . ' is the only selected provider but it is ' . (($c['on'][$p] ?? false) ? 'not configured' : 'switched off')];
    $out = [];
    if (self::usable($p, $c)) { $d = Cache::get('smsg_down_' . $p); if ($d) $skip = self::NAMES[$p] . ' skipped: ' . $d; else $out[] = $p; }
    else $skip = self::NAMES[$p] . ' skipped: ' . (($c['on'][$p] ?? false) ? 'not configured' : 'switched off');
    if (self::usable($s, $c)) $out[] = $s;
    if (!$out && self::usable($p, $c)) $out[] = $p; // primary marked down and nothing else to use: still try it
    return [$out, $skip];
  }
  public static function anyReady(): bool { return (bool)self::order()[0]; }

  // ---------- transport ----------
  /** @return array{0:int,1:?string,2:int} HTTP status, body, curl errno (0 = answered) */
  private static function http(string $p, string $url, ?array $fields, int $timeout = 15): array {
    if (self::$fakeHttp) return (self::$fakeHttp)($p, $url, $fields);
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => $timeout, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_USERAGENT => 'MotherCommerce/1.0', CURLOPT_FOLLOWLOCATION => false]);
      if ($fields !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields)); }
      $b = curl_exec($ch); $no = curl_errno($ch); $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
      return [$st, $b === false ? null : (string)$b, $no];
    }
    $o = ['http' => ['method' => $fields === null ? 'GET' : 'POST', 'timeout' => $timeout, 'ignore_errors' => true, 'header' => "User-Agent: MotherCommerce/1.0\r\nContent-Type: application/x-www-form-urlencoded\r\n"], 'ssl' => ['verify_peer' => true]];
    if ($fields !== null) $o['http']['content'] = http_build_query($fields);
    $r = @file_get_contents($url, false, stream_context_create($o));
    return [$r === false ? 0 : 200, $r === false ? null : $r, $r === false ? 28 : 0]; // without curl a failure cannot be told apart: treated as uncertain
  }
  /** Turn a transport failure into a result. Could not resolve / could not connect = the request never left = safe to use the other provider. */
  private static function transportFail(int $errno, int $status, ?string $body): ?array {
    if (in_array($errno, [5, 6, 7], true)) return ['state' => 'failed', 'class' => 'provider', 'code' => 'net' . $errno, 'msg' => 'Provider unreachable (no connection was made)', 'rid' => null];
    if ($errno !== 0) return ['state' => 'uncertain', 'class' => 'uncertain', 'code' => 'net' . $errno, 'msg' => $errno === 28 ? 'Timed out waiting for the provider' : 'Connection lost while sending', 'rid' => null];
    if ($body === null || trim($body) === '') return ['state' => 'uncertain', 'class' => 'uncertain', 'code' => 'http' . $status, 'msg' => 'Empty answer from the provider', 'rid' => null];
    return null;
  }

  // ---------- provider adapters ----------
  public static function sendVia(string $p, string $num, string $body, array $ctx = []): array {
    return $p === 'alpha' ? self::alphaSend($num, $body, $ctx) : self::bulkSend($num, $body);
  }
  private static function alphaSend(string $num, string $body, array $ctx): array {
    $c = self::cfg(); $f = ['api_key' => (string)Sms::secret('sms_alpha_key'), 'msg' => $body, 'to' => $num];
    if ($c['sender']['alpha'] !== '') $f['sender_id'] = $c['sender']['alpha'];
    if ($c['content'] !== '' && ($ctx['type'] ?? '') === 'marketing') $f['content_id'] = $c['content']; // approved campaign content only
    [$st, $b, $no] = self::http('alpha', self::ALPHA_SEND, $f);
    if ($t = self::transportFail($no, $st, $b)) return $t;
    $j = json_decode((string)$b, true);
    if (!is_array($j) || !array_key_exists('error', $j)) return ['state' => 'uncertain', 'class' => 'uncertain', 'code' => 'http' . $st, 'msg' => 'Unreadable answer from the provider', 'rid' => null];
    $e = (int)$j['error']; $rid = $j['data']['request_id'] ?? null;
    if ($e === 0 && $rid) return ['state' => 'accepted', 'class' => null, 'code' => '0', 'msg' => 'Accepted by provider (request ' . $rid . ')', 'rid' => (string)$rid];
    if ($e === 0) return ['state' => 'uncertain', 'class' => 'uncertain', 'code' => '0', 'msg' => 'Provider answered without a request id', 'rid' => null];
    [$cl, $tx] = self::ALPHA_CODES[$e] ?? ['provider', (string)($j['msg'] ?? 'Unknown error')];
    return ['state' => 'failed', 'class' => $cl, 'code' => (string)$e, 'msg' => $e . ' ' . $tx, 'rid' => null];
  }
  private static function bulkSend(string $num, string $body): array {
    [$st, $b, $no] = self::http('bulk', self::BULK_SEND, ['api_key' => (string)Sms::secret('sms_api_key'), 'type' => 'text', 'senderid' => (string)Sms::secret('sms_sender_id'), 'number' => $num, 'message' => $body]);
    if ($t = self::transportFail($no, $st, $b)) return $t;
    $j = json_decode((string)$b, true); $code = is_array($j) ? (int)($j['response_code'] ?? 0) : 0;
    if ($code === 202) return ['state' => 'accepted', 'class' => null, 'code' => '202', 'msg' => '202 Submitted', 'rid' => isset($j['message_id']) ? (string)$j['message_id'] : null];
    if (!$code) return ['state' => 'uncertain', 'class' => 'uncertain', 'code' => 'http' . $st, 'msg' => 'Unreadable answer from the provider', 'rid' => null];
    [$cl, $tx] = self::BULK_CODES[$code] ?? ['provider', (string)($j['error_message'] ?? 'Unknown error')];
    return ['state' => 'failed', 'class' => $cl, 'code' => (string)$code, 'msg' => $code . ' ' . $tx, 'rid' => null];
  }

  // ---------- routing ----------
  /**
   * Send one message. @return array{ok:bool,state:string,provider:?string,rid:?string,msg:string,class:?string,fallback:?string,uid:string,trace:array}
   * $ctx: log_id, event, type, uid, force (provider key: manual choice, no fall-back), dry (simulate: no DB, no lock, no alerts)
   */
  public static function route(string $to, string $body, array $ctx = []): array {
    $uid = (string)($ctx['uid'] ?? Str::uuid()); $dry = !empty($ctx['dry']);
    $res = ['ok' => false, 'state' => 'failed', 'provider' => null, 'rid' => null, 'msg' => '', 'class' => null, 'fallback' => null, 'uid' => $uid, 'trace' => []];
    $num = Sms::number($to);
    if (!$num) return ['msg' => 'Invalid phone number', 'class' => 'input'] + $res;
    if (trim($body) === '') return ['msg' => 'Message is empty', 'class' => 'input'] + $res;
    if (!empty($ctx['force'])) { $list = self::ready($ctx['force']) ? [$ctx['force']] : []; $skip = $list ? null : self::NAMES[$ctx['force']] . ' is not configured'; }
    else [$list, $skip] = $dry && isset($ctx['order']) ? [$ctx['order'], null] : self::order();
    if (!$list) return ['msg' => $skip ?: 'No SMS provider is configured', 'class' => 'provider'] + $res;
    // one message at a time: the same text to the same number cannot be sent twice at once (double click, two workers)
    $lock = 'smsg_lk_' . md5($num . '|' . $body);
    if (!$dry && !Cache::add($lock, 1, 20)) return ['msg' => 'Same message to this number is already being sent (duplicate blocked)', 'class' => 'duplicate'] + $res;
    $why = $skip; // why we are not on the primary, if so
    foreach ($list as $i => $p) {
      $r = self::sendVia($p, $num, $body, $ctx);
      $res['trace'][] = ['provider' => $p] + $r + ['fallback' => $why];
      if (!$dry) self::record($uid, $p, $num, $ctx, $r, $why);
      $res = ['provider' => $p, 'state' => $r['state'], 'rid' => $r['rid'], 'msg' => self::NAMES[$p] . ': ' . $r['msg'], 'class' => $r['class'], 'fallback' => $why] + $res;
      if ($r['state'] === 'accepted') { $res['ok'] = true; break; }
      if ($r['state'] === 'uncertain') { // may already be accepted: never resend, never fall back; check the provider so the NEXT messages can use the other one
        if (!$dry) { self::probe($p); self::alert('uncertain', $p, $num, $r['msg']); }
        break;
      }
      if (!in_array($r['class'], ['balance', 'provider'], true)) break; // input / blocked: not something another provider should carry
      if (!$dry) { Cache::put('smsg_down_' . $p, $r['msg'], $r['class'] === 'balance' ? 600 : 180); self::alert($r['class'], $p, $num, $r['msg']); }
      $why = self::NAMES[$p] . ' failed: ' . $r['msg'];
      if (!isset($list[$i + 1])) break;
    }
    if (!$dry && !$res['ok']) Cache::forget($lock); // a definite failure may be retried by the staff; an accepted one stays locked for the window
    if (!$dry && $res['state'] === 'uncertain') Cache::put($lock, 1, 600);
    return $res;
  }
  /** After an uncertain answer: is the provider reachable at all? If not, later messages go to the other provider for a few minutes. */
  private static function probe(string $p): void {
    try { $b = self::balance($p, 6); if (!$b['ok']) Cache::put('smsg_down_' . $p, 'health check failed after a timeout (' . $b['text'] . ')', 180); } catch (\Throwable $e) {}
  }
  private static function record(string $uid, string $p, string $num, array $ctx, array $r, ?string $why): void {
    try {
      if (!Schema::hasTable('sms_attempts')) return;
      DB::table('sms_attempts')->insert(['uid' => $uid, 'log_id' => $ctx['log_id'] ?? null, 'provider' => $p, 'to' => $num, 'event' => isset($ctx['event']) ? mb_substr((string)$ctx['event'], 0, 60) : null,
        'state' => $r['state'], 'class' => $r['class'], 'code' => mb_substr((string)$r['code'], 0, 20), 'message' => mb_substr((string)$r['msg'], 0, 250), 'request_id' => $r['rid'],
        'delivery' => $r['state'] === 'accepted' ? ($p === 'alpha' && $r['rid'] ? 'pending' : 'no report') : null, 'fallback_reason' => $why ? mb_substr($why, 0, 250) : null, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {}
  }
  private static function alert(string $kind, string $p, string $num, string $msg): void {
    try {
      $n = self::NAMES[$p]; $tail = '…' . substr($num, -4);
      if ($kind === 'uncertain') DeployHealth::raise('sms_uncertain_' . $p . '_' . date('YmdH'), 'medium', 'SMS', 'SMS status uncertain (' . $n . ')', 'A message to ' . $tail . ' got no clear answer: ' . $msg . '. It was NOT sent again, because the provider may already have accepted it.', 'Timeout or lost connection while talking to ' . $n, 'Open SMS Setup, look at the latest messages and resend by hand only if the customer did not get it.');
      elseif ($kind === 'balance') DeployHealth::raise('sms_balance_' . $p . '_' . date('Ymd'), 'high', 'SMS', $n . ': SMS balance finished', $n . ' refused a message: ' . $msg . '.', 'No balance at ' . $n, 'Recharge ' . $n . '. Until then messages go through the other provider if automatic fall-back is on.');
      else DeployHealth::raise('sms_down_' . $p . '_' . date('YmdH'), 'high', 'SMS', $n . ' is not accepting SMS', $n . ' answered: ' . $msg . '.', 'Provider error or account problem at ' . $n, 'Check the ' . $n . ' account. Messages go through the other provider if automatic fall-back is on.');
    } catch (\Throwable $e) {}
  }

  // ---------- balance ----------
  /** @return array{ok:bool,text:string} */
  public static function balance(string $p, int $timeout = 12): array {
    if (!self::hasKey($p)) return ['ok' => false, 'text' => 'API key not set'];
    if ($p === 'alpha') {
      [$st, $b, $no] = self::http('alpha', self::ALPHA_BAL . '?api_key=' . rawurlencode((string)Sms::secret('sms_alpha_key')), null, $timeout);
      $j = json_decode((string)$b, true);
      if ($no === 0 && is_array($j) && (int)($j['error'] ?? 1) === 0 && isset($j['data']['balance'])) return ['ok' => true, 'text' => (string)$j['data']['balance']];
      if (is_array($j) && isset($j['error'])) return ['ok' => false, 'text' => $j['error'] . ' ' . (self::ALPHA_CODES[(int)$j['error']][1] ?? ($j['msg'] ?? ''))];
      return ['ok' => false, 'text' => $no ? 'No connection to the provider (network code ' . $no . ($no === 6 ? ': name lookup failed' : ($no === 28 ? ': timed out' : ($no === 7 ? ': could not connect' : ($no === 60 || $no === 35 ? ': secure connection failed' : '')))) . ')' : 'No readable answer (HTTP ' . $st . ')'];
    }
    [$st, $b, $no] = self::http('bulk', self::BULK_BAL . '?api_key=' . rawurlencode((string)Sms::secret('sms_api_key')), null, $timeout);
    $j = json_decode((string)$b, true);
    if (is_array($j) && isset($j['balance'])) return ['ok' => true, 'text' => (string)$j['balance']];
    if (is_array($j) && isset($j['response_code'])) return ['ok' => false, 'text' => $j['response_code'] . ' ' . (self::BULK_CODES[(int)$j['response_code']][1] ?? ($j['error_message'] ?? ''))];
    return ['ok' => false, 'text' => $b ? mb_substr(strip_tags((string)$b), 0, 120) : 'No response from gateway'];
  }
  /** Check and remember the balance with the time of the check. */
  public static function refreshBalance(string $p): array {
    $b = self::balance($p); self::put('bal_' . $p, $b['text']); self::put('bal_' . $p . '_at', now()->toDateTimeString()); self::put('bal_' . $p . '_ok', $b['ok'] ? '1' : '0');
    if ($b['ok']) Cache::forget('smsg_down_' . $p);
    return $b;
  }

  // ---------- delivery report ----------
  public static function deliveryClass(string $raw): string {
    $s = strtolower(trim($raw));
    if ($s === '') return 'pending';
    if (preg_match('/fail|reject|invalid|block|expire|undeliv|error|cancel/', $s)) return 'failed';
    if (preg_match('/pend|queue|process|schedul|submit|wait/', $s)) return 'pending';
    if (preg_match('/deliver/', $s)) return 'delivered';
    if (preg_match('/sent|success|complete/', $s)) return 'sent';
    return 'pending';
  }
  /** Read the real status of accepted Alpha SMS requests. BulkSMSBD has no report API, so its rows stay "accepted". */
  public static function reportTick(int $limit = 15): int {
    if (!Schema::hasTable('sms_attempts') || !self::hasKey('alpha')) return 0;
    if (!Cache::add('smsg_report_tick', 1, 50)) return 0;
    $n = 0;
    $rows = DB::table('sms_attempts')->where('provider', 'alpha')->where('state', 'accepted')->where('delivery', 'pending')->whereNotNull('request_id')
      ->where('created_at', '<', now()->subSeconds(45))->where('created_at', '>', now()->subDays(2))->where('checks', '<', 8)
      ->where(fn($q) => $q->whereNull('checked_at')->orWhereRaw("checked_at < now() - (checks * interval '4 minutes')"))->orderBy('id')->limit($limit)->get();
    foreach ($rows as $a) {
      [$st, $b, $no] = self::http('alpha', self::ALPHA_REPORT . rawurlencode((string)$a->request_id) . '/?api_key=' . rawurlencode((string)Sms::secret('sms_alpha_key')), null, 10);
      $j = json_decode((string)$b, true); $up = ['checks' => $a->checks + 1, 'checked_at' => now(), 'updated_at' => now()];
      if ($no === 0 && is_array($j) && (int)($j['error'] ?? 1) === 0) {
        $d = $j['data'] ?? []; $rec = null; foreach ((array)($d['recipients'] ?? []) as $x) if (substr(preg_replace('/\D/', '', (string)($x['number'] ?? '')), -10) === substr($a->to, -10)) $rec = $x;
        $raw = trim((string)($rec['status'] ?? '')); $req = trim((string)($d['request_status'] ?? ''));
        $cls = self::deliveryClass($raw !== '' ? $raw : ($req !== '' && self::deliveryClass($req) === 'failed' ? $req : ''));
        $up += ['delivery' => $cls, 'delivery_raw' => mb_substr(trim($raw . ($req !== '' ? ' / request: ' . $req : '')), 0, 120)];
        if ($a->log_id && Schema::hasColumn('sms_logs', 'delivery')) DB::table('sms_logs')->where('id', $a->log_id)->where('request_id', $a->request_id)->update(['delivery' => $cls, 'updated_at' => now()]);
      } elseif ($a->checks + 1 >= 8) $up += ['delivery' => 'unknown', 'delivery_raw' => 'report not available'];
      DB::table('sms_attempts')->where('id', $a->id)->update($up); $n++;
    }
    return $n;
  }
  public static function tick(): void { try { self::reportTick(); } catch (\Throwable $e) {} }

  // ---------- dry run ----------
  /** Run the routing rules against made-up provider answers. Nothing is sent, nothing is stored. */
  public static function simulate(): array {
    $c = self::cfg(); $p = $c['primary']; $s = $c['secondary']; $okA = '{"error":0,"msg":"Request successfully submitted","data":{"request_id":123456}}'; $okB = '{"response_code":202,"message_id":777,"success_message":"SMS Submitted Successfully"}';
    $ok = fn($x) => [200, $x === 'alpha' ? $okA : $okB, 0];
    $err = fn($x, $a, $b) => [200, $x === 'alpha' ? '{"error":' . $a . ',"msg":"x"}' : '{"response_code":' . $b . ',"error_message":"x"}', 0];
    $cases = [
      ['Primary accepts', fn($x) => $ok($x), 'accepted on primary, secondary not used'],
      ['Primary: balance finished', fn($x) => $x === $p ? $err($x, 417, 1007) : $ok($x), 'secondary used'],
      ['Primary: provider error / account problem', fn($x) => $x === $p ? $err($x, 409, 1005) : $ok($x), 'secondary used'],
      ['Primary: cannot connect (request never left)', fn($x) => $x === $p ? [0, null, 7] : $ok($x), 'secondary used'],
      ['Primary: timeout', fn($x) => $x === $p ? [0, null, 28] : $ok($x), 'uncertain, NOT sent again'],
      ['Primary: unreadable answer', fn($x) => $x === $p ? [502, '<html>Bad gateway</html>', 0] : $ok($x), 'uncertain, NOT sent again'],
      ['Primary: wrong number', fn($x) => $x === $p ? $err($x, 416, 1001) : $ok($x), 'failed, secondary not used'],
      ['Primary: message too long / wrong parameter', fn($x) => $x === $p ? $err($x, 415, 1003) : $ok($x), 'failed, secondary not used'],
      ['Primary: content blocked', fn($x) => $x === $p ? ($x === 'alpha' ? $err($x, 420, 0) : $err($x, 0, 1012)) : $ok($x), 'failed, secondary not used'],
      ['Primary: sender ID not approved', fn($x) => $x === $p ? $err($x, 413, 1002) : $ok($x), 'failed, secondary not used'],
      ['Both: balance finished', fn($x) => $err($x, 417, 1007), 'failed on both'],
    ];
    $out = [];
    foreach ([true, false] as $fb) foreach ($cases as [$name, $fn, $expect]) {
      $calls = [];
      self::$fakeHttp = function ($x, $url, $f) use ($fn, &$calls) { $calls[] = $x; return $fn($x); };
      try { $r = self::route('01700000000', 'পরীক্ষা test', ['dry' => true, 'order' => $fb ? [$p, $s] : [$p]]); } finally { self::$fakeHttp = null; }
      $got = $r['state'] === 'accepted' ? ('accepted on ' . ($r['provider'] === $p ? 'primary' : 'secondary')) : ($r['state'] . ' (' . $r['class'] . ')');
      $want = $fb ? $expect : str_replace(['secondary used', 'failed on both'], ['failed, secondary not used', 'failed, secondary not used'], $expect);
      $pass = match (true) {
        str_starts_with($want, 'accepted on primary') => $r['ok'] && $calls === [$p],
        $want === 'secondary used' => $r['ok'] && $calls === [$p, $s] && $r['provider'] === $s,
        str_starts_with($want, 'uncertain') => $r['state'] === 'uncertain' && $calls === [$p],
        $want === 'failed on both' => !$r['ok'] && $calls === [$p, $s],
        default => !$r['ok'] && $r['state'] === 'failed' && $calls === [$p],
      };
      $out[] = ['mode' => $fb ? 'Automatic fall-back' : 'Primary only', 'case' => $name, 'expect' => $want, 'got' => $got, 'calls' => implode(' → ', array_map(fn($x) => self::NAMES[$x], $calls)), 'pass' => $pass];
    }
    return $out;
  }
}
