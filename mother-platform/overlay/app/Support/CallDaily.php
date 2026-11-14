<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Daily Call Report for the owner (14 Nov 2026): what the call team did today and what is left for tomorrow.
 *  Sent on WhatsApp after duty ends, with a private link to the full report that stops working after four days. Every number comes from call_logs, orders, chats and call reviews. */
class CallDaily {
  const API = 'https://graph.facebook.com/v21.0';
  const BODY = "আসসালামু আলাইকুম {{1}},\nআজকের কল রিপোর্ট ({{2}})।\n\nমোট কল: {{3}}\nকলে অর্ডার কনফার্ম: {{4}}\nমিসড কল, ফেরত দেওয়া হয়নি: {{5}}\nকল না পাওয়া নতুন অর্ডার: {{6}}\n\nস্টাফ অনুযায়ী বিস্তারিত নিচের লিংকে দেখুন।";

  public static function set(string $k, string $d = ''): string { return Procure::set('call_daily_' . $k, $d); }
  public static function tpl(): string { return self::set('tpl', 'daily_call_report'); }
  public static function on(): bool { return self::set('enabled', '1') === '1'; }
  public static function sendAt(): string { $t = trim(self::set('send_at', '21:05')); return preg_match('/^\d{1,2}:\d{2}$/', $t) ? $t : '21:05'; }
  public static function recipients(): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', self::set('to', "Faruk|01884808080")) as $l) {
      [$n, $p] = array_pad(array_map('trim', explode('|', $l, 2)), 2, ''); $wa = WhatsApp::norm($p);
      if (preg_match('/^8801[3-9]\d{8}$/', $wa)) $out[] = [$n !== '' ? $n : 'Team', $wa];
    }
    return $out;
  }

  public static function data(): array {
    $at = now('Asia/Dhaka'); $day = $at->copy()->startOfDay()->utc();
    $tot = DB::table('call_logs')->where('started_at', '>=', $day)->selectRaw("count(*) n, count(*) filter (where status='answered') ans, count(*) filter (where outcome='confirmed') conf, count(*) filter (where outcome='cancelled') canc, coalesce(sum(duration) filter (where status='answered'),0) talk")->first();
    $idle = []; try { $idle = DB::table('staff_idle_notices')->where('created_at', '>=', $day)->groupBy('user_id')->selectRaw('user_id, count(*) n, coalesce(sum(idle_minutes),0) m')->get()->keyBy('user_id')->all(); } catch (\Throwable $e) {}
    $staff = DB::table('call_logs')->where('started_at', '>=', $day)->whereNotNull('user_id')->groupBy('user_id')->selectRaw("user_id, max(user_name) name, count(*) n, count(*) filter (where status='answered') ans, count(*) filter (where outcome='confirmed') conf, coalesce(sum(duration) filter (where status='answered'),0) talk")->orderByDesc('n')->get()
      ->map(fn($s) => ['name' => (string)$s->name, 'calls' => (int)$s->n, 'answered' => (int)$s->ans, 'confirmed' => (int)$s->conf, 'talk' => (int)$s->talk, 'idle_n' => (int)($idle[$s->user_id]->n ?? 0), 'idle_m' => (int)($idle[$s->user_id]->m ?? 0)])->all();
    $up = []; try { $up = json_decode(app(\App\Http\Controllers\CallCenterController::class)->upNext()->getContent(), true)['items'] ?? []; } catch (\Throwable $e) {}
    $cnt = ['new' => 0, 'callback' => 0, 'noanswer' => 0, 'missed' => 0]; foreach ($up as $i) $cnt[$i['type']] = ($cnt[$i['type']] ?? 0) + 1;
    $chats = 0; try { $chats = (int)Chat::humanScope(DB::table('chat_threads'))->count(); } catch (\Throwable $e) {}
    $rev = ['n' => 0, 'avg' => null]; try { $rev = ['n' => (int)DB::table('call_reviews')->where('created_at', '>=', $day)->count(), 'avg' => DB::table('call_reviews')->where('created_at', '>=', $day)->avg('score')]; } catch (\Throwable $e) {}
    return ['date' => $at->format('d M Y'), 'made' => $at->format('d M Y, h:i A'), 'calls' => (int)($tot->n ?? 0), 'answered' => (int)($tot->ans ?? 0), 'confirmed' => (int)($tot->conf ?? 0), 'cancelled' => (int)($tot->canc ?? 0), 'talk' => (int)($tot->talk ?? 0),
      'staff' => $staff, 'left' => ['new' => $cnt['new'], 'callbacks' => $cnt['callback'] + $cnt['noanswer'], 'missed' => $cnt['missed'], 'chats' => $chats], 'review' => ['n' => $rev['n'], 'avg' => $rev['avg'] !== null ? round((float)$rev['avg'], 1) : null]];
  }

  private static function dir(): string { $d = storage_path('app/call-reports'); if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
  public static function make(): array {
    $d = self::data(); $tok = bin2hex(random_bytes(20)); file_put_contents(self::dir() . '/' . $tok . '.json', json_encode($d, JSON_UNESCAPED_UNICODE));
    foreach (glob(self::dir() . '/*.json') ?: [] as $f) if (filemtime($f) < time() - 4 * 86400) @unlink($f);
    return [$tok, $d];
  }
  public static function read(string $tok): ?array { if (!preg_match('/^[0-9a-f]{40}$/', $tok)) return null; $f = self::dir() . '/' . $tok . '.json'; if (!is_file($f) || filemtime($f) < time() - 4 * 86400) return null; $j = json_decode((string)file_get_contents($f), true); return is_array($j) ? $j : null; }

  public static function call(string $to, string $name, array $d, string $tok): array {
    $t = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id'); if (!$t || !$pid) return [false, 'WhatsApp is not connected.'];
    try {
      $r = Http::timeout(20)->withToken($t)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => ['name' => self::tpl(), 'language' => ['code' => 'bn'], 'components' => [
        ['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string)$v], [$name, $d['date'], $d['calls'], $d['confirmed'], $d['left']['missed'], $d['left']['new']])],
        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $tok]]]]]]);
      if ($r->successful()) return [true, (string)$r->json('messages.0.id')];
      return [false, mb_substr('(' . (int)$r->json('error.code') . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 250)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . mb_substr($e->getMessage(), 0, 120)]; }
  }
  public static function send(?array $only = null): array {
    [$tok, $d] = self::make(); $res = [];
    foreach ($only ?? self::recipients() as [$name, $wa]) { [$ok, $m] = self::call($wa, $name, $d, $tok); $res[] = $name . ' …' . substr($wa, -4) . ': ' . ($ok ? 'sent' : 'failed · ' . $m); }
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? 'System', 'action' => 'call.daily_report', 'subject' => 'Daily call report: ' . $d['calls'] . ' calls, ' . $d['confirmed'] . ' confirmed · ' . implode('; ', $res), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return $res;
  }
  public static function tick(): void {
    try { // recordings older than the keep period are removed once a day so the disk does not fill up
      if (Cache::add('cc_rec_clean_'.date('Ymd'), 1, 90000)) { $keep = max(14, (int)Procure::set('cc_rec_keep_days', '90')); $n = 0; foreach (glob(storage_path('app/call-recordings').'/*.{webm,ogg}', GLOB_BRACE) ?: [] as $f) if (filemtime($f) < time() - $keep * 86400) { $id = (int)basename($f); @unlink($f); $n++; DB::table('call_logs')->where('id', $id)->update(['recording_url'=>null, 'recording_status'=>'none']); } }
    } catch (\Throwable $e) {}
    try {
      if (!self::on() || self::set('tpl_status', 'NOT SUBMITTED') !== 'APPROVED') { if (self::set('tpl_status', '') === 'PENDING' && Cache::add('cd_sync', 1, 600)) self::syncTemplate(); return; }
      $now = now('Asia/Dhaka'); $day = $now->format('Ymd'); [$h, $m] = array_map('intval', explode(':', self::sendAt()));
      if ($now->hour * 60 + $now->minute < $h * 60 + $m || self::set('sent_day', '') === $day) return;
      if (!Cache::add('cd_send_' . $day, 1, 3600)) return;
      Procure::put('call_daily_sent_day', $day); self::send();
    } catch (\Throwable $e) { report($e); }
  }
  public static function submitTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl(), 'language' => 'bn', 'category' => 'UTILITY', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['ফারুক', '14 Nov 2026', '146', '38', '3', '7']]]],
        ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'রিপোর্ট দেখুন', 'url' => url('/cr') . '/{{1}}', 'example' => [url('/cr') . '/' . str_repeat('a1', 20)]]]]]]);
      if ($r->successful()) { Procure::put('call_daily_tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl(), 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::tpl());
      $s = $row ? strtoupper((string)$row['status']) : 'NOT_FOUND'; Procure::put('call_daily_tpl_status', $s);
      return [true, $s . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
