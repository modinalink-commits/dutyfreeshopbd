<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp calls inside the admin (owner 16 Nov 2026). Meta's Cloud API Calling: the customer taps the call button in WhatsApp, Meta sends a "calls" webhook with an
 * SDP offer, a staff browser answers over WebRTC and we return its SDP answer (pre_accept + accept). Only API-only numbers can do this - a number that also runs in the
 * WhatsApp Business app (coexistence) is refused by Meta. Nothing here touches the PBX call centre or the message inbox.
 */
class WaCalls {
  const API = 'https://graph.facebook.com/v23.0';
  const RING_SECONDS = 60; // Meta drops an unanswered call after about 30-60 seconds
  public static function ready(): bool { try { return Schema::hasTable('wa_calls'); } catch (\Throwable $e) { return false; } }

  /** Every connected WhatsApp number: [phone_id, display, name, token]. */
  public static function numbers(): array {
    $out = [];
    try {
      $pid = (string)Chat::sec('chat_wa_phone_id'); $tok = (string)Chat::sec('chat_wa_token');
      if ($pid !== '' && $tok !== '') $out[$pid] = ['phone_id' => $pid, 'display' => '', 'name' => 'Main number', 'token' => $tok];
      foreach (DB::table('chat_wa_numbers')->orderBy('id')->get() as $n) { try { $out[(string)$n->phone_id] = ['phone_id' => (string)$n->phone_id, 'display' => (string)$n->display_phone, 'name' => (string)$n->verified_name, 'token' => Crypt::decryptString($n->token)]; } catch (\Throwable $e) {} }
    } catch (\Throwable $e) {}
    return array_values($out);
  }
  private static function tok(string $phoneId): ?string { foreach (self::numbers() as $n) if ($n['phone_id'] === $phoneId) return $n['token']; return null; }
  private static function err($r): string { return mb_substr((string)($r->json('error.error_user_msg') ?? $r->json('error.message') ?? ('HTTP ' . $r->status())), 0, 260); }

  /** What Meta says about calling on this number: ['ok'=>bool, 'status'=>ENABLED|DISABLED|?, 'icon'=>..., 'display'=>..., 'error'=>...]. */
  public static function settings(string $phoneId): array {
    $tok = self::tok($phoneId); if (!$tok) return ['ok' => false, 'error' => 'no token'];
    try {
      $r = Http::timeout(15)->withToken($tok)->get(self::API . '/' . $phoneId . '/settings');
      $d = Http::timeout(15)->withToken($tok)->get(self::API . '/' . $phoneId, ['fields' => 'display_phone_number,verified_name']);
      $base = ['display' => (string)$d->json('display_phone_number'), 'name' => (string)$d->json('verified_name')];
      if (!$r->ok()) return $base + ['ok' => false, 'error' => self::err($r)];
      return $base + ['ok' => true, 'status' => (string)($r->json('calling.status') ?? 'DISABLED'), 'icon' => (string)($r->json('calling.call_icon_visibility') ?? ''), 'sip' => (string)($r->json('calling.sip.status') ?? '')];
    } catch (\Throwable $e) { return ['ok' => false, 'error' => 'request failed']; }
  }
  /** Turn calling on / off for a number (call button visible to customers). Returns [ok, message]. */
  public static function setCalling(string $phoneId, bool $on): array {
    $tok = self::tok($phoneId); if (!$tok) return [false, 'no token'];
    try {
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $phoneId . '/settings', ['calling' => $on ? ['status' => 'ENABLED', 'call_icon_visibility' => 'DEFAULT', 'callback_permission_status' => 'DISABLED'] : ['status' => 'DISABLED']]);
      return $r->ok() ? [true, $on ? 'enabled' : 'disabled'] : [false, self::err($r)];
    } catch (\Throwable $e) { return [false, 'request failed']; }
  }

  /** Webhook value of a "calls" change. */
  public static function hook(array $v): void {
    try {
      if (!self::ready()) return;
      $pid = (string)($v['metadata']['phone_number_id'] ?? ''); $names = [];
      foreach ($v['contacts'] ?? [] as $ct) $names[(string)($ct['wa_id'] ?? '')] = (string)($ct['profile']['name'] ?? '');
      foreach ($v['calls'] ?? [] as $c) {
        $cid = (string)($c['id'] ?? ''); if ($cid === '') continue; $ev = (string)($c['event'] ?? '');
        $row = DB::table('wa_calls')->where('call_id', $cid)->first();
        if ($ev === 'connect') {
          if ($row) continue;
          $from = preg_replace('/\D/', '', (string)($c['from'] ?? ''));
          DB::table('wa_calls')->insert(['call_id' => $cid, 'phone_id' => $pid, 'from_wa' => $from ?: null, 'from_name' => mb_substr($names[$from] ?? (string)(array_values($names)[0] ?? ''), 0, 160) ?: null,
            'direction' => mb_substr((string)($c['direction'] ?? 'USER_INITIATED'), 0, 30), 'status' => 'ringing', 'sdp_offer' => (string)($c['session']['sdp'] ?? '') ?: null, 'created_at' => now(), 'updated_at' => now()]);
          Cache::put('wa.ring', time(), self::RING_SECONDS + 10);
        } elseif ($ev === 'terminate') {
          if (!$row) continue;
          $dur = isset($c['duration']) ? (int)$c['duration'] : null; $failed = (string)($c['status'] ?? '') === 'FAILED';
          $st = in_array($row->status, ['active', 'ended'], true) || $dur ? 'ended' : ($row->status === 'rejected' ? 'rejected' : ($failed ? 'failed' : 'missed'));
          DB::table('wa_calls')->where('id', $row->id)->update(['status' => $st, 'duration' => $dur ?? $row->duration, 'ended_at' => now(), 'sdp_offer' => null,
            'note' => $failed ? mb_substr('failed: ' . (string)($c['errors'][0]['message'] ?? ''), 0, 300) : $row->note, 'updated_at' => now()]);
        }
      }
    } catch (\Throwable $e) { report($e); }
  }
  /** Calls ringing right now (a ringing row older than the window is a call nobody took: marked missed). */
  public static function ringing() {
    if (!self::ready()) return collect();
    DB::table('wa_calls')->whereIn('status', ['ringing', 'answering'])->where('created_at', '<', now()->subSeconds(self::RING_SECONDS + 30))->update(['status' => 'missed', 'ended_at' => now(), 'sdp_offer' => null, 'updated_at' => now()]);
    return DB::table('wa_calls')->where('status', 'ringing')->orderBy('id')->get(['id', 'phone_id', 'from_wa', 'from_name', 'created_at']);
  }
  private static function act(object $c, array $body) {
    $tok = self::tok((string)$c->phone_id); if (!$tok) return null;
    return Http::timeout(15)->withToken($tok)->post(self::API . '/' . $c->phone_id . '/calls', ['messaging_product' => 'whatsapp', 'call_id' => $c->call_id] + $body);
  }
  /** Staff takes the call: only one person can (atomic claim). Returns [ok, message, sdp_offer]. */
  public static function claim(int $id, int $uid, string $uname): array {
    $n = DB::table('wa_calls')->where('id', $id)->where('status', 'ringing')->update(['status' => 'answering', 'user_id' => $uid, 'user_name' => mb_substr($uname, 0, 120), 'updated_at' => now()]);
    if (!$n) return [false, 'This call was already taken or has ended.', null];
    $c = DB::table('wa_calls')->find($id);
    return $c && $c->sdp_offer ? [true, 'ok', (string)$c->sdp_offer] : [false, 'Call data missing.', null];
  }
  /** Browser's SDP answer -> Meta (pre_accept, then accept). Returns [ok, message]. */
  public static function accept(int $id, int $uid, string $sdp): array {
    $c = DB::table('wa_calls')->find($id); if (!$c || $c->status !== 'answering' || (int)$c->user_id !== $uid) return [false, 'This call is not yours to answer.'];
    try {
      $s = ['session' => ['sdp_type' => 'answer', 'sdp' => $sdp]];
      $p = self::act($c, ['action' => 'pre_accept'] + $s); if (!$p) return [false, 'No token for this number.'];
      if (!$p->ok()) { $m = self::err($p); DB::table('wa_calls')->where('id', $id)->update(['status' => 'failed', 'note' => mb_substr('pre_accept: ' . $m, 0, 300), 'ended_at' => now(), 'updated_at' => now()]); return [false, $m]; }
      $a = self::act($c, ['action' => 'accept'] + $s);
      if (!$a->ok()) { $m = self::err($a); DB::table('wa_calls')->where('id', $id)->update(['status' => 'failed', 'note' => mb_substr('accept: ' . $m, 0, 300), 'ended_at' => now(), 'updated_at' => now()]); return [false, $m]; }
      DB::table('wa_calls')->where('id', $id)->update(['status' => 'active', 'answered_at' => now(), 'updated_at' => now()]);
      self::audit($uid, $c, 'answered');
      return [true, 'ok'];
    } catch (\Throwable $e) { report($e); return [false, 'Could not reach WhatsApp.']; }
  }
  /** Reject a ringing call, or hang up one of your own. */
  public static function end(int $id, int $uid, string $uname): array {
    $c = DB::table('wa_calls')->find($id); if (!$c) return [false, 'Call not found.'];
    try {
      if ($c->status === 'ringing') { $r = self::act($c, ['action' => 'reject']); DB::table('wa_calls')->where('id', $id)->update(['status' => 'rejected', 'user_id' => $uid, 'user_name' => mb_substr($uname, 0, 120), 'ended_at' => now(), 'sdp_offer' => null, 'updated_at' => now()]); self::audit($uid, $c, 'rejected'); return [true, 'rejected']; }
      if (in_array($c->status, ['answering', 'active'], true)) {
        self::act($c, ['action' => 'terminate']);
        $dur = $c->answered_at ? max(0, time() - strtotime((string)$c->answered_at)) : null;
        DB::table('wa_calls')->where('id', $id)->update(['status' => $c->status === 'active' ? 'ended' : 'missed', 'duration' => $dur, 'ended_at' => now(), 'sdp_offer' => null, 'updated_at' => now()]);
        return [true, 'ended'];
      }
    } catch (\Throwable $e) { report($e); }
    return [true, 'already ended'];
  }
  private static function audit(?int $uid, object $c, string $what): void {
    try { DB::table('audit_logs')->insert(['user_id' => $uid, 'user_name' => auth()->user()->name ?? '', 'action' => 'wa_call.' . $what, 'subject' => 'WhatsApp call ' . $what . ': ' . ($c->from_wa ?: 'unknown') . ' (call ' . mb_substr((string)$c->call_id, 0, 40) . ')', 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
}
