<?php
namespace App\Http\Controllers;

use App\Support\Deployer;
use App\Support\GoogleSeo;
use App\Support\LiveBoard;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Live Dashboard: a read-only wall screen of everything running in the background. Settings-level permission (ModulePermission). */
class LiveBoardController extends Controller
{
  public function index() { return view('live_board'); }
  public function status() {
    try { return response()->json(LiveBoard::data())->header('Cache-Control', 'no-store'); }
    catch (\Throwable $e) { report($e); return response()->json(['error' => 'Live data could not be read'], 500); }
  }

  public function pulse() {
    try { return response()->json(LiveBoard::pulse())->header('Cache-Control', 'no-store'); }
    catch (\Throwable $e) { return response()->json(['error' => 'pulse'], 500); }
  }

  /**
   * Spoken announcement for the wall screen (owner, 23 Nov 2026): the SAME ElevenLabs voice as the AI calls (Voice settings), numbers spelled in Bangla words.
   * Each sentence is generated once and kept on disk; new text is limited per day (setting lb_voice_cap, default 10000 characters) so the board can never drain the voice credit.
   */
  public function say(Request $r) {
    $t = trim(preg_replace('/\s+/u', ' ', (string)$r->query('t', ''))); if ($t === '' || mb_strlen($t) > 220) return response()->json(['error' => 'text'], 422);
    // 25 Nov 2026 (owner): the board's voice is Google Bangla text-to-speech (free monthly allowance, no ElevenLabs credits). Each sentence is made once, then replayed from disk.
    $keys = array_values(array_unique(array_filter([\App\Support\Sms::secret('vchat_key'), \App\Support\Sms::secret('vc_tts_key')]))); if (!$keys) return response()->json(['error' => 'গুগল কণ্ঠের চাবি (Google API key) বসানো নেই'], 409);
    $dir = storage_path('app/voice/lb'); if (!is_dir($dir)) @mkdir($dir, 0775, true); $f = $dir . '/' . md5('google|bn-IN-Wavenet-A|' . $t) . '.mp3';
    if (!is_file($f) || filesize($f) < 500) {
      $dk = 'lb.say.' . now('Asia/Dhaka')->format('Ymd'); $cap = (int)(DB::table('settings')->where('key', 'lb_voice_cap')->value('value') ?: 10000);
      if ((int)Cache::get($dk, 0) + mb_strlen($t) > $cap) return response()->json(['error' => 'আজকের ভয়েস সীমা শেষ'], 429);
      $ok = false; $st = 0;
      foreach ($keys as $key) {
        try { $res = \Illuminate\Support\Facades\Http::timeout(30)->post('https://texttospeech.googleapis.com/v1/text:synthesize?key=' . urlencode($key), ['input' => ['text' => \App\Support\Voice::bnSpeak($t)], 'voice' => ['languageCode' => 'bn-IN', 'name' => 'bn-IN-Wavenet-A'], 'audioConfig' => ['audioEncoding' => 'MP3']]); }
        catch (\Throwable $e) { continue; }
        $st = $res->status(); $b = base64_decode((string)$res->json('audioContent'));
        if ($res->ok() && strlen($b) >= 500) { file_put_contents($f, $b); Cache::put($dk, (int)Cache::get($dk, 0) + mb_strlen($t), 90000); $ok = true; break; }
      }
      if (!$ok) return response()->json(['error' => 'গুগল কণ্ঠ তৈরি হয়নি' . ($st ? ' (' . $st . ')' : '')], 502);
    }
    return response()->file($f, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=86400']);
  }

  /** Google Search Console connection: the owner pastes the service-account key here once. The key is stored encrypted and never shown again. */
  public function google() { return view('live_board_google', ['email' => GoogleSeo::email(), 'property' => GoogleSeo::property(), 'g' => GoogleSeo::key() ? GoogleSeo::data() : null]); }
  public function googleSave(Request $r) {
    $d = $r->validate(['property' => 'nullable|string|max:120', 'key' => 'nullable|string|max:8000', 'act' => 'nullable|in:save,test,remove']);
    $act = $d['act'] ?? 'save';
    if ($act === 'remove') { Sms::setSecret(GoogleSeo::SECRET, null); Cache::forget('gseo.data'); Deployer::audit('Google SEO key removed', 'Search Console connection', $r->user()->name ?? null); return back()->with('ok', 'Google key removed.'); }
    if (!empty($d['property'])) DB::table('settings')->updateOrInsert(['key' => 'gsc_property'], ['value' => trim($d['property']), 'updated_at' => now(), 'created_at' => now()]);
    if ($act === 'save' && trim((string)($d['key'] ?? '')) !== '') {
      $j = json_decode(trim($d['key']), true);
      if (!is_array($j) || empty($j['client_email']) || empty($j['private_key']) || ($j['type'] ?? '') !== 'service_account') return back()->with('err', 'This is not a Google service-account key file. Paste the whole JSON file (it starts with { and contains "type": "service_account").');
      Sms::setSecret(GoogleSeo::SECRET, json_encode(['client_email' => $j['client_email'], 'private_key' => $j['private_key']]));
      Deployer::audit('Google SEO key saved', 'Service account ' . $j['client_email'], $r->user()->name ?? null);
    }
    Cache::forget('gseo.data'); Cache::forget('lb.data'); $g = GoogleSeo::key() ? GoogleSeo::data(true) : ['state' => 'none', 'msg' => 'No key saved yet'];
    return back()->with($g['state'] === 'ok' ? 'ok' : 'err', $g['state'] === 'ok' ? 'Connected. Last 7 days: ' . $g['clicks'] . ' clicks, ' . $g['impressions'] . ' impressions.' : $g['msg']);
  }
}
