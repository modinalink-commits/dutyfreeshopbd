<?php
namespace App\Http\Controllers;

use App\Support\Chat;
use App\Support\Sms;
use App\Support\VoiceChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Voice on the storefront chat (public, token-protected) and its admin settings page. */
class VoiceChatController extends Controller
{
  // ---------------- admin ----------------
  public function settings() {
    $s = []; foreach (VoiceChat::DEF as $k => $v) $s[$k] = VoiceChat::set($k);
    return view('voice_chat_settings', ['s' => $s, 'hasKey' => (bool)VoiceChat::key(), 'hasScribe' => (bool)VoiceChat::scribeKey(), 'langs' => VoiceChat::LANGS, 'stats' => VoiceChat::stats(), 'recent' => \Illuminate\Support\Facades\Schema::hasTable('voice_chat_logs') ? DB::table('voice_chat_logs')->orderByDesc('id')->limit(10)->get() : collect()]);
  }
  public function saveSettings(Request $r) {
    $d = $r->validate(['api_key' => 'nullable|string|max:300', 'eleven_key' => 'nullable|string|max:300', 'lang' => 'required|in:' . implode(',', array_keys(VoiceChat::LANGS)), 'speed' => 'required|numeric|min:0.75|max:1.25', 'provider' => 'required|in:google', 'retention' => 'required|integer|min:1|max:90', 'voice' => 'required|string|max:40|regex:/^[A-Za-z0-9\-]+$/']);
    if (!empty($d['api_key'])) Sms::setSecret('vchat_key', trim($d['api_key']));
    if (!empty($d['eleven_key'])) Sms::setSecret('vchat_eleven_key', trim($d['eleven_key']));
    foreach (['enabled', 'mic', 'auto', 'speaker', 'store_audio', 'handoff'] as $k) VoiceChat::put($k, $r->boolean($k) ? '1' : '0');
    foreach (['lang', 'speed', 'provider', 'retention', 'voice'] as $k) VoiceChat::put($k, (string)$d[$k]);
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'voicechat.settings', 'subject' => 'Voice chat settings saved', 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'Voice chat settings saved.');
  }
  /** Admin check: run speech-to-text again on the newest customer voice messages that have no transcript yet. Nothing is sent to any customer; only the transcript under the voice message is filled in. */
  public function retest() {
    if (!VoiceChat::key() && !VoiceChat::scribeKey()) return response()->json(['error' => 'Save a speech-to-text key first.'], 422);
    @set_time_limit(170); $t0 = time(); $ok = 0; $bad = 0; $n = 0; $from = (int)(DB::table('voice_chat_logs')->max('id') ?? 0);
    foreach (DB::table('chat_messages')->where('dir', 'in')->where('attachment_type', 'audio')->whereNotNull('attachment_url')->where(fn($w) => $w->whereNull('transcript')->orWhere('transcript', ''))->orderByDesc('id')->limit(12)->pluck('id') as $mid) {
      if (time() - $t0 > 120) break; $n++;
      \App\Support\Chat::transcribe((int)$mid);
      if (trim((string)DB::table('chat_messages')->where('id', $mid)->value('transcript')) !== '') $ok++; else $bad++;
    }
    $errs = DB::table('voice_chat_logs')->where('id', '>', $from)->where('kind', 'stt')->where('ok', false)->selectRaw('error, count(*) c')->groupBy('error')->orderByDesc('c')->limit(6)->pluck('c', 'error')->all();
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'voicechat.retest', 'subject' => "Voice re-test: $ok understood, $bad not", 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return response()->json(['tried' => $n, 'ok' => $ok, 'failed' => $bad, 'errors' => $errs]);
  }
  /** Admin test: speaks a short Bangla line with the saved key and returns the audio. */
  public function test() {
    if (!VoiceChat::key()) return response()->json(['error' => 'Save the Google API key first.'], 422);
    [$f, $e] = VoiceChat::speak('আসসালামু আলাইকুম। এটি DutyFreeBD ভয়েস চ্যাটের একটি পরীক্ষা।', null, null);
    if (!$f) return response()->json(['error' => 'Text-to-Speech failed. Check that the Cloud Text-to-Speech API is enabled and the key allows it.'], 422);
    return response()->file($f, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'no-store']);
  }

  // ---------------- public ----------------
  /** Same-origin only: these routes carry no cookie, so the Origin/Referer check plus the unguessable thread token is the CSRF defence. */
  private function sameOrigin(Request $r): void {
    $o = $r->headers->get('Origin') ?: $r->headers->get('Referer'); $h = $o ? parse_url($o, PHP_URL_HOST) : null;
    abort_unless($h && strcasecmp($h, $r->getHost()) === 0, 403);
  }
  private function thread(string $tok): object { $t = DB::table('chat_threads')->where(['channel' => 'web', 'external_id' => $tok])->first(); abort_unless($t, 404); return $t; }
  private function fail(string $msg, int $code = 422) { return response()->json(['ok' => false, 'error' => $msg], $code); }

  public function stt(Request $r) {
    $this->sameOrigin($r);
    if (VoiceChat::set('mic') !== '1') return $this->fail('ভয়েস সেবা এখন চালু নেই। অনুগ্রহ করে লিখে জানান।', 404);
    $tok = (string)$r->input('token'); if (!preg_match('/^[a-f0-9]{32}$/', $tok)) return $this->fail('Bad request', 400);
    $t = $this->thread($tok);
    $f = $r->file('audio'); if (!$f || !$f->isValid()) return $this->fail('অডিও পাওয়া যায়নি। আবার চেষ্টা করুন।');
    if ($f->getSize() > VoiceChat::MAX_BYTES || $f->getSize() < 800) return $this->fail('রেকর্ডিং খুব বড় বা খুব ছোট। ৪০ সেকেন্ডের মধ্যে বলুন।');
    $dur = (float)$r->input('duration', 0); if ($dur > VoiceChat::MAX_SECONDS + 3) return $this->fail('রেকর্ডিং ৪০ সেকেন্ডের বেশি হতে পারবে না।');
    $bytes = (string)file_get_contents($f->getRealPath());
    $fmt = VoiceChat::sniff($bytes); if (!$fmt) return $this->fail('অডিও ফাইলটি গ্রহণযোগ্য নয়। আবার চেষ্টা করুন।');
    // per-conversation cap: keeps one visitor from filling the disk or running up the speech bill
    $k = 'vchat.t.' . $t->id . '.' . now()->format('YmdH'); Cache::add($k, 0, 4000); if (Cache::increment($k) > 40) return $this->fail('অনেক ভয়েস মেসেজ হয়ে গেছে। কিছুক্ষণ পর আবার চেষ্টা করুন, অথবা লিখে জানান।', 429);
    $dk = 'vchat.n.' . now()->format('Ymd'); Cache::add($dk, 0, 90000); if (Cache::increment($dk) > 1500) return $this->fail('আজকের ভয়েস সীমা শেষ। অনুগ্রহ করে লিখে জানান।', 429);

    // The recording is always kept so the support team can listen to it in the chat; the written transcript is an optional extra.
    $dir = public_path('uploads/chat'); if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'v-' . date('ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $fmt; @file_put_contents($dir . '/' . $name, $bytes);
    $url = url('/uploads/chat/' . $name);
    $text = '';
    if (VoiceChat::ready()) { [$tx, $err, $dl] = VoiceChat::transcribe($bytes, (int)$t->id); if ($tx !== null) $text = mb_substr(trim(strip_tags($tx)), 0, 1000); }   // untrusted, exactly like typed text
    $th = Chat::ingest('web', $tok, null, $text, $url, null, ['att_type' => 'audio']);
    $m = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->orderByDesc('id')->first(['id', 'created_at']);
    if ($m) DB::table('chat_messages')->where('id', $m->id)->update(['input_type' => 'voice', 'transcript' => $text !== '' ? $text : null, 'detected_language' => $dl ?? null, 'metadata' => json_encode(['duration' => round($dur, 1), 'bytes' => strlen($bytes)])]);
    if ($th && $text !== '' && !$th->ai_paused) { $tid = (int)$th->id; app()->terminating(fn() => Chat::aiReply($tid)); }
    if (random_int(1, 40) === 1) VoiceChat::prune();
    return response()->json(['ok' => true, 'id' => $m->id ?? null, 'text' => $text, 'at' => $m ? \Carbon\Carbon::parse($m->created_at)->toIso8601String() : null]);
  }

  /** Audio of one assistant/staff message of the caller's own conversation. POST so the token never appears in a URL. */
  public function tts(Request $r) {
    $this->sameOrigin($r);
    if (!VoiceChat::ready() || VoiceChat::set('speaker') !== '1') return $this->fail('off', 404);
    $tok = (string)$r->input('token'); if (!preg_match('/^[a-f0-9]{32}$/', $tok)) return $this->fail('Bad request', 400);
    $t = $this->thread($tok);
    $m = DB::table('chat_messages')->where('id', (int)$r->input('id'))->where('thread_id', $t->id)->where('dir', 'out')->first(['id', 'body']);
    if (!$m || trim((string)$m->body) === '') return $this->fail('not found', 404);
    $k = 'vchat.s.' . $t->id . '.' . now()->format('YmdH'); Cache::add($k, 0, 4000); if (Cache::increment($k) > 60) return $this->fail('limit', 429);
    [$f, $e] = VoiceChat::speak((string)$m->body, (int)$t->id, (int)$m->id);
    if (!$f) return $this->fail($e ?: 'fail', 502);
    return response()->file($f, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=3600']);
  }
}
