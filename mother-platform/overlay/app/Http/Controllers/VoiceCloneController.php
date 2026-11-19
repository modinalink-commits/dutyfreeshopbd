<?php
namespace App\Http\Controllers;

use App\Support\Sms;
use App\Support\Voice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Owner 20 Nov 2026: clone the owner's wife's voice (her consent) from the supplied recording for the AI order-confirmation call.
 *  Uses the ElevenLabs key already saved on the server; the key is never shown. Does NOT switch the live call voice by itself. */
class VoiceCloneController extends Controller
{
  private const SRC = 'resources/voice-src/dd_clone.mp3';
  private function key(): ?string { return Sms::secret('vchat_eleven_key') ?: Sms::secret('vc_tts_key') ?: null; }
  private function wav(): string { return storage_path('app/voice/clone_sample.wav'); }

  public function page()
  {
    $vid = Voice::set('clone_voice_id', ''); $has = is_file($this->wav());
    $h = '<!doctype html><meta charset="utf-8"><title>Voice clone</title><body style="font:14px system-ui;max-width:640px;margin:24px auto;padding:0 16px">'
      . '<h3>Voice clone (সম্মতিপ্রাপ্ত কণ্ঠ)</h3><p>ফাইল: ' . (is_file(base_path(self::SRC)) ? 'আছে' : 'নেই') . ' · ক্লোন voice id: ' . ($vid !== '' ? e(substr($vid, 0, 4)) . '…' : 'এখনো নেই') . '</p>'
      . '<button id="c">১) ক্লোন বানান</button> <button id="s">২) নমুনা বানান</button><pre id="o"></pre>'
      . ($has ? '<p>সর্বশেষ নমুনা:</p><audio controls src="/admin/voice/clone/audio?' . filemtime($this->wav()) . '"></audio>' : '')
      . '<script>const t=document.querySelector(\'meta[name=csrf-token]\')?.content||"";async function go(u){o.textContent="...";const r=await fetch(u,{method:"POST",headers:{"X-CSRF-TOKEN":t,"Accept":"application/json"}});o.textContent=await r.text()}c.onclick=()=>go("/admin/voice/clone/make");s.onclick=()=>go("/admin/voice/clone/sample");</script>';
    return response($h);
  }

  public function make(Request $r)
  {
    $k = $this->key(); if (!$k) return response()->json(['ok' => false, 'error' => 'ElevenLabs key not saved'], 422);
    $f = base_path(self::SRC); if (!is_file($f)) return response()->json(['ok' => false, 'error' => 'source recording missing'], 422);
    try {
      $res = Http::timeout(120)->withHeaders(['xi-api-key' => $k])->attach('files', file_get_contents($f), 'dd_clone.mp3')
        ->post('https://api.elevenlabs.io/v1/voices/add', ['name' => 'DutyFreeBD Order Voice (consented clone)', 'remove_background_noise' => 'true', 'description' => 'Consented voice of the owner family member for DutyFreeBD order confirmation calls']);
    } catch (\Throwable $e) { return response()->json(['ok' => false, 'error' => 'ElevenLabs unreachable'], 502); }
    $id = (string)$res->json('voice_id');
    if (!$res->ok() || $id === '') { $d = $res->json('detail'); return response()->json(['ok' => false, 'status' => $res->status(), 'error' => is_array($d) ? ($d['message'] ?? $d['status'] ?? 'rejected') : (string)$d], 422); }
    Voice::put('clone_voice_id', $id);
    return response()->json(['ok' => true, 'voice_id_prefix' => substr($id, 0, 4), 'note' => 'saved; live call voice NOT changed']);
  }

  public function sample(Request $r)
  {
    $k = $this->key(); $vid = Voice::set('clone_voice_id', ''); if (!$k || $vid === '') return response()->json(['ok' => false, 'error' => 'clone first'], 422);
    $text = 'আসসালামু আলাইকুম! আমি ডিউটি ফ্রি বিডি থেকে বলছি। আপনি যে অর্ডারটা করেছেন, সেটা একটু নিশ্চিত করতে ফোন করেছি। আপনার মোট বিল ১২৩৪ টাকা। সব ঠিক থাকলে ১ চাপুন। আর কথা বলতে চাইলে ২ চাপুন। ধন্যবাদ!';
    try {
      $res = Http::timeout(60)->withHeaders(['xi-api-key' => $k, 'Accept' => 'application/octet-stream'])->post('https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($vid) . '?output_format=pcm_8000', ['text' => Voice::bnSpeak($text), 'model_id' => 'eleven_v3', 'voice_settings' => ['stability' => 0.5]]);
    } catch (\Throwable $e) { return response()->json(['ok' => false, 'error' => 'ElevenLabs unreachable'], 502); }
    $pcm = $res->body(); if (!$res->ok() || strlen($pcm) < 1000) return response()->json(['ok' => false, 'status' => $res->status()], 422);
    $n = strlen($pcm); @mkdir(dirname($this->wav()), 0775, true);
    file_put_contents($this->wav(), 'RIFF' . pack('V', 36 + $n) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', $n) . $pcm);
    return response()->json(['ok' => true, 'bytes' => $n, 'seconds' => round($n / 16000, 1)]);
  }

  /** Owner 20 Nov 2026: use the consented clone as the live call voice (previous voice id kept for one-step revert). */
  public function use(Request $r)
  {
    $vid = Voice::set('clone_voice_id', ''); if ($vid === '') return response()->json(['ok' => false, 'error' => 'clone first'], 422);
    $cur = Voice::set('tts_voice', ''); if ($cur !== $vid) Voice::put('prev_tts_voice', $cur);
    Voice::put('tts_voice', $vid); Voice::put('tts_provider', 'elevenlabs');
    return response()->json(['ok' => true, 'live_voice_prefix' => substr($vid, 0, 4), 'prev_prefix' => substr(Voice::set('prev_tts_voice', ''), 0, 4)]);
  }

  public function revert(Request $r)
  {
    $prev = Voice::set('prev_tts_voice', ''); if ($prev === '') return response()->json(['ok' => false, 'error' => 'no previous voice'], 422);
    Voice::put('tts_voice', $prev);
    return response()->json(['ok' => true, 'live_voice_prefix' => substr($prev, 0, 4)]);
  }

  /** Remove the source recording and the sample from the server disk (the clone itself lives in ElevenLabs). */
  public function purge(Request $r)
  {
    $out = [];
    foreach ([base_path(self::SRC), $this->wav()] as $f) { $out[basename($f)] = is_file($f) ? (@unlink($f) ? 'deleted' : 'failed') : 'absent'; }
    return response()->json(['ok' => !in_array('failed', $out, true), 'files' => $out]);
  }

  public function audio() { return is_file($this->wav()) ? response()->file($this->wav(), ['Content-Type' => 'audio/wav', 'Cache-Control' => 'no-store']) : abort(404); }
}
