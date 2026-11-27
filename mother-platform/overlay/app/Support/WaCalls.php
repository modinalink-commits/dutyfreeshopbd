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
      return $base + ['ok' => true, 'status' => (string)($r->json('calling.status') ?? 'DISABLED'), 'icon' => (string)($r->json('calling.call_icon_visibility') ?? ''), 'sip' => (string)($r->json('calling.sip.status') ?? ''), 'callback' => (string)($r->json('calling.callback_permission_status') ?? '')];
    } catch (\Throwable $e) { return ['ok' => false, 'error' => 'request failed']; }
  }
  /** Turn calling on / off for a number (call button visible to customers). Returns [ok, message]. */
  public static function setCalling(string $phoneId, bool $on): array {
    $tok = self::tok($phoneId); if (!$tok) return [false, 'no token'];
    try {
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $phoneId . '/settings', ['calling' => $on ? ['status' => 'ENABLED', 'call_icon_visibility' => 'DEFAULT', 'callback_permission_status' => 'ENABLED'] : ['status' => 'DISABLED']]);
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
          if (strtoupper((string)($c['direction'] ?? '')) === 'BUSINESS_INITIATED') { // our own call back: Meta returns the customer's side of the audio (SDP answer) for the staff browser
            $ans = (string)($c['session']['sdp'] ?? '');
            if ($ans !== '') { if ($row) DB::table('wa_calls')->where('id', $row->id)->where('status', 'dialing')->update(['sdp_offer' => $ans, 'updated_at' => now()]); else Cache::put('wa.out.sdp.' . $cid, $ans, 180); }
            continue;
          }
          if ($row) continue;
          $from = preg_replace('/\D/', '', (string)($c['from'] ?? ''));
          DB::table('wa_calls')->insert(['call_id' => $cid, 'phone_id' => $pid, 'from_wa' => $from ?: null, 'from_name' => mb_substr($names[$from] ?? (string)(array_values($names)[0] ?? ''), 0, 160) ?: null,
            'direction' => mb_substr((string)($c['direction'] ?? 'USER_INITIATED'), 0, 30), 'status' => 'ringing', 'sdp_offer' => (string)($c['session']['sdp'] ?? '') ?: null, 'created_at' => now(), 'updated_at' => now()]);
          Cache::put('wa.ring', time(), self::RING_SECONDS + 10);
        } elseif ($ev === 'terminate') {
          if (!$row) continue;
          $dur = isset($c['duration']) ? (int)$c['duration'] : null; $failed = (string)($c['status'] ?? '') === 'FAILED';
          $outb = (string)$row->direction === 'BUSINESS_INITIATED'; // a call back the customer did not pick up is never a "missed call"
          $st = in_array($row->status, ['active', 'ended'], true) || $dur ? 'ended' : ($row->status === 'rejected' ? 'rejected' : ($failed || $outb ? 'failed' : 'missed'));
          DB::table('wa_calls')->where('id', $row->id)->update(['status' => $st, 'duration' => $dur ?? $row->duration, 'ended_at' => now(), 'sdp_offer' => null,
            'note' => $failed ? mb_substr('failed: ' . (string)($c['errors'][0]['message'] ?? ''), 0, 300) : ($outb && $st === 'failed' && !$row->note ? 'Customer did not answer' : $row->note), 'updated_at' => now()]);
          self::logFinal((int)$row->id);
        }
      }
      // call back progress: the customer picked up / declined (sent as call statuses, only for calls we started)
      foreach ($v['statuses'] ?? [] as $s) {
        if ((string)($s['type'] ?? '') !== 'call') continue; $cid = (string)($s['id'] ?? ''); if ($cid === '') continue;
        $row = DB::table('wa_calls')->where('call_id', $cid)->first(); if (!$row || (string)$row->direction !== 'BUSINESS_INITIATED' || $row->status !== 'dialing') continue;
        $st = strtoupper((string)($s['status'] ?? ''));
        if ($st === 'ACCEPTED') { DB::table('wa_calls')->where('id', $row->id)->where('status', 'dialing')->update(['status' => 'active', 'answered_at' => now(), 'updated_at' => now()]); self::beat((int)$row->id); }
        elseif ($st === 'REJECTED') { DB::table('wa_calls')->where('id', $row->id)->where('status', 'dialing')->update(['status' => 'rejected', 'ended_at' => now(), 'sdp_offer' => null, 'note' => 'Customer declined the call back', 'updated_at' => now()]); self::logFinal((int)$row->id); }
      }
    } catch (\Throwable $e) { report($e); }
  }
  /**
   * Housekeeping (worker, and every full poll): a ringing / answering row older than the ring window is a call nobody took -> missed;
   * an active call whose answering browser stopped reporting for a minute (closed laptop, lost internet) is hung up on WhatsApp so the customer is not left on a dead line.
   */
  public static function tick(): void {
    try {
      if (!self::ready()) return;
      foreach (DB::table('wa_calls')->whereIn('status', ['ringing', 'answering'])->where('created_at', '<', now()->subSeconds(self::RING_SECONDS + 30))->pluck('id') as $sid) { DB::table('wa_calls')->where('id', $sid)->whereIn('status', ['ringing', 'answering'])->update(['status' => 'missed', 'ended_at' => now(), 'sdp_offer' => null, 'updated_at' => now()]); self::logFinal((int)$sid); }
      foreach (DB::table('wa_calls')->where('status', 'dialing')->where('created_at', '<', now()->subSeconds(self::RING_SECONDS + 45))->limit(10)->get() as $c) {
        try { if (!str_starts_with((string)$c->call_id, 'out-')) self::act($c, ['action' => 'terminate']); } catch (\Throwable $e) {}
        if (DB::table('wa_calls')->where('id', $c->id)->where('status', 'dialing')->update(['status' => 'failed', 'ended_at' => now(), 'sdp_offer' => null, 'note' => 'Customer did not answer', 'updated_at' => now()])) self::logFinal((int)$c->id);
      }
      if (Cache::add('wa.alert.lock', 1, 55)) self::alert();
      foreach (DB::table('wa_calls')->where('status', 'active')->where('answered_at', '<', now()->subSeconds(60))->limit(10)->get() as $c) {
        if (Cache::has('wa.hb.' . $c->id)) continue;
        try { self::act($c, ['action' => 'terminate']); } catch (\Throwable $e) {}
        DB::table('wa_calls')->where('id', $c->id)->where('status', 'active')->update(['status' => 'ended', 'duration' => max(0, time() - strtotime((string)$c->answered_at)), 'ended_at' => now(), 'note' => mb_substr(trim((string)$c->note . ' [ended: staff connection lost]'), 0, 300), 'updated_at' => now()]);
      }
    } catch (\Throwable $e) { report($e); }
  }
  /** The answering browser reports "still on this call" every few seconds. */
  public static function beat(int $id): void { Cache::put('wa.hb.' . $id, time(), 60); }
  /** Display text of the business number a call came in on. */
  public static function numberLabel(string $phoneId): string {
    try { $n = DB::table('chat_wa_numbers')->where('phone_id', $phoneId)->first(); if ($n) return trim((string)$n->display_phone . ((string)$n->verified_name !== '' ? ' (' . $n->verified_name . ')' : '')); } catch (\Throwable $e) {}
    return 'WhatsApp number';
  }
  /** Calls ringing right now. */
  public static function ringing() {
    if (!self::ready()) return collect();
    self::tick();
    return DB::table('wa_calls')->where('status', 'ringing')->orderBy('id')->get(['id', 'phone_id', 'from_wa', 'from_name', 'created_at']);
  }
  /** The staff browser could not open the microphone / connection before answering: give the call back so another person can still take it. */
  public static function release(int $id, int $uid): bool {
    return (bool)DB::table('wa_calls')->where('id', $id)->where('status', 'answering')->where('user_id', $uid)->whereNull('answered_at')->where('created_at', '>', now()->subSeconds(self::RING_SECONDS))->update(['status' => 'ringing', 'user_id' => null, 'user_name' => null, 'updated_at' => now()]);
  }
  /** Staff note about the call (kept on the call row). */
  /** Results a representative can give a WhatsApp call (same idea as the PBX dialer outcomes). */
  const OUTCOMES = ['confirmed' => 'Order Confirmed', 'info' => 'Information given', 'pending' => 'Pending', 'call_later' => 'Call Later', 'complaint' => 'Complaint', 'cancelled' => 'Order Cancelled', 'other' => 'Other'];
  public static function hasResult(): bool { static $r = null; if ($r === null) { try { $r = self::ready() && Schema::hasColumn('wa_calls', 'outcome'); } catch (\Throwable $e) { $r = false; } } return $r; }
  /** Result and follow-up time of a call. $followup: local (Dhaka) date-time string, '' clears it. One staff-only line goes to the caller's conversation. */
  public static function saveResult(int $id, ?string $outcome, ?string $followup, string $by): bool {
    if (!self::hasResult()) return false; $c = DB::table('wa_calls')->find($id); if (!$c) return false; $upd = ['updated_at' => now()]; $say = [];
    if ($outcome !== null && ($outcome === '' || isset(self::OUTCOMES[$outcome]))) { $upd['outcome'] = $outcome ?: null; if ($outcome) $say[] = 'result: ' . self::OUTCOMES[$outcome]; }
    if ($followup !== null) {
      if (trim($followup) === '') { $upd['followup_at'] = null; }
      else { try { $at = \Carbon\Carbon::parse($followup, 'Asia/Dhaka'); $upd['followup_at'] = $at->copy()->utc(); $upd['followup_done_at'] = null; $say[] = 'follow-up ' . $at->format('d M, h:i A'); } catch (\Throwable $e) { return false; } }
    }
    DB::table('wa_calls')->where('id', $id)->update($upd);
    if ($say) self::threadNote($c->from_wa ?? null, 'WhatsApp call ' . implode(' · ', $say), (int)(auth()->id() ?? 0) ?: null, $by);
    return true;
  }
  public static function followupDone(int $id): bool { return self::hasResult() && (bool)DB::table('wa_calls')->where('id', $id)->whereNotNull('followup_at')->update(['followup_done_at' => now(), 'updated_at' => now()]); }
  /** Who a WhatsApp number belongs to, by the last 11 digits: customer, staff, supplier or unknown. */
  public static function who(?string $wa): array {
    $d = substr(preg_replace('/\D/', '', (string)$wa), -11); if (strlen($d) < 10) return ['kind' => 'unknown', 'label' => 'Unknown', 'name' => null];
    if ($rd = Rider::match($d)) return ['kind' => 'rider', 'label' => $rd['courier'] . ' rider', 'name' => $rd['name'], 'order' => $rd['invoice']];
    try {
      foreach ([['employees', 'Staff', 'staff'], ['suppliers', 'Supplier', 'supplier']] as [$tb, $lab, $k]) if (Schema::hasTable($tb) && Schema::hasColumn($tb, 'phone')) { $x = DB::table($tb)->whereRaw("right(regexp_replace(coalesce(phone,''), '\\D', '', 'g'), 11) = ?", [$d])->first(['name']); if ($x) return ['kind' => $k, 'label' => $lab, 'name' => (string)$x->name]; }
      $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$d])->first(['name']); if ($c) return ['kind' => 'customer', 'label' => 'Customer', 'name' => (string)$c->name];
      $o = DB::table('orders')->whereRaw('right(phone,11) = ?', [$d])->orderByDesc('id')->first(['customer_name']); if ($o) return ['kind' => 'customer', 'label' => 'Customer', 'name' => (string)$o->customer_name];
    } catch (\Throwable $e) {}
    return ['kind' => 'unknown', 'label' => 'New caller', 'name' => null];
  }
  /** IP (PBX) and WhatsApp calls of one phone number as plain rows, newest first: used by the Messages inbox customer panel. */
  public static function unified(?string $phone, $user, int $limit = 12): array {
    $d = substr(preg_replace('/\D/', '', (string)$phone), -11); if (strlen($d) < 10) return []; $out = [];
    $at = fn($v) => \Carbon\Carbon::parse($v, 'UTC')->timezone('Asia/Dhaka');
    try {
      $po = ['confirmed' => 'Order Confirmed', 'pending' => 'Pending', 'call_later' => 'Call Later', 'no_answer' => 'No Answer', 'cancelled' => 'Order Cancelled'];
      foreach (\App\Http\Controllers\CallCenterController::callRows($d, null)->take($limit) as $l) { $t = $at($l->started_at ?? $l->created_at);
        $out[] = ['ch' => 'ip', 'ts' => $t->getTimestamp(), 'at' => $t->format('d M h:i A'), 'agent' => (string)($l->user_name ?? ''), 'dir' => ($l->direction ?? '') === 'in' ? 'In' : 'Out', 'dur' => gmdate('i:s', (int)($l->duration ?? 0)),
          'result' => $po[$l->outcome ?? ''] ?? ucfirst((string)($l->status ?? '')), 'note' => (string)($l->note ?? ''), 'followup' => !empty($l->callback_at) ? $at($l->callback_at)->format('d M h:i A') : null,
          'play' => !empty($l->recording_url) ? (string)$l->recording_url : null, 'dl' => !empty($l->recording_url) && \App\Http\Controllers\CallCenterController::recDl($l) ? '/admin/call-center/rec/' . (int)$l->id . '/download' : null, 'id' => (int)$l->id, 'rec' => !empty($l->recording_url) ? 'play' : 'none']; }
    } catch (\Throwable $e) { report($e); }
    try {
      $lab = ['ringing' => 'Ringing', 'answering' => 'Answering', 'active' => 'On call', 'ended' => 'Answered', 'missed' => 'Missed', 'rejected' => 'Rejected', 'failed' => 'Failed'];
      foreach (array_slice(self::historyRows($d, null, $user), 0, $limit) as $w) { $c = $w['call']; $t = $at($c->created_at); $u = $w['rec_id'] ? '/admin/call-center/whatsapp/rec/' . $w['rec_id'] . '/audio?call=' . (int)$c->id : null;
        $out[] = ['ch' => 'wa', 'ts' => $t->getTimestamp(), 'at' => $t->format('d M h:i A'), 'agent' => (string)($c->user_name ?? ''), 'dir' => $c->status === 'missed' ? 'Missed' : ((($c->direction ?? '') === 'BUSINESS_INITIATED') ? 'Out' : 'In'), 'dur' => $c->duration !== null ? gmdate('i:s', (int)$c->duration) : '—',
          'result' => !empty($c->outcome) ? (self::OUTCOMES[$c->outcome] ?? (string)$c->outcome) : ($lab[$c->status] ?? (string)$c->status), 'note' => (string)($c->note ?? ''), 'followup' => !empty($c->followup_at) ? $at($c->followup_at)->format('d M h:i A') . (!empty($c->followup_done_at) ? ' · done' : '') : null,
          'play' => $w['state'] === 'play' ? $u : null, 'dl' => $w['state'] === 'play' && $w['dl'] ? $u . '&dl=1' : null, 'id' => (int)$c->id, 'rec' => $w['state']]; }
    } catch (\Throwable $e) { report($e); }
    usort($out, fn($a, $b) => $b['ts'] <=> $a['ts']);
    return array_slice($out, 0, $limit);
  }
  /** WhatsApp calls of one phone number (or linked to one order) with the state of each recording, for the shared call history. */
  public static function historyRows(?string $phone, ?int $orderId, $user): array {
    if (!self::ready()) return []; $rec = WaRec::ready();
    $q = DB::table('wa_calls')->orderByDesc('id')->limit(60);
    if ($orderId) { if (!$rec) return []; $q->whereIn('id', DB::table('wa_call_recordings')->where('order_id', $orderId)->select('call_id')); }
    elseif ($phone) { $d = substr(preg_replace('/\D/', '', $phone), -11); if (strlen($d) < 10) return []; $q->whereRaw('right(from_wa, 10) = ?', [substr($d, -10)]); }
    else return [];
    $rows = $q->get(); if ($rows->isEmpty()) return []; $recs = [];
    if ($rec) foreach (DB::table('wa_call_recordings')->whereIn('call_id', $rows->pluck('id'))->get() as $x) $recs[(int)$x->call_id] = $x;
    $out = [];
    foreach ($rows as $c) { $x = $recs[(int)$c->id] ?? null; $has = $x && in_array($x->status, ['complete', 'incomplete'], true); $file = $has && WaRec::file($x) !== null;
      $state = !$x ? ($c->status === 'ended' ? 'none' : 'na') : ($file ? (WaRec::canListen($user, $x) ? 'play' : 'denied') : ($has ? 'nofile' : (string)$x->status));
      $out[] = ['call' => $c, 'rec_id' => $x ? (int)$x->id : null, 'state' => $state, 'incomplete' => $x && $x->status === 'incomplete', 'dl' => $file && WaRec::mayDownload($user, $x->user_id ? (int)$x->user_id : null)]; }
    return $out;
  }
  public static function saveNote(int $id, string $text, string $by): bool {
    $ok = (bool)DB::table('wa_calls')->where('id', $id)->update(['note' => mb_substr(trim($text) . ' — ' . $by, 0, 300), 'updated_at' => now()]);
    if ($ok) { $c = DB::table('wa_calls')->find($id); self::threadNote($c->from_wa ?? null, 'Call note: ' . trim($text), (int)(auth()->id() ?? 0) ?: null, $by); }
    return $ok;
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
      DB::table('wa_calls')->where('id', $id)->update(['status' => 'active', 'answered_at' => now(), 'updated_at' => now()]); self::beat($id);
      self::audit($uid, $c, 'answered'); self::linkChat($c, $uid);
      return [true, 'ok'];
    } catch (\Throwable $e) { report($e); return [false, 'Could not reach WhatsApp.']; }
  }
  /** Reject a ringing call, or hang up one of your own. */
  public static function end(int $id, int $uid, string $uname): array {
    $c = DB::table('wa_calls')->find($id); if (!$c) return [false, 'Call not found.'];
    try {
      // Owner 19 Nov 2026: one representative's Reject only hides the call for THAT person; it keeps ringing for everyone else until someone answers or the ring window ends (then it becomes a missed call)
      if ($c->status === 'ringing') { Cache::put('wa.dis.' . $id . '.' . $uid, 1, 600); self::audit($uid, $c, 'dismissed'); return [true, 'dismissed']; }
      if (false) { $r = self::act($c, ['action' => 'reject']); DB::table('wa_calls')->where('id', $id)->update(['status' => 'rejected', 'user_id' => $uid, 'user_name' => mb_substr($uname, 0, 120), 'ended_at' => now(), 'sdp_offer' => null, 'updated_at' => now()]); self::audit($uid, $c, 'rejected'); return [true, 'rejected']; }
      if ($c->status === 'dialing') {
        if ((int)$c->user_id !== $uid) return [false, 'This call back belongs to another representative.'];
        if (!str_starts_with((string)$c->call_id, 'out-')) self::act($c, ['action' => 'terminate']);
        if (DB::table('wa_calls')->where('id', $id)->where('status', 'dialing')->update(['status' => 'failed', 'ended_at' => now(), 'sdp_offer' => null, 'note' => mb_substr('Call back cancelled by ' . $uname, 0, 300), 'updated_at' => now()])) self::logFinal($id);
        return [true, 'ended'];
      }
      if (in_array($c->status, ['answering', 'active'], true)) {
        self::act($c, ['action' => 'terminate']);
        $dur = $c->answered_at ? max(0, time() - strtotime((string)$c->answered_at)) : null;
        DB::table('wa_calls')->where('id', $id)->update(['status' => $c->status === 'active' ? 'ended' : 'missed', 'duration' => $dur, 'ended_at' => now(), 'sdp_offer' => null, 'updated_at' => now()]);
        return [true, 'ended'];
      }
    } catch (\Throwable $e) { report($e); }
    return [true, 'already ended'];
  }
  /**
   * WhatsApp call back (owner 20 Nov 2026): a representative calls a customer who called us on WhatsApp. Meta allows it only while the customer's call permission
   * is valid (given when they call us and tap Allow; "callback permission" is switched on with calling). Step 1 makes the call row, step 2 (dial) sends the browser's audio offer.
   * Returns [ok, message, new call id].
   */
  public static function outNew(int $fromCallId, int $uid, string $uname): array {
    $p = self::ready() ? DB::table('wa_calls')->find($fromCallId) : null; if (!$p) return [false, 'Call not found.', null];
    return self::outNewFor((string)$p->phone_id, (string)$p->from_wa, $p->from_name, $uid, $uname);
  }
  /** Same, for any customer of a business number (used by the chat inbox). */
  public static function outNewFor(string $phoneId, ?string $wa, ?string $name, int $uid, string $uname): array {
    try {
      if (!self::ready()) return [false, 'WhatsApp calls are not set up.', null];
      $p = (object)['phone_id' => $phoneId, 'from_name' => $name ? mb_substr($name, 0, 160) : null];
      $wa = preg_replace('/\D/', '', (string)$wa); if ($wa === '') return [false, 'WhatsApp did not share this caller\'s number, so a WhatsApp call back is not possible.', null];
      if (!self::tok((string)$p->phone_id)) return [false, 'This business number is not connected for calling.', null];
      if (DB::table('wa_calls')->where('user_id', $uid)->whereIn('status', ['dialing', 'answering', 'active'])->where('updated_at', '>', now()->subMinutes(3))->exists()) return [false, 'You are already on a WhatsApp call. End it first.', null];
      if (DB::table('wa_calls')->where('from_wa', $wa)->whereIn('status', ['dialing', 'ringing', 'answering', 'active'])->where('updated_at', '>', now()->subMinutes(3))->exists()) return [false, 'A WhatsApp call with this customer is already in progress.', null];
      $id = (int)DB::table('wa_calls')->insertGetId(['call_id' => 'out-' . bin2hex(random_bytes(16)), 'phone_id' => $p->phone_id, 'from_wa' => $wa, 'from_name' => $p->from_name, 'direction' => 'BUSINESS_INITIATED', 'status' => 'dialing',
        'user_id' => $uid, 'user_name' => mb_substr($uname, 0, 120), 'created_at' => now(), 'updated_at' => now()]);
      $c = DB::table('wa_calls')->find($id); self::audit($uid, $c, 'callback started'); self::linkChat($c, $uid);
      return [true, 'ok', $id];
    } catch (\Throwable $e) { report($e); return [false, 'Could not start the call back.', null]; }
  }
  /** Step 2: the browser's SDP offer goes to Meta, which rings the customer. Returns [ok, message]. */
  public static function dial(int $id, int $uid, string $sdp): array {
    $c = DB::table('wa_calls')->find($id);
    if (!$c || $c->status !== 'dialing' || (int)$c->user_id !== $uid || !str_starts_with((string)$c->call_id, 'out-')) return [false, 'This call back cannot be started (it was already sent or has ended).'];
    $tok = self::tok((string)$c->phone_id); if (!$tok) return [false, 'No token for this number.'];
    $fail = function (string $m) use ($id) { DB::table('wa_calls')->where('id', $id)->where('status', 'dialing')->update(['status' => 'failed', 'ended_at' => now(), 'note' => mb_substr($m, 0, 300), 'updated_at' => now()]); self::logFinal($id); return [false, $m]; };
    try {
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $c->phone_id . '/calls', ['messaging_product' => 'whatsapp', 'to' => (string)$c->from_wa, 'action' => 'connect', 'session' => ['sdp_type' => 'offer', 'sdp' => $sdp], 'biz_opaque_callback_data' => 'wc' . $id]);
      if (!$r->ok()) { $code = (int)$r->json('error.code');
        return $fail($code === 138006 ? 'The customer has not allowed WhatsApp call back yet. Permission is given when they call us on WhatsApp and tap Allow. Call them on the phone instead.' : self::err($r)); }
      $cid = (string)($r->json('calls.0.id') ?? ''); if ($cid === '') return $fail('WhatsApp did not return a call id.');
      $ans = Cache::pull('wa.out.sdp.' . $cid); // the answer can arrive on the webhook before this response does
      DB::table('wa_calls')->where('id', $id)->update(['call_id' => $cid, 'sdp_offer' => $ans ?: null, 'updated_at' => now()]);
      return [true, 'ok'];
    } catch (\Throwable $e) { report($e); return $fail('Could not reach WhatsApp.'); }
  }
  // ---------------- calling a customer from the chat inbox (owner 20 Nov 2026)
  /** The WhatsApp thread as [thread, phoneId, wa] or null when it is not a WhatsApp conversation with a number. */
  public static function threadTarget(int $threadId): ?array {
    try { $t = DB::table('chat_threads')->find($threadId); if (!$t || $t->channel !== 'whatsapp') return null;
      $wa = preg_replace('/\D/', '', (string)$t->external_id); if ($wa === '') return null;
      [, $pid] = Chat::waCred($t->page_ref ?? null); return $pid ? [$t, (string)$pid, $wa] : null; } catch (\Throwable $e) { return null; }
  }
  /** Meta's own answer: may we call this customer now, and may we ask? Returns ['ok','status','can_call','can_ask','error']. */
  public static function permission(string $phoneId, string $wa): array {
    $tok = self::tok($phoneId); if (!$tok) return ['ok' => false, 'error' => 'This business number is not connected for calling.'];
    try {
      $r = Http::timeout(15)->withToken($tok)->get(self::API . '/' . $phoneId . '/call_permissions', ['user_wa_id' => $wa]);
      if (!$r->ok()) return ['ok' => false, 'error' => self::err($r)];
      $can = ['start_call' => null, 'send_call_permission_request' => null]; // null = Meta did not say; then the call itself decides
      foreach ((array)$r->json('actions') as $a) { $n = (string)($a['action_name'] ?? $a['name'] ?? ''); if (array_key_exists($n, $can)) $can[$n] = (bool)($a['can_perform_action'] ?? false); }
      $st = (string)($r->json('permission.status') ?? 'no_permission');
      return ['ok' => true, 'status' => $st, 'can_call' => $st !== 'no_permission' && ($can['start_call'] ?? true), 'can_ask' => $can['send_call_permission_request'] ?? true];
    } catch (\Throwable $e) { return ['ok' => false, 'error' => 'Could not reach WhatsApp.']; }
  }
  /** Sends WhatsApp's own "allow calls from this business" request into the chat (free-form: Meta accepts it only inside the customer's open 24-hour window; at most 1 a day and 2 a week per customer). Returns [ok, message]. */
  public static function askPermission(int $threadId, int $uid, string $by): array {
    $x = self::threadTarget($threadId); if (!$x) return [false, 'This is not a WhatsApp conversation.']; [$t, $pid, $wa] = $x;
    $tok = self::tok($pid); if (!$tok) return [false, 'This business number is not connected for calling.'];
    try {
      $text = 'আসসালামু আলাইকুম। আপনার বিষয়টি নিয়ে কথা বলতে DutyFreeBD থেকে আপনাকে WhatsApp-এ কল করতে চাই। কল করার অনুমতি দিতে নিচের বাটনে চাপ দিন।';
      $r = Http::timeout(20)->withToken($tok)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $wa, 'type' => 'interactive',
        'interactive' => ['type' => 'call_permission_request', 'action' => ['name' => 'call_permission_request'], 'body' => ['text' => $text]]]);
      if (!$r->ok()) { $c = (int)$r->json('error.code'); return [false, in_array($c, [131047, 131026], true) ? 'WhatsApp did not accept the request: the customer has not written to us in the last 24 hours. Ask again after they send a message, or call on the phone.' : self::err($r)]; }
      self::threadNote($wa, 'WhatsApp call permission requested from the customer.', $uid, $by);
      try { DB::table('audit_logs')->insert(['user_id' => $uid, 'user_name' => $by, 'action' => 'wa_call.permission_request', 'subject' => 'WhatsApp call permission requested: ' . $wa, 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
      return [true, 'Permission request sent. When the customer taps Allow, press WhatsApp Call again.'];
    } catch (\Throwable $e) { report($e); return [false, 'Could not reach WhatsApp.']; }
  }
  /** Chat inbox "WhatsApp Call": starts the call when Meta says we may, otherwise says what is missing. */
  public static function callThread(int $threadId, int $uid, string $uname): array {
    $x = self::threadTarget($threadId); if (!$x) return ['ok' => false, 'message' => 'This is not a WhatsApp conversation.']; [$t, $pid, $wa] = $x;
    $p = self::permission($pid, $wa); if (!$p['ok']) return ['ok' => false, 'message' => 'WhatsApp calling is not available for this number: ' . $p['error']];
    if (!$p['can_call']) return ['ok' => false, 'need' => 'permission', 'can_ask' => $p['can_ask'],
      'message' => $p['status'] === 'no_permission' ? 'The customer has not allowed WhatsApp calls from us yet.' : 'WhatsApp does not allow another call to this customer right now (call limit reached).'];
    [$ok, $m, $id] = self::outNewFor($pid, $wa, (string)($t->name ?? ''), $uid, $uname);
    return ['ok' => $ok, 'message' => $m, 'id' => $id];
  }
  /** The customer's SDP answer for the representative who is calling back (null until Meta has sent it). */
  public static function outAnswer(object $c, int $uid): ?string {
    if ((string)$c->direction !== 'BUSINESS_INITIATED' || (int)$c->user_id !== $uid || !in_array($c->status, ['dialing', 'active'], true)) return null;
    if ($c->sdp_offer) return (string)$c->sdp_offer;
    $s = Cache::get('wa.out.sdp.' . $c->call_id); return $s ? (string)$s : null;
  }
  /** Last messages of the caller's WhatsApp conversation for the call dock. Read only: opening it here never marks the chat as seen or answered. */
  /** One WhatsApp call as a plain row: only what the record holds (nothing is guessed). at = when the call started (UTC, ISO). */
  private static function rowWa(object $c): array {
    $out = (string)($c->direction ?? '') === 'BUSINESS_INITIATED'; $st = (string)$c->status;
    $res = $st === 'ended' ? 'answered' : (in_array($st, ['missed', 'rejected', 'failed'], true) ? $st : 'ongoing');
    if ($out && $st === 'failed' && (string)($c->note ?? '') === 'Customer did not answer') $res = 'no_answer';
    return ['src' => 'wa', 'id' => (int)$c->id, 'dir' => $out ? 'out' : 'in', 'result' => $res, 'by' => (string)($c->user_name ?? ''), 'who' => (string)($c->from_name ?? ''),
      'at' => \Carbon\Carbon::parse($c->created_at, 'UTC')->toIso8601String(), 'dur' => $c->duration !== null ? (int)$c->duration : null, 'note' => (string)($c->note ?? '')];
  }
  /** One phone (PBX / dialer) call as a plain row. It is never labelled WhatsApp. */
  private static function rowIp(object $l): array {
    $st = strtolower((string)($l->status ?? '')); $t = $l->started_at ?? $l->created_at;
    return ['src' => 'ip', 'id' => (int)$l->id, 'dir' => ($l->direction ?? '') === 'in' ? 'in' : 'out', 'result' => $st !== '' ? $st : 'unknown', 'by' => (string)($l->user_name ?? ''), 'who' => (string)($l->customer_name ?? ''),
      'at' => \Carbon\Carbon::parse($t, 'UTC')->toIso8601String(), 'dur' => isset($l->duration) ? (int)$l->duration : null, 'note' => ''];
  }
  /** Every recorded call of one customer number (WhatsApp and phone kept apart), newest first. */
  public static function history(?string $phone, int $limit = 60): array {
    $d = substr(preg_replace('/\D/', '', (string)$phone), -10); if (strlen($d) < 7) return []; $out = [];
    try { if (self::ready()) foreach (DB::table('wa_calls')->whereRaw('right(from_wa, 10) = ?', [$d])->orderByDesc('id')->limit($limit)->get() as $c) $out[] = self::rowWa($c); } catch (\Throwable $e) { report($e); }
    try { if (Schema::hasTable('call_logs')) foreach (DB::table('call_logs')->whereRaw('right(phone, 10) = ?', [$d])->orderByDesc('id')->limit($limit)->get() as $l) $out[] = self::rowIp($l); } catch (\Throwable $e) { report($e); }
    usort($out, fn($a, $b) => strcmp($b['at'], $a['at']));
    return array_slice($out, 0, $limit);
  }
  /** Latest call per customer for a page of chat cards: key = last 10 digits. WhatsApp and phone calls are both considered; each row says which. */
  public static function lastByKeys(array $keys): array {
    $keys = array_values(array_unique(array_filter($keys, fn($k) => strlen((string)$k) >= 7))); if (!$keys) return []; $out = []; $in = implode(',', array_fill(0, count($keys), '?'));
    try { if (self::ready()) foreach (DB::select("select distinct on (right(from_wa, 10)) * from wa_calls where right(from_wa, 10) in ($in) order by right(from_wa, 10), id desc", $keys) as $c) $out[substr(preg_replace('/\D/', '', (string)$c->from_wa), -10)] = self::rowWa($c); } catch (\Throwable $e) { report($e); }
    try { if (Schema::hasTable('call_logs')) foreach (DB::select("select distinct on (right(phone, 10)) * from call_logs where right(phone, 10) in ($in) order by right(phone, 10), id desc", $keys) as $l) { $r = self::rowIp($l); $k = substr(preg_replace('/\D/', '', (string)$l->phone), -10); if (!isset($out[$k]) || strcmp($r['at'], $out[$k]['at']) > 0) $out[$k] = $r; } } catch (\Throwable $e) { report($e); }
    return $out;
  }
  public static function chat(?string $wa, int $limit = 30): array {
    $out = ['thread_id' => null, 'messages' => []];
    try {
      $t = self::thread($wa); if (!$t) return $out; $out['thread_id'] = (int)$t->id;
      foreach (DB::table('chat_messages')->where('thread_id', $t->id)->orderByDesc('id')->limit($limit)->get(['id', 'dir', 'kind', 'body', 'attachment_type', 'user_name', 'status', 'created_at'])->reverse() as $m)
        $out['messages'][] = ['id' => (int)$m->id, 'out' => $m->dir === 'out', 'body' => mb_substr((string)$m->body, 0, 1500), 'att' => (string)($m->attachment_type ?: ($m->kind !== 'text' ? $m->kind : '')), 'by' => (string)$m->user_name, 'failed' => $m->status === 'failed',
          'at' => \Carbon\Carbon::parse($m->created_at, 'UTC')->timezone('Asia/Dhaka')->format('d M h:i A')];
    } catch (\Throwable $e) {}
    return $out;
  }
  /** The WhatsApp conversation of the caller, if there is one. */
  public static function thread(?string $wa): ?object {
    $wa = preg_replace('/\D/', '', (string)$wa); if ($wa === '') return null;
    try { return DB::table('chat_threads')->where(['channel' => 'whatsapp', 'external_id' => $wa])->first(); } catch (\Throwable $e) { return null; }
  }
  /** Staff-only line in the caller's conversation (never sent to the customer, never given to the AI), so chat and calls are read together. */
  private static function threadNote(?string $wa, string $body, ?int $uid = null, string $by = 'System'): void {
    try { $t = self::thread($wa); if (!$t || !Schema::hasTable('chat_notes')) return;
      DB::table('chat_notes')->insert(['thread_id' => (int)$t->id, 'user_id' => $uid, 'user_name' => mb_substr($by, 0, 120), 'body' => mb_substr($body, 0, 1000), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  /** One staff-only line per finished call. */
  private static function logFinal(int $id): void {
    try { if (!Cache::add('wa.final.' . $id, 1, 172800)) return; $c = DB::table('wa_calls')->find($id); if (!$c) return;
      if ((string)$c->direction === 'BUSINESS_INITIATED') { $who = $c->user_name ?: 'staff';
        $t2 = ['ended' => 'connected' . ($c->duration !== null ? ', talk time ' . gmdate('i:s', (int)$c->duration) : ''), 'rejected' => 'customer declined', 'failed' => (string)($c->note ?: 'not connected')][$c->status] ?? null;
        if ($t2) self::threadNote($c->from_wa, 'WhatsApp call back by ' . $who . ': ' . $t2, null, 'Call log'); return; }
      $txt = ['ended' => 'answered by ' . ($c->user_name ?: 'staff') . ($c->duration !== null ? ', talk time ' . gmdate('i:s', (int)$c->duration) : ''), 'missed' => 'MISSED - call the customer back', 'rejected' => 'rejected by ' . ($c->user_name ?: 'staff'), 'failed' => 'failed to connect'][$c->status] ?? null;
      if ($txt) self::threadNote($c->from_wa, 'WhatsApp call: ' . $txt, null, 'Call log'); } catch (\Throwable $e) {}
  }
  /** Calls and chat stay with one representative: the person who answers the call takes the caller's conversation if nobody owns it (so the AI stops answering it). Never takes it away from another owner. */
  private static function linkChat(object $c, int $uid): void {
    try { $t = self::thread($c->from_wa); if (!$t || !class_exists(ChatOwner::class) || !ChatOwner::ready()) return; $u = DB::table('users')->find($uid); if ($u) ChatOwner::claim((int)$t->id, $u); } catch (\Throwable $e) {}
  }
  /** Missed calls nobody has dealt with yet (last 3 days). */
  public static function missedOpen(): int {
    try { return self::ready() && Schema::hasColumn('wa_calls', 'handled_at') ? (int)DB::table('wa_calls')->where('status', 'missed')->whereNull('handled_at')->where('created_at', '>', now()->subDays(3))->count() : 0; } catch (\Throwable $e) { return 0; }
  }
  /** Owner 27 Nov 2026: chats with a missed WhatsApp call nobody has dealt with (same 3-day window as missedOpen). Key = last 10 digits of the customer's number; value = ['n' => how many, 'id' => newest call id, 'at' => ISO time of the newest]. Read only. */
  public static function missedByPhone(): array {
    try {
      if (!self::ready() || !Schema::hasColumn('wa_calls', 'handled_at')) return [];
      $out = [];
      foreach (DB::table('wa_calls')->where('status', 'missed')->whereNull('handled_at')->where('created_at', '>', now()->subDays(3))->whereNotNull('from_wa')->orderBy('id')->get(['id', 'from_wa', 'created_at']) as $c) {
        $k = substr(preg_replace('/\D+/', '', (string)$c->from_wa), -10); if (strlen($k) < 7) continue;
        $o = $out[$k] ?? ['n' => 0, 'id' => 0, 'at' => null]; $o['n']++; $o['id'] = (int)$c->id; $o['at'] = \Carbon\Carbon::parse($c->created_at)->toIso8601String(); $out[$k] = $o;
      }
      return $out;
    } catch (\Throwable $e) { return []; }
  }
  /** A representative called the customer back / dealt with the missed call. */
  public static function handle(int $id, int $uid, string $by): bool {
    try { $n = DB::table('wa_calls')->where('id', $id)->where('status', 'missed')->whereNull('handled_at')->update(['handled_at' => now(), 'handled_by' => mb_substr($by, 0, 120), 'updated_at' => now()]);
      if ($n) { $c = DB::table('wa_calls')->find($id); self::threadNote($c->from_wa, 'Missed WhatsApp call handled (customer contacted).', $uid, $by); self::alert(); }
      return (bool)$n; } catch (\Throwable $e) { return false; }
  }
  /** Bell alert while a missed WhatsApp call waits more than 5 minutes for a callback; cleared when none is left. */
  public static function alert(): void {
    try {
      if (!self::ready() || !Schema::hasColumn('wa_calls', 'handled_at')) return;
      $q = DB::table('wa_calls')->where('status', 'missed')->whereNull('handled_at')->where('created_at', '>', now()->subDays(3))->where('created_at', '<', now()->subMinutes(5));
      $n = (int)$q->count();
      if ($n > 0) DeployHealth::raise('wa_missed_calls', 'medium', 'Call Center', $n . ' missed WhatsApp call' . ($n > 1 ? 's' : '') . ' waiting for a callback', 'Customers called on WhatsApp and nobody answered. They are listed under Call Center > WhatsApp Calls.', 'No representative answered within the ring time (about one minute).', 'Open WhatsApp Calls, call each customer back and press Done.');
      else DeployHealth::resolve('wa_missed_calls', 'System (all missed WhatsApp calls handled)');
      if (self::hasResult()) {
        $f = (int)DB::table('wa_calls')->whereNotNull('followup_at')->whereNull('followup_done_at')->where('followup_at', '<=', now())->where('followup_at', '>', now()->subDays(7))->count();
        if ($f > 0) DeployHealth::raise('wa_followups_due', 'medium', 'Call Center', $f . ' WhatsApp call follow-up' . ($f > 1 ? 's are' : ' is') . ' due', 'A representative promised to contact these customers again and the time has come. They are listed under Call Center > WhatsApp Calls.', 'The follow-up time set on the call has passed and nobody pressed Done.', 'Open WhatsApp Calls, contact each customer and press Done beside the follow-up.');
        else DeployHealth::resolve('wa_followups_due', 'System (no WhatsApp follow-up is due)');
      }
    } catch (\Throwable $e) {}
  }
  /** Extra customer facts for the call dock that the dialer lookup does not give. Read only; empty when the number matches nothing. */
  public static function extras(?string $wa): array {
    $ph = Chat::phone($wa); $out = ['returns' => 0, 'cancelled' => 0, 'address' => null, 'notes' => [], 'owner' => null];
    try {
      if ($ph && preg_match('/^01\d{9}$/', $ph)) {
        $out['returns'] = (int)DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->whereIn('status', ['Return', 'Returned'])->count();
        $out['cancelled'] = (int)DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->whereIn('status', ['Cancel', 'Cancelled'])->count();
        $o = DB::table('orders')->whereRaw('right(phone,11) = ?', [$ph])->whereNotNull('address')->orderByDesc('id')->first(['address', 'district']);
        if ($o && trim((string)$o->address) !== '') $out['address'] = trim((string)$o->address . (trim((string)($o->district ?? '')) !== '' ? ', ' . $o->district : ''));
      }
      if ($t = self::thread($wa)) {
        if (class_exists(ChatOwner::class) && ChatOwner::ready() && ($oid = ChatOwner::ownerId((int)$t->id))) $out['owner'] = ChatOwner::name($oid);
        if (Schema::hasTable('chat_notes')) foreach (DB::table('chat_notes')->where('thread_id', $t->id)->orderByDesc('id')->limit(6)->get() as $n) $out['notes'][] = ['body' => (string)$n->body, 'by' => (string)$n->user_name, 'at' => \Carbon\Carbon::parse($n->created_at)->timezone('Asia/Dhaka')->format('d M h:i A')];
      }
    } catch (\Throwable $e) {}
    return $out;
  }
  private static function audit(?int $uid, object $c, string $what): void {
    try { DB::table('audit_logs')->insert(['user_id' => $uid, 'user_name' => auth()->user()->name ?? '', 'action' => 'wa_call.' . $what, 'subject' => 'WhatsApp call ' . $what . ': ' . ($c->from_wa ?: 'unknown') . ' (call ' . mb_substr((string)$c->call_id, 0, 40) . ')', 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
}
