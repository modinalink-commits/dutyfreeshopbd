<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Customer voice chat on the SAME Commerce Agent: speech -> text (Google Speech-to-Text) -> existing agent -> text -> speech (Google Text-to-Speech).
 * Everything here is best-effort: any failure returns an error string and the text chat keeps working.
 * Transcripts are untrusted customer text, exactly like typed text; this class never decides anything about orders, payments or prices.
 */
class VoiceChat
{
  const MAX_BYTES = 1500000;   // ~1.5 MB of opus = well over a minute
  const MAX_SECONDS = 45;
  const MAX_TTS_CHARS = 700;
  const MIMES = ['audio/webm', 'audio/ogg'];
  const LANGS = ['bn-BD' => 'বাংলা (বাংলাদেশ)', 'bn-IN' => 'বাংলা (ভারত)', 'en-US' => 'English'];
  const DEF = ['enabled' => '0', 'mic' => '1', 'auto' => '1', 'speaker' => '1', 'lang' => 'bn-BD', 'speed' => '1.0', 'provider' => 'google', 'store_audio' => '0', 'retention' => '7', 'handoff' => '1', 'voice' => 'bn-IN-Wavenet-A'];

  public static function set(string $k): string { $v = DB::table('settings')->where('key', 'vchat_' . $k)->value('value'); return $v === null ? (self::DEF[$k] ?? '') : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'vchat_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); Cache::forget('vchat.cfg'); }
  public static function key(): ?string { return Sms::secret('vchat_key') ?: null; }
  public static function ready(): bool { return self::set('enabled') === '1' && self::key() !== null && Schema::hasTable('voice_chat_logs'); }

  /** What the storefront widget may show (cached 60 s). No secrets. */
  public static function widgetCfg(): array {
    return Cache::remember('vchat.cfg', 60, function () {
      $on = self::ready();
      return ['on' => $on, 'rec' => self::set('mic') === '1', 'mic' => $on && self::set('mic') === '1', 'auto' => $on && self::set('auto') === '1', 'speaker' => $on && self::set('speaker') === '1', 'handoff' => self::set('handoff') === '1', 'max' => self::MAX_SECONDS];
    });
  }

  private static function log(?int $tid, ?int $mid, string $kind, bool $ok, string $err = '', int $ms = 0, int $bytes = 0, int $chars = 0, bool $cached = false): void {
    try { DB::table('voice_chat_logs')->insert(['thread_id' => $tid, 'message_id' => $mid, 'kind' => $kind, 'provider' => self::set('provider'), 'ok' => $ok, 'error' => mb_substr($err, 0, 200), 'ms' => $ms, 'bytes' => $bytes, 'chars' => $chars, 'cached' => $cached, 'created_at' => now()]); } catch (\Throwable $e) {}
  }

  /** Real audio check on the first bytes: the browser-sent MIME is not trusted. */
  public static function sniff(string $b): ?string {
    if (substr($b, 0, 4) === "\x1A\x45\xDF\xA3") return 'webm';
    if (substr($b, 0, 4) === 'OggS') return 'ogg';
    return null;
  }

  /** Speech to text. @return array{0:?string,1:string,2:?string} [transcript, error (Bengali, customer-safe), detected language] */
  public static int $lastStatus = 0;
  public static function transcribe(string $bytes, ?int $tid, ?int $rate = null): array {
    $key = self::key(); $t0 = microtime(true); self::$lastStatus = 0;
    if (!$key) return [null, 'ভয়েস সেবা এখন বন্ধ আছে। অনুগ্রহ করে লিখে জানান।', null];
    $fmt = self::sniff($bytes); if (!$fmt) return [null, 'অডিও ফাইলটি গ্রহণযোগ্য নয়। আবার চেষ্টা করুন।', null];
    $lang = array_key_exists(self::set('lang'), self::LANGS) ? self::set('lang') : 'bn-BD';
    $cfg = ['encoding' => $fmt === 'webm' ? 'WEBM_OPUS' : 'OGG_OPUS', 'languageCode' => $lang, 'enableAutomaticPunctuation' => true, 'maxAlternatives' => 1];
    if ($lang !== 'en-US') $cfg['alternativeLanguageCodes'] = ['en-US'];
    try {
      $r = Http::timeout(25)->post('https://speech.googleapis.com/v1/speech:recognize?key=' . urlencode($key), ['config' => $cfg, 'audio' => ['content' => base64_encode($bytes)]]);
      $ms = (int)((microtime(true) - $t0) * 1000);
      self::$lastStatus = $r->status();
      if (!$r->ok()) { self::log($tid, null, 'stt', false, 'HTTP ' . $r->status() . ' ' . ($r->json('error.status') ?? ''), $ms, strlen($bytes)); return [null, 'আপনার কথা বুঝতে পারিনি। অনুগ্রহ করে লিখে জানান।', null]; }
      $res = $r->json('results.0.alternatives.0') ?? []; $text = trim((string)($res['transcript'] ?? '')); $dl = $r->json('results.0.languageCode');
      if ($text === '' && !empty($cfg['alternativeLanguageCodes'])) { // retry once with the main language only
        unset($cfg['alternativeLanguageCodes']);
        $r2 = Http::timeout(25)->post('https://speech.googleapis.com/v1/speech:recognize?key=' . urlencode($key), ['config' => $cfg, 'audio' => ['content' => base64_encode($bytes)]]);
        if ($r2->ok()) { $text = trim((string)$r2->json('results.0.alternatives.0.transcript')); $dl = $r2->json('results.0.languageCode'); }
      }
      if ($text === '') { self::log($tid, null, 'stt', false, 'empty transcript', $ms, strlen($bytes)); return [null, 'আপনার কথা শোনা যায়নি। আবার বলুন, অথবা লিখে জানান।', null]; }
      self::log($tid, null, 'stt', true, '', $ms, strlen($bytes), mb_strlen($text));
      return [$text, '', $dl ? (string)$dl : $lang];
    } catch (\Throwable $e) { self::log($tid, null, 'stt', false, 'unreachable', (int)((microtime(true) - $t0) * 1000), strlen($bytes)); return [null, 'এই মুহূর্তে ভয়েস কাজ করছে না। অনুগ্রহ করে লিখে জানান।', null]; }
  }

  /** Message text -> what is worth speaking: no links, markup, emoji or ids that sound bad aloud. */
  public static function spoken(string $t): string {
    $t = preg_replace('~https?://\S+~u', '', $t);
    $t = preg_replace_callback('/৳\s*([0-9০-৯][0-9০-৯,\.]*)/u', fn($m) => str_replace(',', '', rtrim($m[1], '.')) . ' টাকা', $t);
    $t = preg_replace('/[\*\_`#>\[\]\|]+/u', ' ', $t);
    $t = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE0F}]/u', '', $t);
    $t = preg_replace('/\s*\n+\s*/u', '। ', $t);
    $t = trim(preg_replace('/\s+/u', ' ', $t));
    if (mb_strlen($t) > self::MAX_TTS_CHARS) { $t = mb_substr($t, 0, self::MAX_TTS_CHARS); $p = max((int)mb_strrpos($t, '।'), (int)mb_strrpos($t, '.')); if ($p > 200) $t = mb_substr($t, 0, $p + 1); }
    return $t;
  }

  /** Text to speech, cached by (voice, speed, text). @return array{0:?string,1:string} [file path, error] */
  public static function speak(string $text, ?int $tid, ?int $mid): array {
    $key = self::key(); $t0 = microtime(true); $text = self::spoken($text);
    if (!$key) return [null, 'off']; if ($text === '') return [null, 'empty'];
    $speed = max(0.75, min(1.25, (float)self::set('speed') ?: 1.0)); $voice = self::set('voice') ?: self::DEF['voice'];
    $dir = storage_path('app/voicechat/tts'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $f = $dir . '/' . hash('sha256', $voice . '|' . $speed . '|' . $text) . '.mp3';
    if (is_file($f) && filesize($f) > 500) { self::log($tid, $mid, 'tts', true, '', 0, filesize($f), mb_strlen($text), true); return [$f, '']; }
    try {
      $lc = substr($voice, 0, 5) ?: 'bn-IN';
      $r = Http::timeout(25)->post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode($key), ['input' => ['text' => $text], 'voice' => ['languageCode' => $lc, 'name' => $voice], 'audioConfig' => ['audioEncoding' => 'MP3', 'speakingRate' => $speed]]);
      $ms = (int)((microtime(true) - $t0) * 1000); $b = base64_decode((string)$r->json('audioContent'));
      if (!$r->ok() || strlen($b) < 500) { self::log($tid, $mid, 'tts', false, 'HTTP ' . $r->status() . ' ' . ($r->json('error.status') ?? ''), $ms, 0, mb_strlen($text)); return [null, 'fail']; }
      file_put_contents($f, $b); self::log($tid, $mid, 'tts', true, '', $ms, strlen($b), mb_strlen($text));
      return [$f, ''];
    } catch (\Throwable $e) { self::log($tid, $mid, 'tts', false, 'unreachable', (int)((microtime(true) - $t0) * 1000), 0, mb_strlen($text)); return [null, 'fail']; }
  }

  /** Removes stored customer recordings and cached speech older than the retention window. Cheap; called now and then. */
  public static function prune(): void {
    $days = max(1, (int)self::set('retention')); $cut = time() - $days * 86400;
    foreach (['in' => $cut, 'tts' => time() - 30 * 86400] as $d => $c) {
      foreach (glob(storage_path('app/voicechat/' . $d . '/*')) ?: [] as $f) if (is_file($f) && filemtime($f) < $c) @unlink($f);
    }
    try { DB::table('chat_messages')->where('input_type', 'voice')->whereNotNull('audio_path')->where('created_at', '<', now()->subDays($days))->update(['audio_path' => null]); } catch (\Throwable $e) {}
  }

  public static function stats(): array {
    if (!Schema::hasTable('voice_chat_logs')) return ['stt' => 0, 'stt_fail' => 0, 'tts' => 0, 'tts_fail' => 0, 'cached' => 0, 'chars' => 0];
    $s = now()->subDays(7);
    $q = fn($k, $ok = null) => DB::table('voice_chat_logs')->where('kind', $k)->where('created_at', '>', $s)->when($ok !== null, fn($x) => $x->where('ok', $ok))->count();
    return ['stt' => $q('stt'), 'stt_fail' => $q('stt', false), 'tts' => $q('tts'), 'tts_fail' => $q('tts', false), 'cached' => DB::table('voice_chat_logs')->where('kind', 'tts')->where('cached', true)->where('created_at', '>', $s)->count(), 'chars' => (int)DB::table('voice_chat_logs')->where('kind', 'tts')->where('cached', false)->where('created_at', '>', $s)->sum('chars')];
  }
}
