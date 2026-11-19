<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AI order-confirmation call (owner spec 19 Nov 2026).
 * Reuses the existing Voice PBX path (Event Socket originate, text-to-speech audio, key-press webhook). Nothing new on the PBX side.
 *  Key 1 = customer confirmed the order, key 2 = customer asks to keep it pending. An answered call alone is NOT a confirmation.
 *  Modes (setting ocv_mode): off (default) | assist (call and record evidence only) | gate (an eligible order is not auto-approved until key 1 or a staff call).
 *  ocv_test_only = 1 (default): only the test phone list is ever called, no real customer.
 *  The confirmation belongs to the order version heard (phone, total, paid, items). A changed order needs a new confirmation.
 *  Staff calls stay separate (call_logs); this class never merges the two sources.
 */
class OrderCall
{
  const TEST_DEFAULT = '01884808080';

  public static function set(string $k, string $d = ''): string { return Voice::set('ocv_' . $k, $d); }
  public static function put2(string $k, string $v): void { Voice::put('ocv_' . $k, $v); }
  public static function ready(): bool { try { return Schema::hasTable('order_call_verifications'); } catch (\Throwable $e) { return false; } }
  public static function mode(): string { $m = self::set('mode', 'off'); return in_array($m, ['assist', 'gate'], true) ? $m : 'off'; }
  public static function testOnly(): bool { return self::set('test_only', '1') !== '0'; }
  public static function testPhones(): array { return array_values(array_filter(array_map(fn($p) => Voice::norm($p), preg_split('/[\s,;]+/', self::set('test_phones', self::TEST_DEFAULT)) ?: []))); }
  public static function pbxReady(): bool {
    return (Voice::set('esl_host') !== '' || env('PBX_ESL_HOST')) && (Sms::secret('vc_esl_pass') || env('PBX_ESL_PASSWORD')) && Voice::set('gateway') !== '' && Voice::set('tts_provider', 'none') !== 'none' && (bool)Sms::secret('vc_tts_key');
  }
  public static function readiness(): array {
    return [
      ['PBX connection and gateway', (Voice::set('esl_host') !== '' || (bool)env('PBX_ESL_HOST')) && (bool)(Sms::secret('vc_esl_pass') || env('PBX_ESL_PASSWORD')) && Voice::set('gateway') !== '', 'Set in Marketing → Voice Campaigns → Settings (gateway must match the PBX gateway list)'],
      ['Bangla voice (text-to-speech)', Voice::set('tts_provider', 'none') !== 'none' && (bool)Sms::secret('vc_tts_key'), 'Voice settings → text-to-speech provider and key'],
      ['Call script: placed on the PBX automatically (a small file in /tmp), no FusionPBX script setup needed', true, ''],
    ];
  }

  // ---------------- order version and eligibility
  public static function version(object $o, $items = null): string {
    $items = $items ?? DB::table('order_items')->where('order_id', $o->id)->get();
    return md5(json_encode([Voice::norm((string)$o->phone), round((float)$o->total, 2), round((float)($o->paid_total ?? 0), 2), collect($items)->map(fn($i) => [$i->product_id, (int)$i->qty, round((float)$i->price, 2)])->sortBy(fn($x) => json_encode($x))->values()->all()]));
  }
  /** null = the order may be called; otherwise the reason it may not. */
  public static function eligible(object $o): ?string {
    if ($o->status !== 'New') return 'order is not waiting for approval';
    if (!empty($o->duplicate_of_order_id)) return 'duplicate order';
    $p = Voice::norm((string)$o->phone); if (!Voice::validPhone($p)) return 'invalid phone';
    if ((float)$o->total <= 0) return 'no amount';
    $src = trim(self::set('source', 'Web Site')); if ($src !== '' && strcasecmp((string)($o->source ?? ''), $src) !== 0) return 'order source is not ' . $src;
    if (self::testOnly() && !in_array($p, self::testPhones(), true)) return 'test-only mode: phone is not in the test list';
    if (!empty($o->customer_id)) { $cu = DB::table('customers')->find($o->customer_id); if ($cu && !empty($cu->blocked)) return 'customer is blocked'; }
    // only orders that AI Verification itself has passed (every other check green) get the call; risky or unchecked orders stay with staff
    $v = DB::table('order_ai_verifications')->where('order_id', $o->id)->first();
    if (!$v) return 'AI verification has not run yet';
    if (($v->override_status ?: $v->verification_status) !== 'verified') return 'order is not AI Verified (staff handles it)';
    return null;
  }
  /** A staff call answered by this customer around this order (call_logs), AI calls excluded. */
  public static function staffCall(object $o): ?object {
    try {
      if (!Schema::hasTable('call_logs')) return null; $p = Voice::norm((string)$o->phone); if (!Voice::validPhone($p)) return null;
      $c = DB::table('call_logs')->whereRaw('right(phone,11) = ?', [$p])->where('status', 'answered')->where(fn($q) => $q->where('order_id', $o->id)->orWhere('started_at', '>=', \Carbon\Carbon::parse($o->created_at)->subHours(2)))->orderByDesc('id')->limit(5)->get();
      foreach ($c as $x) if (!self::isAiCall($p, $x->started_at)) return $x;
    } catch (\Throwable $e) {}
    return null;
  }
  public static function isAiCall(string $phone, $at): bool {
    if (!self::ready() || !$at) return false;
    try { $t = \Carbon\Carbon::parse($at); return DB::table('order_call_verifications')->where('phone', Voice::norm($phone))->whereBetween('started_at', [$t->copy()->subMinutes(6), $t->copy()->addMinutes(6)])->exists(); } catch (\Throwable $e) { return false; }
  }

  // ---------------- state shown in AI Verification
  /** Latest AI-call state for this order's current version: ['state','row','at'] or null. */
  public static function state(object $o): ?array {
    if (!self::ready()) return null;
    try {
      $ver = self::version($o); $rows = DB::table('order_call_verifications')->where('order_id', $o->id)->orderByDesc('id')->get(); if (!$rows->count()) return null;
      $cur = $rows->where('version_hash', $ver); $hit = $cur->first(fn($r) => in_array($r->status, ['confirmed', 'pending_requested'], true));
      if ($hit) return ['state' => $hit->status, 'row' => $hit, 'at' => $hit->ended_at ?: $hit->updated_at, 'attempts' => $cur->count()];
      $last = $cur->first(); if ($last) return ['state' => $last->status, 'row' => $last, 'at' => $last->updated_at, 'attempts' => $cur->count()];
      return ['state' => 'stale', 'row' => $rows->first(), 'at' => $rows->first()->updated_at, 'attempts' => 0];
    } catch (\Throwable $e) { return null; }
  }
  /** Gate mode: an order that may be called is not auto-approved until key 1 (or a staff call) has confirmed it. */
  public static function blocksApproval(object $o): bool {
    try {
      if (self::mode() !== 'gate' || !self::ready() || self::eligible($o) !== null) return false;
      $s = self::state($o); if ($s && $s['state'] === 'confirmed') return false; if (self::staffCall($o)) return false; return true;
    } catch (\Throwable $e) { return false; }
  }

  // ---------------- script
  private static function money(float $v): string { return Voice::bn(number_format(round($v), 0, '', '')); }
  public static function script(object $o, $items): string {
    $inv = preg_replace('/\D/', '', (string)$o->invoice); $last = Voice::bn(substr($inv !== '' ? $inv : (string)$o->id, -4));
    $names = collect($items)->map(fn($i) => trim((string)$i->name) . ' ' . Voice::bn((string)(int)$i->qty) . 'টি')->values();
    $list = $names->take(3)->implode(', ') . ($names->count() > 3 ? ' এবং আরও ' . Voice::bn((string)($names->count() - 3)) . 'টি পণ্য' : '');
    $total = (float)$o->total; $paid = (float)($o->paid_total ?? 0); $due = max(0, $total - $paid);
    $s = 'আসসালামু আলাইকুম। আমি ডিউটি ফ্রি বিডির স্বয়ংক্রিয় অর্ডার সহকারী। আপনার অর্ডার নম্বরের শেষ চার সংখ্যা ' . $last . '। আপনি ' . $list . ' অর্ডার করেছেন। ';
    if ($paid > 0) $s .= 'ডেলিভারি চার্জসহ মোট ' . self::money($total) . ' টাকা। যাচাই করা পরিশোধ ' . self::money($paid) . ' টাকা। বাকি ' . self::money($due) . ' টাকা। ';
    else $s .= 'ডেলিভারি চার্জসহ মোট ' . self::money($total) . ' টাকা। পণ্য গ্রহণের সময় দিতে হবে ' . self::money($due) . ' টাকা। ';
    return $s . 'সব তথ্য ঠিক থাকলে অর্ডারটি নিশ্চিত করতে ১ চাপুন। তথ্য ঠিক না থাকলে বা আমাদের সঙ্গে কথা বলতে চাইলে অর্ডারটি পেন্ডিং রাখতে ২ চাপুন।';
  }
  public static function testScript(): string {
    return 'আসসালামু আলাইকুম। আমি ডিউটি ফ্রি বিডির স্বয়ংক্রিয় অর্ডার সহকারী। এটি একটি পরীক্ষামূলক কল। আপনার অর্ডার নম্বরের শেষ চার সংখ্যা ০০০০। আপনি নমুনা পণ্য ১টি অর্ডার করেছেন। ডেলিভারি চার্জসহ মোট ১২৩৪ টাকা। পণ্য গ্রহণের সময় দিতে হবে ১২৩৪ টাকা। সব তথ্য ঠিক থাকলে অর্ডারটি নিশ্চিত করতে ১ চাপুন। তথ্য ঠিক না থাকলে বা আমাদের সঙ্গে কথা বলতে চাইলে অর্ডারটি পেন্ডিং রাখতে ২ চাপুন।';
  }

  // ---------------- calling
  // ---------------- the call itself: this server drives the PBX call over the Event Socket (no PBX script, no dialplan, no shell command on the PBX)
  private static function promptUrl(string $text): array {
    $c = (object)['id' => 0, 'pbx_audio' => null, 'audio_path' => null, 'dtmf_on' => 0, 'script' => $text . ' ', 'expiry_date' => null, 'validity_days' => 3];
    $row = (object)['id' => 0, 'phone' => '', 'product_name' => '', 'discount_value' => 0, 'name' => ''];
    [$f, $err] = Voice::audio($c, $row); if (!$f) return [null, $err]; $u = preg_replace('~/voice-audio/(\d+)\.(\w+)\?t=(\w+)~', '/voice-audio/$1-$3.$2', Voice::publicUrl($f)); return [$u, ''];
  }
  private static function frame($fp): ?array {
    $h = []; while (($l = fgets($fp)) !== false) { $l = rtrim($l, "\r\n"); if ($l === '') { if ($h) break; continue; } if (str_contains($l, ':')) { [$k, $v] = explode(':', $l, 2); $h[trim($k)] = trim($v); } }
    if (!$h) return null; $len = (int)($h['Content-Length'] ?? 0); $body = '';
    while (strlen($body) < $len) { $c = fread($fp, $len - strlen($body)); if ($c === false || $c === '') break; $body .= $c; }
    return [$h, $body];
  }
  private static function evParse(string $body): array {
    $parts = explode("\n\n", $body, 2); $e = []; foreach (explode("\n", $parts[0]) as $l) { if (str_contains($l, ':')) { [$k, $v] = explode(':', $l, 2); $e[trim($k)] = urldecode(trim($v)); } } $e['_body'] = $parts[1] ?? ''; return $e;
  }
  /** Runs one call to the end (about a minute). Started in the background by spawn(). */
  public static function session(int $rowId): void {
    @set_time_limit(180); $a = DB::table('order_call_verifications')->find($rowId); if (!$a || $a->status !== 'calling') return;
    $upd = fn(array $u) => DB::table('order_call_verifications')->where('id', $rowId)->update($u + ['updated_at' => now()]);
    $text = $a->order_id ? null : self::testScript();
    if ($a->order_id) { $o = DB::table('orders')->find($a->order_id); if (!$o) { $upd(['status' => 'failed', 'cause' => 'order not found', 'ended_at' => now()]); return; } $text = self::script($o, DB::table('order_items')->where('order_id', $o->id)->get()); }
    $U = []; foreach (['main' => $text, 'retry' => 'অর্ডারটি নিশ্চিত করতে ১ চাপুন। পেন্ডিং রাখতে ২ চাপুন।', 'ok' => 'ধন্যবাদ। আপনার অর্ডারের তথ্য নিশ্চিত করা হয়েছে। আমাদের টিম বাকি যাচাই শেষ করবে।', 'hold' => 'ঠিক আছে। আপনার অনুরোধ অনুযায়ী অর্ডারটি পেন্ডিং রাখা হয়েছে। আমাদের টিম আপনার সঙ্গে যোগাযোগ করবে। ধন্যবাদ।', 'none' => 'আপনার উত্তর পাওয়া যায়নি। অর্ডারটি যাচাইয়ের অপেক্ষায় আছে। ধন্যবাদ।'] as $k => $t) {
      [$u, $err] = self::promptUrl($t); if (!$u) { $upd(['status' => 'failed', 'cause' => mb_substr((string)$err, 0, 200), 'ended_at' => now()]); return; } $U[$k] = $u;
    }
    // the PBX's own http_get downloads each prompt into its cache and gives back a local file path; playing that path is what works (a direct https play returns FILE NOT FOUND)
    foreach ($U as $k => $u) { [$gok, $gout] = Voice::esl('http_get ' . $u, 30); $lp = trim((string)$gout); if ($gok && preg_match('~^/[\w./-]+\.wav$~', $lp)) $U[$k] = $lp; }
    $h = Voice::set('esl_host') ?: (string)env('PBX_ESL_HOST', ''); $port = (int)(Voice::set('esl_port') ?: env('PBX_ESL_PORT', 8021)); $pw = Sms::secret('vc_esl_pass') ?: (string)env('PBX_ESL_PASSWORD', '');
    $fp = @fsockopen($h, $port, $en, $es, 6); if (!$fp) { $upd(['status' => 'failed', 'cause' => 'PBX is not reachable', 'ended_at' => now()]); return; } stream_set_timeout($fp, 1);
    $send = function (string $c) use ($fp) { fwrite($fp, $c . "\n\n"); };
    $max = max(1, (int)self::set('max_attempts', '2')); $gap = max(10, (int)self::set('retry_gap', '60')); $test = (bool)$a->test;
    $finish = function (string $st, string $cause = '', int $dur = 0) use ($upd, $a, $max, $gap, $test) {
      $retry = !$test && $a->attempt < $max && in_array($st, ['no_answer', 'busy', 'failed', 'no_input'], true);
      $upd(['status' => $st, 'cause' => mb_substr($cause, 0, 240) ?: null, 'duration' => $dur, 'ended_at' => now(), 'next_attempt_at' => $retry ? now()->addMinutes($gap) : null]);
    };
    $uuid = (string)\Illuminate\Support\Str::uuid(); $job = (string)\Illuminate\Support\Str::uuid();
    try {
      self::frame($fp); $send('auth ' . $pw); $r = self::frame($fp); if (!$r || !str_contains(json_encode($r[0]), '+OK')) { $finish('failed', 'PBX rejected the password'); fclose($fp); return; }
      $send('event plain CHANNEL_ANSWER CHANNEL_HANGUP_COMPLETE DTMF PLAYBACK_START PLAYBACK_STOP CHANNEL_EXECUTE_COMPLETE BACKGROUND_JOB'); self::frame($fp);
      $send('filter Unique-ID ' . $uuid); self::frame($fp); $send('filter Job-UUID ' . $job); self::frame($fp);
      $ring = (int)Voice::set('ring_timeout', '30'); $caller = Voice::set('caller_id') ?: '0000';
      $send("bgapi originate {origination_uuid=$uuid,origination_caller_id_number=$caller,origination_caller_id_name=DutyFreeBD,originate_timeout=$ring,ignore_early_media=true}sofia/gateway/" . Voice::set('gateway') . '/' . Voice::dialNumber((string)$a->phone) . " &park()\nJob-UUID: $job");
      $trace = []; $deadline = time() + $ring + 100; $state = 'dialing'; $stage = 0; $waitUntil = null; $closeBy = null; $answered = false; $done = false; $digit = ''; $cause = ''; $dur = 0; $hung = false;
      $playing = false; $skip = 0; $play = function (string $k) use ($send, $uuid, $U, &$playing) { $playing = true; $send("api uuid_broadcast $uuid {$U[$k]} aleg"); };
      while (time() < $deadline) {
        $f = self::frame($fp);
        if ($f === null) {
          if ($state === 'wait' && $waitUntil && time() >= $waitUntil) { if ($stage === 0) { $stage = 1; $state = 'play'; $waitUntil = null; $play('retry'); } else { $state = 'closing'; $closeBy = time() + 12; $waitUntil = null; $play('none'); } }
          if ($state === 'closing' && $closeBy && time() >= $closeBy) { $send("api uuid_kill $uuid"); $closeBy = time() + 5; }
          continue;
        }
        if (($f[0]['Content-Type'] ?? '') !== 'text/event-plain') { $b = trim((string)$f[1]); if ($b !== '' || !empty($f[0]['Reply-Text'])) { $trace[] = ['t' => date('H:i:s'), 'reply' => mb_substr($b !== '' ? $b : (string)$f[0]['Reply-Text'], 0, 160)]; $upd(['webhook_payload' => json_encode(array_slice($trace, -40), JSON_UNESCAPED_UNICODE)]); } continue; }
        $e = self::evParse($f[1]); $n = $e['Event-Name'] ?? '';
        $trace[] = ['t' => date('H:i:s'), 'ev' => $n, 'digit' => $e['DTMF-Digit'] ?? null, 'app' => $e['Application'] ?? null, 'resp' => isset($e['Application-Response']) ? mb_substr($e['Application-Response'], 0, 100) : null, 'file' => isset($e['Playback-File-Path']) ? mb_substr($e['Playback-File-Path'], 0, 120) : (isset($e['Application-Data']) ? mb_substr($e['Application-Data'], 0, 120) : null), 'cause' => $e['Hangup-Cause'] ?? null];
        $upd(['webhook_payload' => json_encode(array_slice($trace, -40), JSON_UNESCAPED_UNICODE)]);
        if ($n === 'BACKGROUND_JOB') { $res = trim($e['_body']); if (str_starts_with($res, '-ERR')) { $cause = trim(preg_replace('/^-ERR\s*/', '', $res)); break; } }
        elseif ($n === 'CHANNEL_ANSWER' && !$answered) { $answered = true; $upd(['status' => 'answered', 'answered_at' => now()]); $state = 'play'; usleep(400000); $play('main'); }
        elseif ($n === 'PLAYBACK_STOP') { $playing = false; if ($skip > 0) { $skip--; continue; } if ($state === 'play') { $state = 'wait'; $waitUntil = time() + 7; } elseif ($state === 'closing') { $send("api uuid_kill $uuid"); } }
        elseif ($n === 'DTMF' && !$done) { $g = substr((string)($e['DTMF-Digit'] ?? ''), 0, 1); if ($g === '1' || $g === '2') { $done = true; $digit = $g; DB::table('order_call_verifications')->where('id', $rowId)->update(['digit' => $g, 'updated_at' => now()]); $skip = $playing ? 1 : 0; $send("api uuid_break $uuid all"); $state = 'closing'; $closeBy = time() + 14; $waitUntil = null; $play($g === '1' ? 'ok' : 'hold'); self::apply(DB::table('order_call_verifications')->find($rowId), $g); } }
        elseif ($n === 'CHANNEL_HANGUP_COMPLETE') { $hung = true; $cause = $e['Hangup-Cause'] ?? ''; $dur = (int)($e['variable_billsec'] ?? 0); break; }
      }
      if (!$hung) { $send("api uuid_kill $uuid"); usleep(300000); }
      $now = DB::table('order_call_verifications')->find($rowId);
      if ($done || ($now && in_array($now->status, ['confirmed', 'pending_requested', 'stale'], true))) { $upd(['ended_at' => now(), 'duration' => $dur]); }
      elseif ($answered) $finish('no_input', $cause, $dur);
      else { $u = strtoupper($cause); $finish(str_contains($u, 'BUSY') ? 'busy' : (preg_match('/NO_ANSWER|NO_USER_RESPONSE|ORIGINATOR_CANCEL|RECOVERY_ON_TIMER|NORMAL_CLEARING/', $u) ? 'no_answer' : 'failed'), $cause ?: 'no answer from the PBX', $dur); }
    } catch (\Throwable $e) { Log::warning('order call session: ' . $e->getMessage()); $finish('failed', 'call session error'); }
    @fclose($fp);
  }
  private static function spawn(int $id): bool {
    try { $php = 'php'; $cmd = 'nohup ' . $php . ' ' . escapeshellarg(base_path('artisan')) . ' ordercall:run ' . $id . ' > /dev/null 2>&1 &'; @exec($cmd, $o, $rc); return $rc === 0; } catch (\Throwable $e) { return false; }
  }
  private static function place(string $phone, string $text, ?int $orderId, ?string $ver, int $attempt, bool $test): array {
    if (!self::pbxReady()) return ['failed', 'PBX call setup is not complete (see readiness list)', null];
    $id = DB::table('order_call_verifications')->insertGetId(['order_id' => $orderId, 'version_hash' => $ver, 'attempt' => $attempt, 'phone' => $phone, 'status' => 'calling', 'started_at' => now(), 'test' => $test, 'created_at' => now(), 'updated_at' => now()]);
    if (!self::spawn($id)) { try { self::session($id); } catch (\Throwable $e) { report($e); } }
    return ['calling', '', $id];
  }
  /** Read-only PBX diagnostics (fixed commands only). */
  public static function diag(string $what): array {
    if ($what === 'last') { $r = DB::table('order_call_verifications')->orderByDesc('id')->limit(2)->get(['id', 'status', 'digit', 'cause', 'webhook_payload']); return [true, json_encode($r, JSON_UNESCAPED_UNICODE)]; }
    if (in_array($what, ['fetch', 'fetchhttp'], true)) {
      [$u, $err] = self::promptUrl('পরীক্ষা'); if (!$u) return [false, (string)$err]; if ($what === 'fetchhttp') $u = preg_replace('~^https:~', 'http:', $u);
      [$ok, $out] = Voice::esl('curl ' . $u . ' headers connect-timeout 6 timeout 10', 25);
      return [$ok, 'url_scheme ' . strtok($u, ':') . ' | ' . preg_replace('/[^\x20-\x7e\n]/', '.', mb_substr((string)$out, 0, 700))];
    }
    if (in_array($what, ['httpget', 'httpgethttp'], true)) {
      [$u, $err] = self::promptUrl('পরীক্ষা'); if (!$u) return [false, (string)$err]; if ($what === 'httpgethttp') $u = preg_replace('~^https:~', 'http:', $u);
      [$ok, $out] = Voice::esl('http_get ' . $u, 30);
      return [$ok, 'scheme ' . strtok($u, ':') . ' | ' . preg_replace('/[^\x20-\x7e\n]/', '.', mb_substr((string)$out, 0, 500))];
    }
    $cmds = ['gw' => 'sofia status gateway', 'calls' => 'show calls count', 'channels' => 'show channels count'];
    if (!isset($cmds[$what])) return [false, 'unknown'];
    return Voice::esl($cmds[$what], 25);
  }

  /** Owner test button: a sample call to a number in the test list. No order is touched. */
  public static function testCall(string $phone): array {
    $p = Voice::norm($phone); if (!in_array($p, self::testPhones(), true)) return ['failed', 'This number is not in the test list.', null];
    return self::place($p, self::testScript(), null, null, 1, true);
  }
  private static function dial(object $o, string $ver, int $attempt): void {
    $items = DB::table('order_items')->where('order_id', $o->id)->get(); $p = Voice::norm((string)$o->phone);
    [$st, $cause, $id] = self::place($p, self::script($o, $items), (int)$o->id, $ver, $attempt, in_array($p, self::testPhones(), true));
    if ($id) OrderActivity::log((int)$o->id, 'ai_call', null, $st, 'AI confirmation call, attempt ' . $attempt . ': ' . $st . ($st === 'failed' ? ' (' . mb_substr($cause, 0, 80) . ')' : ''), ['call_row' => $id]);
  }

  /** Worker tick (every cycle): close stuck calls, then call what is due. */
  public static function tick(): void {
    if (self::mode() === 'off' || !self::ready() || !self::pbxReady()) return;
    $lock = Cache::lock('ocv_tick', 90); if (!$lock->get()) return;
    try {
      $max = max(1, (int)self::set('max_attempts', '2')); $gap = max(10, (int)self::set('retry_gap', '60'));
      foreach (DB::table('order_call_verifications')->whereIn('status', ['calling', 'answered'])->where('updated_at', '<', now()->subMinutes(4))->get() as $r) {
        $st = $r->status === 'answered' ? 'no_input' : 'no_answer';
        DB::table('order_call_verifications')->where('id', $r->id)->update(['status' => $st, 'ended_at' => now(), 'next_attempt_at' => (!$r->test && $r->attempt < $max) ? now()->addMinutes($gap) : null, 'updated_at' => now()]);
      }
      $t = now('Asia/Dhaka')->format('H:i'); if ($t < self::set('start', '09:00') || $t >= self::set('end', '21:00')) return;
      $active = DB::table('order_call_verifications')->whereIn('status', ['calling', 'answered'])->where('updated_at', '>', now()->subMinutes(4))->count();
      $cap = max(1, (int)self::set('concurrency', '2')); if ($active >= $cap) return; $t0 = time();
      $orders = DB::table('orders')->where('status', 'New')->where('created_at', '>=', now()->subHours(max(1, (int)self::set('hours_back', '24'))))->orderBy('id')->limit(40)->get();
      foreach ($orders as $o) {
        if (time() - $t0 > 45 || $active >= $cap) return;
        if (self::eligible($o) !== null) continue; $ver = self::version($o);
        $rows = DB::table('order_call_verifications')->where('order_id', $o->id)->where('version_hash', $ver)->orderByDesc('id')->get();
        if ($rows->contains(fn($r) => in_array($r->status, ['confirmed', 'pending_requested'], true))) continue;
        if ($rows->contains(fn($r) => in_array($r->status, ['calling', 'answered'], true))) continue;
        $n = $rows->count(); if ($n >= $max) continue; $last = $rows->first();
        if ($last && (!$last->next_attempt_at || \Carbon\Carbon::parse($last->next_attempt_at)->isFuture())) continue;
        if ($n === 0 && $o->created_at && \Carbon\Carbon::parse($o->created_at)->gt(now()->subSeconds(90))) continue; // let the order settle first
        if (self::staffCall($o)) continue;
        if (DB::table('order_call_verifications')->where('phone', Voice::norm((string)$o->phone))->whereIn('status', ['calling', 'answered'])->where('updated_at', '>', now()->subMinutes(4))->exists()) continue;
        self::dial($o, $ver, $n + 1); $active++;
      }
    } finally { $lock->release(); }
  }

  // ---------------- PBX webhook (called from Voice::hook before the campaign lookup)
  public static function hook(array $d): ?array {
    if (!self::ready()) return null; $uuid = (string)($d['uuid'] ?? $d['call_id'] ?? ''); if ($uuid === '') return null;
    $a = DB::table('order_call_verifications')->where('pbx_call_id', $uuid)->orderByDesc('id')->first(); if (!$a) return null;
    $log = json_decode((string)($a->webhook_payload ?? ''), true) ?: []; $log[] = ['at' => now()->toIso8601String(), 'data' => $d]; $log = array_slice($log, -30);
    DB::table('order_call_verifications')->where('id', $a->id)->update(['webhook_payload' => json_encode($log, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    $ev = (string)($d['ev'] ?? $d['event'] ?? '');
    if ($ev === 'answered') { if ($a->status === 'calling') DB::table('order_call_verifications')->where('id', $a->id)->update(['status' => 'answered', 'answered_at' => now(), 'updated_at' => now()]); }
    elseif ($ev === 'digit') {
      $g = substr((string)($d['digit'] ?? ''), 0, 1); if (!in_array($a->status, ['calling', 'answered', 'no_input'], true)) return ['ok' => true];
      DB::table('order_call_verifications')->where('id', $a->id)->update(['digit' => $g, 'updated_at' => now()]);
      if ($g === '1' || $g === '2') self::apply($a, $g);
    } elseif ($ev === 'hangup') {
      $dur = max(0, (int)($d['duration'] ?? 0)); $rec = ($d['recording'] ?? null) ? mb_substr((string)$d['recording'], 0, 250) : null; $max = max(1, (int)self::set('max_attempts', '2'));
      $upd = ['duration' => $dur, 'ended_at' => now(), 'recording_ref' => $rec ?: $a->recording_ref, 'updated_at' => now()];
      if (in_array($a->status, ['calling', 'answered'], true)) { $upd['status'] = $a->status === 'answered' ? 'no_input' : 'no_answer'; $upd['next_attempt_at'] = (!$a->test && $a->attempt < $max) ? now()->addMinutes(max(10, (int)self::set('retry_gap', '60'))) : null; }
      DB::table('order_call_verifications')->where('id', $a->id)->update($upd);
    }
    return ['ok' => true];
  }
  /** Key 1 or 2 pressed. Idempotent; the order version heard must still be the current version. */
  private static function apply(object $a, string $g): void {
    $upd = ['ended_at' => now(), 'updated_at' => now()];
    if (!$a->order_id) { DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => $g === '1' ? 'confirmed' : 'pending_requested']); return; } // owner test call
    $o = DB::table('orders')->find($a->order_id); if (!$o) { DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => 'failed', 'cause' => 'order not found']); return; }
    if ($a->version_hash !== self::version($o)) {
      DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => 'stale', 'cause' => 'order changed during or after the call; new confirmation needed']);
      OrderActivity::log((int)$o->id, 'ai_call_stale', null, 'stale', 'Customer pressed ' . $g . ' but the order changed after the call: not applied. A new confirmation or staff review is needed.', ['call_row' => $a->id]); return;
    }
    DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => $g === '1' ? 'confirmed' : 'pending_requested', 'next_attempt_at' => null]);
    OrderActivity::log((int)$o->id, $g === '1' ? 'ai_call_confirmed' : 'ai_call_pending', null, $g === '1' ? 'confirmed' : 'pending', $g === '1' ? 'Customer pressed 1 on the AI call: order confirmed (order status: ' . $o->status . ')' : 'Customer pressed 2 on the AI call: asked to keep the order pending (order status: ' . $o->status . ')', ['call_row' => $a->id]);
    if ($o->status === 'New') { try { AiVerify::run((int)$o->id, 'ai-call', false, true); } catch (\Throwable $e) { report($e); } } // refresh the panel; auto-approval still follows every existing rule
  }

  /** For the settings page. */
  public static function exceptions(int $limit = 40)
  {
    if (!self::ready()) return collect();
    return DB::table('order_call_verifications as v')->leftJoin('orders as o', 'o.id', '=', 'v.order_id')->whereIn('v.status', ['pending_requested', 'no_input', 'no_answer', 'busy', 'failed', 'stale'])->where('v.test', false)->where('o.status', 'New')
      ->orderByDesc('v.id')->limit($limit)->get(['v.*', 'o.invoice', 'o.customer_name', 'o.total']);
  }
}
