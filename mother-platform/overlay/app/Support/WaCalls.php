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
          self::logFinal((int)$row->id);
        }
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
    try {
      foreach ([['employees', 'Staff', 'staff'], ['suppliers', 'Supplier', 'supplier']] as [$tb, $lab, $k]) if (Schema::hasTable($tb) && Schema::hasColumn($tb, 'phone')) { $x = DB::table($tb)->whereRaw("right(regexp_replace(coalesce(phone,''), '\\D', '', 'g'), 11) = ?", [$d])->first(['name']); if ($x) return ['kind' => $k, 'label' => $lab, 'name' => (string)$x->name]; }
      $c = DB::table('customers')->whereRaw('right(phone,11) = ?', [$d])->first(['name']); if ($c) return ['kind' => 'customer', 'label' => 'Customer', 'name' => (string)$c->name];
      $o = DB::table('orders')->whereRaw('right(phone,11) = ?', [$d])->orderByDesc('id')->first(['customer_name']); if ($o) return ['kind' => 'customer', 'label' => 'Customer', 'name' => (string)$o->customer_name];
    } catch (\Throwable $e) {}
    return ['kind' => 'unknown', 'label' => 'New caller', 'name' => null];
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
