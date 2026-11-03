<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Promotional Voice Campaigns: last ordered product -> personalised % offer -> scheduled PBX call -> conversion tracking. */
class Voice
{
  const SCRIPT = 'আসসালামু আলাইকুম। DutyFreeBD থেকে বলছি। আপনার সর্বশেষ অর্ডার করা {product}-এর উপর আপনার জন্য বিশেষ {pct} শতাংশ ডিসকাউন্ট রয়েছে। অফারটি সীমিত সময়ের জন্য। আপনার অফারটি আগামী {days} দিন পর্যন্ত কার্যকর থাকবে। অর্ডার করতে DutyFreeBD ওয়েবসাইটে ভিজিট করুন অথবা আমাদের প্রতিনিধির সাথে যোগাযোগ করুন। ধন্যবাদ।';
  const MENU = ' অফারটি নিতে ১ চাপুন। আমাদের প্রতিনিধির সাথে কথা বলতে ২ চাপুন। এই ধরনের অফার কল আর পেতে না চাইলে ৯ চাপুন।';
  const SMS = 'DutyFreeBD: আপনার সর্বশেষ অর্ডার করা {product} এর উপর {pct}% বিশেষ ডিসকাউন্ট রয়েছে। অফারটি {date} পর্যন্ত কার্যকর।';
  const SEGMENTS = ['all' => 'All eligible customers', 'inactive_15' => 'Inactive 15–29 days', 'inactive_30' => 'Inactive 30–44 days', 'inactive_45' => 'Inactive 45–54 days', 'inactive_55' => 'Inactive 55+ days', 'vip' => 'VIP customers', 'regular' => 'Regular customers', 'selected' => 'Selected customers (phone list)', 'custom' => 'Custom filter'];
  const STATUS = ['pending' => 'Pending', 'calling' => 'Calling', 'answered' => 'Answered', 'not_answered' => 'Not Answered', 'busy' => 'Busy', 'failed' => 'Failed', 'interested' => 'Interested', 'transferred' => 'Transferred to Agent', 'accepted' => 'Offer Accepted', 'opted_out' => 'Opted Out'];

  public static function set(string $k, string $def = ''): string { $v = DB::table('settings')->where('key', 'vc_' . $k)->value('value'); return $v === null ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'vc_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function on(): bool { return self::set('on', '0') === '1'; }
  public static function norm(string $p): string { return substr(preg_replace('/\D/', '', $p), -11); }
  public static function validPhone(string $p): bool { return (bool)preg_match('/^01[3-9]\d{8}$/', $p); }
  public static function hookToken(): string { $t = self::set('hook_token'); if (strlen($t) < 20) { $t = bin2hex(random_bytes(16)); self::put('hook_token', $t); } return $t; }
  public static function baseUrl(): string { return rtrim(self::set('base_url') ?: (string)config('app.url'), '/'); }
  public static function audioToken(int $id): string { return substr(hash_hmac('sha256', 'vcaudio' . $id, (string)config('app.key')), 0, 24); }
  public static function bn(string $s): string { return strtr($s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']); }
  public static function pct(float $v): string { return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'); }

  /** Readiness checklist shown on the admin page. */
  public static function readiness(): array {
    return [
      ['PBX call origination (Event Socket)', (self::set('esl_host') !== '' || env('PBX_ESL_HOST')) && (Sms::secret('vc_esl_pass') || env('PBX_ESL_PASSWORD')) && self::set('gateway') !== '', 'PBX host, port, password and outbound gateway name'],
      ['Voice (Bangla text-to-speech)', self::set('tts_provider', 'none') !== 'none' && (bool)Sms::secret('vc_tts_key'), 'Azure or Google key, or upload a prerecorded audio per campaign'],
      ['PBX call script installed (voice_offer)', self::set('script_ok') === '1', 'Install voice_offer.lua on the PBX once and tick "installed"'],
      ['Follow-up SMS gateway', Sms::configured(), 'SMS API key and sender ID'],
    ];
  }

  // ---------- script / audio
  public static function render(string $t, array $v): string { return trim(strtr($t, $v)); }
  public static function vars(object $c, object $row): array {
    $exp = $c->expiry_date ? \Carbon\Carbon::parse($c->expiry_date)->timezone('Asia/Dhaka') : now('Asia/Dhaka')->addDays((int)$c->validity_days);
    $days = $c->expiry_date ? max(1, now('Asia/Dhaka')->startOfDay()->diffInDays($exp->copy()->startOfDay(), false)) : (int)$c->validity_days;
    return ['{product}' => $row->product_name, '{pct}' => self::bn(self::pct((float)$row->discount_value)), '{pct_en}' => self::pct((float)$row->discount_value), '{days}' => self::bn((string)$days), '{name}' => (string)$row->name, '{date}' => $exp->format('d M Y')];
  }
  public static function scriptFor(object $c, object $row): string {
    $t = trim((string)$c->script) !== '' ? $c->script : self::SCRIPT; $s = self::render($t, self::vars($c, $row));
    if ($c->dtmf_on && !str_contains($t, '১ চাপুন')) $s .= self::MENU; return $s;
  }
  /** Local audio file for one customer's call (cached). @return array{?string,string} */
  public static function audio(object $c, object $row): array {
    if ($c->audio_path && is_file(storage_path('app/' . $c->audio_path))) return [storage_path('app/' . $c->audio_path), ''];
    $text = self::scriptFor($c, $row); $dir = storage_path('app/voice'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $f = $dir . '/' . md5(self::set('tts_provider') . self::set('tts_voice') . $text) . '.wav'; if (is_file($f) && filesize($f) > 1000) return [$f, ''];
    $prov = self::set('tts_provider', 'none'); $key = Sms::secret('vc_tts_key'); if ($prov === 'none' || !$key) return [null, 'No voice engine set up: add a text-to-speech key in Voice settings or upload an audio file to the campaign.'];
    try {
      if ($prov === 'azure') {
        $reg = self::set('tts_region', 'southeastasia'); $voice = self::set('tts_voice', 'bn-BD-NabanitaNeural');
        $ssml = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="bn-BD"><voice name="' . e($voice) . '">' . e($text) . '</voice></speak>';
        $r = Http::timeout(30)->withHeaders(['Ocp-Apim-Subscription-Key' => $key, 'Content-Type' => 'application/ssml+xml', 'X-Microsoft-OutputFormat' => 'riff-8khz-16bit-mono-pcm', 'User-Agent' => 'MotherCommerce'])->withBody($ssml, 'application/ssml+xml')->post("https://$reg.tts.speech.microsoft.com/cognitiveservices/v1");
        if (!$r->ok() || strlen($r->body()) < 1000) return [null, 'Voice engine error ' . $r->status()]; file_put_contents($f, $r->body()); return [$f, ''];
      }
      $voice = self::set('tts_voice', 'bn-IN-Wavenet-A');
      $r = Http::timeout(30)->post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode($key), ['input' => ['text' => $text], 'voice' => ['languageCode' => 'bn-IN', 'name' => $voice], 'audioConfig' => ['audioEncoding' => 'LINEAR16', 'sampleRateHertz' => 8000]]);
      $b = base64_decode((string)$r->json('audioContent')); if (!$r->ok() || strlen($b) < 1000) return [null, 'Voice engine error ' . $r->status()]; file_put_contents($f, $b); return [$f, ''];
    } catch (\Throwable $e) { return [null, 'Voice engine unreachable']; }
  }

  // ---------- audience
  /** Last-order product of a delivered order: the highest-value line. */
  public static function lastProduct(int $orderId): ?object {
    return DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $orderId)->orderByRaw('i.qty * i.price desc')->first(['p.id as pid', 'p.name as pname', 'p.published', 'p.stock', 'i.price', 'i.name as iname']);
  }
  /** Eligible customers for a campaign. @return array{rows:array,skipped:array} */
  public static function candidates(object $c, int $limit = 100000): array {
    $cfg = json_decode((string)$c->segment_cfg, true) ?: []; $rows = []; $skip = []; $seg = $c->segment;
    $sel = null; if ($seg === 'selected') { $sel = array_values(array_filter(array_map(fn($x) => self::norm($x), preg_split('/[\s,;]+/', (string)$c->segment_phones)))); }
    $last = $sel !== null ? [] : Winback::lastDelivered(); if ($sel !== null) foreach ($sel as $p) { $x = Winback::lastDelivered($p); if ($x) $last += $x; else $skip[$p] = 'No delivered order, or a newer open order exists'; }
    $cool = now()->subDays(max(0, (int)$c->cooldown_days)); $already = DB::table('voice_campaign_customers')->where('campaign_id', $c->id)->pluck('phone')->flip()->all();
    $recent = DB::table('voice_call_attempts')->where('test', false)->where('started_at', '>', $cool)->pluck('phone')->flip()->all();
    $custs = DB::table('customers')->whereIn(DB::raw('right(phone,11)'), array_keys($last) ?: ['0'])->get()->keyBy(fn($x) => self::norm($x->phone));
    foreach ($last as $p => $x) {
      if (isset($already[$p])) continue; $d = Winback::days($x['at']); $cu = $custs[$p] ?? null;
      $ok = match ($seg) { 'inactive_15' => $d >= 15 && $d < 30, 'inactive_30' => $d >= 30 && $d < 45, 'inactive_45' => $d >= 45 && $d < 55, 'inactive_55' => $d >= 55, 'vip' => $cu && strtolower((string)($cu->type ?? '')) === 'vip', 'regular' => $cu && strtolower((string)($cu->type ?? '')) !== 'vip',
        'custom' => $d >= (int)($cfg['min_days'] ?? 0) && $d <= ((int)($cfg['max_days'] ?? 0) ?: 100000) && (!($cfg['category'] ?? '') || (($cfg['category'] === 'vip') === ($cu && strtolower((string)($cu->type ?? '')) === 'vip'))), default => true };
      if (!$ok) continue;
      if (!$cu) { $skip[$p] = 'No customer account'; continue; } if ($cu->blocked) { $skip[$p] = 'Customer blocked'; continue; }
      if (!($cu->promo_call ?? true) || self::optedOut($p)) { $skip[$p] = 'Voice offer opt-out'; continue; } if (!self::validPhone($p)) { $skip[$p] = 'Invalid phone number'; continue; }
      if (isset($recent[$p])) { $skip[$p] = 'Called within the last ' . $c->cooldown_days . ' days'; continue; }
      if (($cfg['min_orders'] ?? 0) && OrderDiscount::stats($p, $cu)['n'] < (int)$cfg['min_orders']) { $skip[$p] = 'Fewer orders than required'; continue; }
      $pr = self::lastProduct($x['oid']); if (!$pr) { $skip[$p] = 'Last order has no catalogue product'; continue; }
      if (!$pr->published) { $skip[$p] = 'Product is not active'; continue; } if ((int)$pr->stock <= 0) { $skip[$p] = 'Product out of stock'; continue; }
      $rows[] = ['customer_id' => $cu->id, 'name' => $cu->name, 'phone' => $p, 'product_id' => $pr->pid, 'product_name' => $pr->pname, 'last_price' => (float)$pr->price, 'order_id' => $x['oid'], 'last_order_at' => $x['at'], 'days' => $d];
      if (count($rows) >= $limit) break;
    }
    return ['rows' => $rows, 'skipped' => $skip];
  }
  /** Create the personal offers (campaign customers). Returns number added. */
  public static function enroll(object $c): int {
    $cand = self::candidates($c); $n = 0;
    foreach ($cand['rows'] as $r) {
      $ins = ['campaign_id' => $c->id, 'customer_id' => $r['customer_id'], 'product_id' => $r['product_id'], 'order_id' => $r['order_id'], 'phone' => $r['phone'], 'name' => $r['name'], 'product_name' => $r['product_name'], 'last_price' => $r['last_price'], 'last_order_at' => $r['last_order_at'], 'discount_value' => $c->discount_value, 'created_at' => now(), 'updated_at' => now()];
      try { DB::table('voice_campaign_customers')->insert($ins); $n++; } catch (\Throwable $e) {}
    }
    self::put('enrolled_' . $c->id, now('Asia/Dhaka')->toDateString()); return $n;
  }

  // ---------- calling
  public static function esl(string $cmd, int $timeout = 45): array {
    $h = self::set('esl_host') ?: (string)env('PBX_ESL_HOST', ''); $port = (int)(self::set('esl_port') ?: env('PBX_ESL_PORT', 8021)); $pw = Sms::secret('vc_esl_pass') ?: (string)env('PBX_ESL_PASSWORD', ''); if ($h === '' || !$pw) return [false, 'PBX connection is not set up'];
    $fp = @fsockopen($h, $port, $en, $es, 6); if (!$fp) return [false, 'PBX is not reachable (' . ($es ?: 'timeout') . ')']; stream_set_timeout($fp, $timeout);
    $rd = function () use ($fp) { $hd = ''; while (($l = fgets($fp)) !== false) { if (trim($l) === '') break; $hd .= $l; } $len = 0; if (preg_match('/Content-Length:\s*(\d+)/i', $hd, $m)) $len = (int)$m[1]; return [$hd, $len ? fread($fp, $len) : '']; };
    try {
      $rd(); fwrite($fp, "auth $pw\n\n"); [$h1] = $rd(); if (!str_contains($h1, '+OK')) { fclose($fp); return [false, 'PBX rejected the password']; }
      fwrite($fp, "api $cmd\n\n"); [, $body] = $rd(); fclose($fp); return [true, trim($body)];
    } catch (\Throwable $e) { return [false, 'PBX connection lost']; }
  }
  public static function dialNumber(string $p): string { $f = self::set('dial_format', 'local'); return $f === 'intl' ? '88' . $p : ($f === 'plus' ? '+88' . $p : $p); }
  /** Place one call now. @return array{string,string,string} [status, cause, uuid] */
  /** Full PBX-side path of a recording picked in the campaign (file name from FusionPBX → Recordings, or a full path). Null when the campaign does not use a PBX recording. */
  public static function pbxPath(object $c): ?string {
    $n = trim((string)($c->pbx_audio ?? '')); if ($n === '') return null; if (str_contains($n, '..') || !preg_match('#^[A-Za-z0-9_./ \-]+$#', $n)) return null;
    if (str_starts_with($n, '/')) return $n; $d = rtrim(self::set('pbx_rec_dir'), '/'); return $d !== '' ? $d . '/' . $n : null;
  }
  public static function originate(object $c, object $row): array {
    $pp = self::pbxPath($c);
    if (!empty($c->pbx_audio) && !$pp) return ['failed', 'The PBX recording name is not valid, or the PBX recordings folder is not set in Voice settings.', ''];
    if ($pp) { $f = null; $err = ''; } else { [$f, $err] = self::audio($c, $row); if (!$f) return ['failed', $err, '']; }
    $id = (int)($row->id ?? 0); $uuid = self::uuid();
    if ($pp) $url = $pp; else { $aid = self::audioId($f); $url = self::baseUrl() . '/voice-audio/' . $aid . '.' . strtolower(pathinfo($f, PATHINFO_EXTENSION)) . '?t=' . self::audioToken((int)$aid); self::linkAudio($aid, $f); }
    $var = ["origination_uuid=$uuid", 'origination_caller_id_number=' . (self::set('caller_id') ?: '0000'), 'origination_caller_id_name=DutyFreeBD', 'originate_timeout=' . (int)self::set('ring_timeout', '30'), 'ignore_early_media=true',
      "vc_id=$id", "vc_audio='$url'", "vc_hook='" . self::baseUrl() . '/webhooks/voice?k=' . self::hookToken() . "'", 'vc_dtmf=' . ($c->dtmf_on ? '1' : '0'), 'vc_agent=' . (self::set('agent_ext') ?: '101'), 'vc_rec=' . self::recName($id, $row->phone, $uuid)];
    $cmd = 'originate {' . implode(',', $var) . '}sofia/gateway/' . self::set('gateway') . '/' . self::dialNumber($row->phone) . ' ' . (self::set('dialplan_ext') ?: (string)env('PBX_DESTINATION', 'voice_offer')) . ' XML ' . (self::set('context') ?: (string)env('PBX_CONTEXT', 'public'));
    [$ok, $out] = self::esl($cmd, (int)self::set('ring_timeout', '30') + 15); if (!$ok) return ['failed', $out, $uuid];
    if (str_starts_with($out, '+OK')) { $job = trim(substr($out, 3)); if ($job !== '') self::$lastJob = $job; return ['answered', '', $uuid]; }
    $cause = trim(preg_replace('/^-ERR\s*/', '', $out)); $u = strtoupper($cause);
    $st = str_contains($u, 'BUSY') ? 'busy' : (preg_match('/NO_ANSWER|NO_USER_RESPONSE|ORIGINATOR_CANCEL|RECOVERY_ON_TIMER/', $u) ? 'not_answered' : 'failed');
    return [$st, $cause, $uuid];
  }
  public static ?string $lastJob = null;
  public static function recName($id, string $phone, string $uuid): string { return preg_replace('/[^A-Za-z0-9_\-]/', '', 'voiceoffer_' . ($uuid ?: $id) . '_' . $phone . '_' . now('Asia/Dhaka')->format('YmdHis')) . '.mp3'; }
  private static function uuid(): string { $b = random_bytes(16); $b[6] = chr(ord($b[6]) & 0x0f | 0x40); $b[8] = chr(ord($b[8]) & 0x3f | 0x80); return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4)); }
  private static function audioId(string $f): string { return (string)(hexdec(substr(md5(basename($f)), 0, 7))); }
  private static function linkAudio(string $id, string $f): void { Cache::put('vcaudio.' . $id, $f, now()->addDay()); }
  public static function audioFile(int $id): ?string { $f = Cache::get('vcaudio.' . $id); if (!$f) { foreach (glob(storage_path('app/voice/*.wav')) ?: [] as $x) if (self::audioId($x) === (string)$id) { $f = $x; break; } if (!$f) foreach (glob(storage_path('app/voicecamp/*')) ?: [] as $x) if (self::audioId($x) === (string)$id) { $f = $x; break; } } return $f && is_file($f) ? $f : null; }


  /** Admin "Test Call": rings ONE number with a sample offer; creates no offer and touches no customer. */
  public static function testCall(object $c, string $phone, ?string $product = null): array {
    $p = self::norm($phone); if (!self::validPhone($p)) return [false, 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).'];
    $row = (object)['id' => 0, 'phone' => $p, 'name' => 'Test', 'product_name' => $product ?: 'Patron Nano White Special', 'discount_value' => $c->discount_value, 'customer_id' => null];
    $aid = DB::table('voice_call_attempts')->insertGetId(['vc_id' => 0, 'campaign_id' => $c->id, 'phone' => $p, 'attempt' => 1, 'started_at' => now(), 'result' => 'calling', 'test' => true, 'created_at' => now(), 'updated_at' => now()]);
    [$st, $cause, $uuid] = self::originate($c, $row);
    DB::table('voice_call_attempts')->where('id', $aid)->update(['pbx_call_id' => $uuid ?: null, 'pbx_job_uuid' => self::$lastJob, 'recording_name' => self::recName($row->id ?? 0, $row->phone, $uuid), 'error' => in_array($st, ['failed', 'busy', 'not_answered']) ? mb_substr($cause, 0, 250) : null, 'result' => $st, 'cause' => mb_substr($cause, 0, 60), 'updated_at' => now()]); self::$lastJob = null;
    return [$st === 'answered', $st === 'answered' ? 'Test call was answered.' : 'Test call result: ' . $st . ($cause ? ' — ' . $cause : '') . '.'];
  }

  /** Pre-flight rules for ONE call (calling hours, opt-out, cooldown). */
  public static function canCallNow(object $c): bool {
    $now = now('Asia/Dhaka'); $t = $now->format('H:i'); if ($t < $c->call_start_time || $t >= $c->call_end_time) return false;
    if ($c->start_date && $now->toDateString() < substr((string)$c->start_date, 0, 10)) return false; if ($c->end_date && $now->toDateString() > substr((string)$c->end_date, 0, 10)) return false; return true;
  }
  public static function calledToday(int $cid): int { return (int)DB::table('voice_call_attempts')->where('campaign_id', $cid)->where('test', false)->where('started_at', '>=', now('Asia/Dhaka')->startOfDay()->utc())->count(); }

  /** Place the call for one campaign customer and record the result. */
  public static function dial(object $c, object $row, bool $test = false): array {
    $att = $row->call_attempts + 1; if (!$test && DB::table('voice_campaign_customers')->where('id', $row->id)->where('call_status', 'calling')->where('updated_at', '>', now()->subMinutes(3))->exists()) return ['calling', 'duplicate blocked']; DB::table('voice_campaign_customers')->where('id', $row->id)->update(['call_status' => 'calling', 'call_attempts' => $att, 'called_at' => now(), 'updated_at' => now()]);
    $aid = DB::table('voice_call_attempts')->insertGetId(['vc_id' => $row->id, 'campaign_id' => $c->id, 'customer_id' => $row->customer_id, 'phone' => $row->phone, 'attempt' => $att, 'started_at' => now(), 'result' => 'calling', 'test' => $test, 'created_at' => now(), 'updated_at' => now()]);
    [$st, $cause, $uuid] = self::originate($c, $row);
    DB::table('voice_call_attempts')->where('id', $aid)->update(['pbx_call_id' => $uuid ?: null, 'pbx_job_uuid' => self::$lastJob, 'recording_name' => self::recName($row->id ?? 0, $row->phone, $uuid), 'error' => in_array($st, ['failed', 'busy', 'not_answered']) ? mb_substr($cause, 0, 250) : null, 'result' => $st, 'cause' => mb_substr($cause, 0, 60), 'updated_at' => now()]); self::$lastJob = null;
    if ($st === 'answered') { /* the PBX script reports answer / digit / hangup through the webhook; mark answered even if its first event is late */ self::onAnswered($row->id, $aid, $uuid); return [$st, $cause]; }
    $retry = $c->retry_on && $att < (int)$c->retry_limit && in_array($st, ['not_answered', 'busy', 'failed']);
    DB::table('voice_campaign_customers')->where('id', $row->id)->update(['call_status' => $st, 'pbx_call_id' => $uuid ?: null, 'next_attempt_at' => $retry ? now()->addHours(max(1, (int)$c->retry_gap_hours)) : null, 'updated_at' => now()]);
    return [$st, $cause];
  }

  public static function onAnswered(int $vcid, int $aid, string $uuid = ''): void {
    $row = DB::table('voice_campaign_customers')->find($vcid); if (!$row) return; $c = DB::table('voice_campaigns')->find($row->campaign_id); if (!$c) return;
    DB::table('voice_call_attempts')->where('id', $aid)->update(['answered_at' => now(), 'result' => 'answered', 'updated_at' => now()]);
    if ($row->answered_at) return;
    $exp = $c->expiry_date ? \Carbon\Carbon::parse($c->expiry_date, 'Asia/Dhaka')->endOfDay()->utc() : now()->addDays(max(1, (int)$c->validity_days));
    DB::table('voice_campaign_customers')->where('id', $vcid)->update(['call_status' => in_array($row->call_status, ['interested', 'transferred', 'opted_out', 'accepted']) ? $row->call_status : 'answered', 'answered_at' => now(), 'offer_status' => $row->offer_status === 'scheduled' ? 'active' : $row->offer_status, 'offer_expires_at' => $exp, 'next_attempt_at' => null, 'pbx_call_id' => $uuid ?: $row->pbx_call_id, 'updated_at' => now()]);
    $cu = DB::table('customers')->find($row->customer_id);
    if ($c->sms_follow && $cu && ($cu->promo_sms ?? true) && Sms::configured()) { $row2 = DB::table('voice_campaign_customers')->find($vcid); Sms::queue($row->phone, self::render(trim((string)$c->sms_text) !== '' ? $c->sms_text : self::SMS, ['{product}' => $row->product_name, '{pct}' => self::pct((float)$row->discount_value), '{date}' => \Carbon\Carbon::parse($row2->offer_expires_at, 'UTC')->timezone('Asia/Dhaka')->format('d M Y'), '{name}' => (string)$row->name]), 'voice_offer'); }
  }

  /** Called by the scheduler worker: top-up enrolment once a day, then dial what is due. */
  public static function tick(): void {
    if (!self::on() || !(self::set('esl_host') ?: env('PBX_ESL_HOST'))) return;
    $lock = Cache::lock('vc_tick', 90); if (!$lock->get()) return;
    try {
      $t0 = time();
      // calls the PBX never reported on are closed as unanswered
      foreach (DB::table('voice_campaign_customers')->where('call_status', 'calling')->where('updated_at', '<', now()->subMinutes(3))->get() as $r) { $cc = DB::table('voice_campaigns')->find($r->campaign_id); $retry = $cc && $cc->retry_on && $r->call_attempts < (int)$cc->retry_limit;
        DB::table('voice_campaign_customers')->where('id', $r->id)->update(['call_status' => 'not_answered', 'next_attempt_at' => $retry ? now()->addHours(max(1, (int)$cc->retry_gap_hours)) : null, 'updated_at' => now()]); }
      DB::table('voice_campaign_customers')->where('offer_status', 'active')->where('offer_expires_at', '<', now())->update(['offer_status' => 'expired', 'updated_at' => now()]);
      foreach (DB::table('voice_campaigns')->where('status', 'active')->get() as $c) {
        if ($c->end_date && now('Asia/Dhaka')->toDateString() > substr((string)$c->end_date, 0, 10)) { DB::table('voice_campaigns')->where('id', $c->id)->update(['status' => 'completed', 'updated_at' => now()]); continue; }
        if (self::set('enrolled_' . $c->id) !== now('Asia/Dhaka')->toDateString()) self::enroll($c);
        if (!self::canCallNow($c)) continue; $left = (int)$c->daily_limit - self::calledToday($c->id); if ($left <= 0) continue;
        $due = DB::table('voice_campaign_customers')->where('campaign_id', $c->id)->where(fn($q) => $q->where('call_status', 'pending')->orWhere(fn($w) => $w->whereIn('call_status', ['not_answered', 'busy', 'failed'])->whereNotNull('next_attempt_at')->where('next_attempt_at', '<=', now())))->orderBy('id')->limit(min($left, 4))->get();
        foreach ($due as $r) {
          if (time() - $t0 > 50) return;
          $cu = DB::table('customers')->find($r->customer_id); if (!$cu || $cu->blocked || !($cu->promo_call ?? true) || self::optedOut($r->phone)) { DB::table('voice_campaign_customers')->where('id', $r->id)->update(['call_status' => 'opted_out', 'offer_status' => 'void', 'skip_reason' => 'Calls not allowed', 'updated_at' => now()]); continue; }
          $pr = DB::table('products')->find($r->product_id); if (!$pr || !$pr->published || (int)$pr->stock <= 0) { DB::table('voice_campaign_customers')->where('id', $r->id)->update(['call_status' => 'failed', 'offer_status' => 'void', 'skip_reason' => 'Product unavailable', 'next_attempt_at' => null, 'updated_at' => now()]); continue; }
          self::dial($c, $r);
        }
      }
    } finally { $lock->release(); }
  }

  /** Configurable DTMF map: digit => [action, label, enabled]. Digit 9 is always the opt-out. */
  public static function dtmfMap(): array {
    $d = ['1' => ['interested', 'Interested', 1], '2' => ['agent', 'Speak to agent', 1], '9' => ['optout', 'Do Not Call / Opt-Out', 1]];
    $c = json_decode(self::set('dtmf_map'), true); if (is_array($c)) foreach ($c as $k => $v) if (is_array($v) && isset($v[0])) $d[(string)$k] = [(string)$v[0], (string)($v[1] ?? ''), (int)($v[2] ?? 1)];
    $d['9'][0] = 'optout'; $d['9'][2] = 1; return $d;
  }
  public static function optedOut(string $phone): bool { return DB::table('voice_optouts')->where('phone', self::norm($phone))->exists(); }
  public static function optOut(string $phone, ?int $cid, ?string $callId): void {
    $p = self::norm($phone); try { DB::table('voice_optouts')->insert(['customer_id' => $cid, 'phone' => $p, 'opted_out_at' => now(), 'source' => 'voice_offer', 'call_id' => $callId, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    if ($cid) DB::table('customers')->where('id', $cid)->update(['promo_call' => false, 'updated_at' => now()]);
  }
  /** PBX webhook: answered / digit / hangup. The raw payload is always kept. */
  public static function hook(array $d): array {
    unset($d['k']); $uuid = (string)($d['uuid'] ?? $d['call_id'] ?? ''); $a = $uuid !== '' ? DB::table('voice_call_attempts')->where('pbx_call_id', $uuid)->orderByDesc('id')->first() : null;
    if (!$a) { Log::warning('voice hook: unknown call', ['uuid' => $uuid]); return ['ok' => false, 'error' => 'unknown call']; }
    $log = json_decode((string)($a->webhook_payload ?? ''), true) ?: []; $log[] = ['at' => now()->toIso8601String(), 'data' => $d]; $log = array_slice($log, -30);
    DB::table('voice_call_attempts')->where('id', $a->id)->update(['webhook_payload' => json_encode($log, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    $ev = (string)($d['ev'] ?? $d['event'] ?? ''); $row = DB::table('voice_campaign_customers')->find($a->vc_id);
    if (!empty($d['cause']) && !in_array(strtoupper((string)$d['cause']), ['NORMAL_CLEARING'], true)) Log::info('voice hook carrier cause', ['phone' => $a->phone, 'cause' => $d['cause']]);
    if ($ev === 'answered') { if ($row) self::onAnswered($row->id, $a->id, $uuid); else DB::table('voice_call_attempts')->where('id', $a->id)->update(['answered_at' => now(), 'result' => 'answered', 'updated_at' => now()]); }
    elseif ($ev === 'digit') {
      $g = substr((string)($d['digit'] ?? ''), 0, 1); DB::table('voice_call_attempts')->where('id', $a->id)->update(['digit' => $g, 'updated_at' => now()]);
      $m = self::dtmfMap()[$g] ?? null; if (!$m || !$m[2]) return ['ok' => true];
      if ($m[0] === 'optout') { self::optOut($a->phone, $a->customer_id, $uuid); if ($row) DB::table('voice_campaign_customers')->where('id', $row->id)->update(['opted_out' => true, 'call_status' => 'opted_out', 'offer_status' => 'void', 'updated_at' => now()]); return ['ok' => true]; }
      if (!$row) return ['ok' => true];
      if ($m[0] === 'interested') DB::table('voice_campaign_customers')->where('id', $row->id)->update(['interested' => true, 'call_status' => $row->converted ? 'accepted' : 'interested', 'updated_at' => now()]);
      elseif ($m[0] === 'agent') DB::table('voice_campaign_customers')->where('id', $row->id)->update(['transferred' => true, 'call_status' => $row->converted ? 'accepted' : 'transferred', 'updated_at' => now()]);
    } elseif ($ev === 'hangup') {
      $dur = max(0, (int)($d['duration'] ?? 0)); $final = isset($d['billsec']) && (int)$d['billsec'] > 0 ? (int)$d['billsec'] : null; $rec = ($d['recording'] ?? null) ? mb_substr((string)$d['recording'], 0, 250) : ($a->recording_ref ?? null);
      DB::table('voice_call_attempts')->where('id', $a->id)->update(['duration' => $dur, 'approx_duration' => $dur, 'final_duration' => $final ?? $a->final_duration ?? null, 'ended_at' => now(), 'recording_ref' => $rec, 'updated_at' => now()]);
      if ($row) DB::table('voice_campaign_customers')->where('id', $row->id)->update(['duration' => $dur, 'updated_at' => now()]);
    }
    return ['ok' => true];
  }
  /** Connection test from this server: TCP, auth, and a harmless command. */
  public static function pbxTest(): array {
    $h = self::set('esl_host') ?: (string)env('PBX_ESL_HOST', ''); $port = (int)(self::set('esl_port') ?: env('PBX_ESL_PORT', 8021)); $out = ['host' => $h, 'port' => $port];
    $fp = @fsockopen($h ?: 'invalid', $port, $en, $es, 6); $out['tcp'] = (bool)$fp; if (!$fp) { $out['error'] = $es ?: 'timeout'; return $out; } fclose($fp);
    [$ok, $r] = self::esl('status', 15); $out['auth_and_command'] = $ok; $out['response'] = $ok ? mb_substr($r, 0, 200) : $r; return $out;
  }

  // ---------- offer at checkout
  /** Active offers for a phone (also the offer already used by $exOrder, so editing that order keeps its discount). */
  public static function active(string $phone, ?int $ex = null): array {
    $p = self::norm($phone); if (!$p) return [];
    return DB::table('voice_campaign_customers as v')->join('voice_campaigns as c', 'c.id', '=', 'v.campaign_id')->where('v.phone', $p)
      ->where(fn($q) => $q->where(fn($w) => $w->where('v.offer_status', 'active')->where('v.offer_expires_at', '>', now()))->when($ex, fn($w) => $w->orWhere('v.converted_order_id', $ex)))
      ->get(['v.*', 'c.name as cname', 'c.whole_cart', 'c.reusable'])->all();
  }
  /** Best voice-offer discount for this basket (items: product_id, price, qty), or null. */
  public static function best(string $phone, array $items, float $sub, ?int $ex = null): ?array {
    $best = null; foreach (self::active($phone, $ex) as $o) {
      $base = 0.0; if ($o->whole_cart) $base = $sub; else foreach ($items as $it) if ((int)($it['product_id'] ?? 0) === (int)$o->product_id) $base += (float)$it['price'] * (int)$it['qty'];
      $amt = round($base * (float)$o->discount_value / 100, 2); if ($amt > 0 && (!$best || $amt > $best['amount'])) $best = ['amount' => $amt, 'pct' => (float)$o->discount_value, 'vcid' => (int)$o->id, 'product' => $o->product_name, 'campaign' => $o->cname];
    }
    return $best;
  }
  /** Mark the offer used once the order is saved. */
  public static function onOrder(string $phone, int $orderId, array $applied): void {
    $o = DB::table('orders')->find($orderId); if (!$o) return;
    foreach ($applied as $l) if (($l['key'] ?? '') === 'voice' && !empty($l['vcid'])) {
      $v = DB::table('voice_campaign_customers as v')->join('voice_campaigns as c', 'c.id', '=', 'v.campaign_id')->where('v.id', $l['vcid'])->first(['v.id', 'v.used_at', 'c.reusable']); if (!$v) continue;
      DB::table('voice_campaign_customers')->where('id', $v->id)->update(['converted' => true, 'converted_order_id' => $orderId, 'used_at' => now(), 'used_discount' => (float)$l['amount'], 'used_total' => (float)$o->total, 'call_status' => 'accepted', 'offer_status' => $v->reusable ? 'active' : 'used', 'updated_at' => now()]);
    }
  }
  public static function history(string $phone): array {
    return DB::table('voice_campaign_customers as v')->join('voice_campaigns as c', 'c.id', '=', 'v.campaign_id')->where('v.phone', self::norm($phone))->orderByDesc('v.id')->get(['v.*', 'c.name as cname'])->all();
  }
  public static function stats(int $cid): array {
    $b = DB::table('voice_campaign_customers')->where('campaign_id', $cid);
    $s = (clone $b)->selectRaw("count(*) sel, count(*) filter (where call_attempts>0) att, count(*) filter (where answered_at is not null) ans, count(*) filter (where call_status in ('not_answered','busy','failed') and answered_at is null and call_attempts>0) noans, count(*) filter (where interested) intr, count(*) filter (where transferred) tr, count(*) filter (where converted) used, coalesce(sum(used_total) filter (where converted),0) rev, coalesce(sum(used_discount) filter (where converted),0) disc, count(*) filter (where opted_out) oo, count(*) filter (where call_status='pending') pend")->first();
    $a = (array)$s; $a['conv'] = $a['sel'] ? round($a['used'] * 100 / $a['sel'], 1) : 0; return $a;
  }
}
