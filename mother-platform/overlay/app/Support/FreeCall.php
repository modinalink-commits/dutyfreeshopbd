<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Free Call: browser-to-browser (WebRTC) voice calls between a website visitor and DutyFreeBD staff.
 * Laravel + PostgreSQL keep the queue, routing, history and the signalling messages (offer / answer / ICE); the audio itself never touches the server.
 * Real-time updates use short polling (this deployment has no Redis / WebSocket server); the browsers poll every ~2 seconds.
 */
class FreeCall
{
  const LIVE = ['waiting', 'ringing', 'accepted', 'connected'];
  const STATUS = ['waiting' => 'Waiting', 'ringing' => 'Ringing', 'accepted' => 'Connecting', 'connected' => 'Connected', 'completed' => 'Completed', 'cancelled' => 'Cancelled by Customer', 'declined' => 'Declined', 'missed' => 'Missed', 'no_agent' => 'No Agent Available', 'failed' => 'Connection Failed', 'dropped' => 'Dropped'];
  const OUTCOMES = ['resolved' => 'Resolved', 'follow_up' => 'Follow-up Required', 'order' => 'Order Related', 'lead' => 'Sales Lead', 'technical' => 'Not Reached / Technical'];

  public static function set(string $k, string $d = ''): string { $v = DB::table('settings')->where('key', 'fc_' . $k)->value('value'); return $v === null ? $d : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => 'fc_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function ready(): bool { return \Illuminate\Support\Facades\Schema::hasTable('customer_calls'); }
  public static function on(): bool { return self::set('on', '0') === '1'; }
  public static function ringTimeout(): int { return max(5, min(60, (int)self::set('ring_timeout', '15'))); }
  public static function maxQueue(): int { return max(1, min(200, (int)self::set('max_queue', '20'))); }
  public static function agentFresh(): int { return 20; }
  public static function norm(string $p): string { return substr(preg_replace('/\D/', '', $p), -11); }
  public static function validMobile(string $p): bool { return (bool)preg_match('/^01\d{9}$/', $p); }

  /** Optional operating hours (Dhaka time). Off by default: availability then depends only on the agents. */
  public static function hoursOk(): bool {
    if (self::set('hours_on', '0') !== '1') return true;
    $n = now('Asia/Dhaka')->format('H:i'); $f = self::set('hours_from', '09:00'); $t = self::set('hours_to', '22:00');
    return $f <= $t ? ($n >= $f && $n < $t) : ($n >= $f || $n < $t);
  }
  /** Customer-facing availability: [visible, open, message]. Staff who are signed in can always try it (preview) even while the service is OFF. */
  public static function widget(bool $staff = false): array {
    if (!self::ready()) return [false, false, ''];
    if (!self::on()) return [$staff, $staff, $staff ? 'Preview: Free Call is OFF for customers.' : 'Free Call is currently offline.'];
    if (!self::hoursOk()) return [true, false, 'Free Call is outside operating hours (' . self::set('hours_from', '09:00') . '–' . self::set('hours_to', '22:00') . '). Please use Chat.'];
    return [true, true, ''];
  }

  /** ICE servers for one call. STUN always; TURN (mandatory for mobile networks / CGNAT) with short-lived credentials when a shared secret is set. */
  public static function ice(string $uuid): array {
    $o = [['urls' => array_values(array_filter(array_map('trim', explode(',', self::set('stun', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302')))))]];
    $urls = array_values(array_filter(array_map('trim', explode(',', self::set('turn_urls')))));
    if ($urls) {
      $sec = Sms::secret('fc_turn_secret');
      if ($sec) { $u = (time() + 7200) . ':' . $uuid; $o[] = ['urls' => $urls, 'username' => $u, 'credential' => base64_encode(hash_hmac('sha1', $u, $sec, true))]; }
      else { $user = Sms::secret('fc_turn_user'); $cred = Sms::secret('fc_turn_cred'); if ($user && $cred) $o[] = ['urls' => $urls, 'username' => $user, 'credential' => $cred]; }
    }
    return $o;
  }
  public static function turnReady(): bool { return self::set('turn_urls') !== '' && (bool)(Sms::secret('fc_turn_secret') || (Sms::secret('fc_turn_user') && Sms::secret('fc_turn_cred'))); }

  public static function ev(int $callId, string $type, ?int $staff = null, array $meta = []): void {
    DB::table('customer_call_events')->insert(['call_id' => $callId, 'event_type' => $type, 'staff_id' => $staff, 'metadata' => $meta ? json_encode($meta) : null, 'created_at' => now()]);
  }
  private static function tried(object $c): array { $j = json_decode((string)$c->tried_staff, true); return is_array($j) ? array_map('intval', $j) : []; }

  // ---------------------------------------------------------------- customer side
  /** @return array{0:?object,1:string} [call, error] */
  public static function request(array $in): array {
    [$vis, $open, $msg] = self::widget(!empty($in['staff']));
    if (!$open) return [null, $msg ?: 'Free Call is currently offline.'];
    $p = self::norm((string)($in['phone'] ?? '')); if (!self::validMobile($p)) return [null, 'Enter a valid 11-digit mobile number starting with 01.'];
    $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 60));
    // one active Free Call per customer / session
    $ex = DB::table('customer_calls')->whereIn('status', self::LIVE)->where(fn($q) => $q->where('mobile', $p)->orWhere('customer_session_id', (string)($in['session'] ?? '-')))->orderByDesc('id')->first();
    if ($ex) return [$ex, ''];
    // gentle per-number limit (never per shared IP)
    $k = 'fc.rl.' . $p; $n = (int)Cache::get($k, 0); if ($n >= 8) return [null, 'Too many call attempts. Please try again in a few minutes or use Chat.']; Cache::put($k, $n + 1, 600);
    if (DB::table('customer_calls')->whereIn('status', ['waiting', 'ringing'])->count() >= self::maxQueue()) return [null, 'Our representatives are currently handling a high number of calls. Please try again shortly or use Chat.'];
    $cu = DB::table('customers')->whereRaw('right(phone,11) = ?', [$p])->first();
    $ctx = ['name' => $name ?: ($cu->name ?? ''), 'cart' => mb_substr((string)($in['cart'] ?? ''), 0, 200), 'page_title' => mb_substr((string)($in['title'] ?? ''), 0, 160)];
    $pid = null; if (!empty($in['product_id']) && is_numeric($in['product_id'])) { $pr = DB::table('products')->find((int)$in['product_id']); if ($pr) { $pid = (int)$pr->id; $ctx['product'] = $pr->name; $ctx['code'] = $pr->ext_id ?? null; } }
    $oid = null; if (!empty($in['invoice'])) { $o = DB::table('orders')->where('invoice', mb_substr((string)$in['invoice'], 0, 40))->whereRaw('right(phone,11) = ?', [$p])->first(); if ($o) $oid = (int)$o->id; }
    $url = mb_substr((string)($in['url'] ?? ''), 0, 500); $type = $oid ? 'order' : ($pid ? 'product' : (preg_match('#/checkout#', $url) ? 'checkout' : 'page'));
    $uuid = bin2hex(random_bytes(16)); $sess = $in['session'] ?? bin2hex(random_bytes(16));
    $id = DB::table('customer_calls')->insertGetId(['call_uuid' => $uuid, 'customer_id' => $cu->id ?? null, 'guest_name' => $name ?: null, 'mobile' => $p, 'order_id' => $oid, 'product_id' => $pid, 'source_url' => $url ?: null, 'source_type' => $type,
      'status' => 'waiting', 'requested_at' => now(), 'customer_session_id' => $sess, 'cust_seen_at' => now(), 'context' => json_encode($ctx), 'created_at' => now(), 'updated_at' => now()]);
    self::ev($id, 'requested'); self::ev($id, 'queued');
    self::dispatch();
    return [DB::table('customer_calls')->find($id), ''];
  }
  public static function position(object $c): int { return (int)DB::table('customer_calls')->whereIn('status', ['waiting', 'ringing'])->where('requested_at', '<', $c->requested_at)->count() + 1; }

  // ---------------------------------------------------------------- agents
  public static function touchAgent(int $staff): object {
    $a = DB::table('free_call_agents')->where('staff_id', $staff)->first();
    if (!$a) { DB::table('free_call_agents')->insert(['staff_id' => $staff, 'voice_status' => 'offline', 'last_seen_at' => now(), 'updated_at' => now()]); return DB::table('free_call_agents')->where('staff_id', $staff)->first(); }
    DB::table('free_call_agents')->where('staff_id', $staff)->update(['last_seen_at' => now()]); $a->last_seen_at = now(); return $a;
  }
  public static function setStatus(int $staff, string $st): void {
    if (!in_array($st, ['available', 'busy', 'offline'], true)) return; self::touchAgent($staff);
    $u = ['voice_status' => $st, 'updated_at' => now()]; if ($st === 'available') $u['last_available_at'] = now();
    DB::table('free_call_agents')->where('staff_id', $staff)->update($u); self::dispatch();
  }
  /** Stored status + live facts => what the UI shows. */
  public static function effective(object $a): string {
    if ($a->active_call_id) return 'busy';
    $fresh = $a->last_seen_at && time() - strtotime($a->last_seen_at . ' UTC') <= self::agentFresh();
    if (!$fresh) return 'offline'; return $a->voice_status === 'busy' ? 'busy' : ($a->voice_status === 'available' ? 'available' : 'offline');
  }

  // ---------------------------------------------------------------- routing
  /** FIFO queue; each call is offered to the agent who has been available the longest, one at a time, with a ring timeout. */
  public static function dispatch(): void {
    try { $lock = Cache::lock('freecall:dispatch', 8); if (!$lock->get()) return; } catch (\Throwable $e) { $lock = null; }
    try {
      $now = now();
      foreach (DB::table('customer_calls')->where('status', 'ringing')->get() as $c) {
        $ag = DB::table('free_call_agents')->where('staff_id', $c->ring_staff_id)->first();
        $agentGone = $ag && (!$ag->last_seen_at || time() - strtotime($ag->last_seen_at . ' UTC') > self::agentFresh());
        if (($c->ring_expires_at && strtotime($c->ring_expires_at . ' UTC') <= time()) || $agentGone || !$ag) {
          $t = self::tried($c); $t[] = (int)$c->ring_staff_id; self::ev($c->id, 'agent_timeout', (int)$c->ring_staff_id);
          DB::table('customer_calls')->where('id', $c->id)->update(['status' => 'waiting', 'ring_staff_id' => null, 'ring_expires_at' => null, 'tried_staff' => json_encode(array_values(array_unique($t))), 'updated_at' => now()]);
        }
      }
      $ringing = DB::table('customer_calls')->where('status', 'ringing')->pluck('ring_staff_id')->map(fn($v) => (int)$v)->all();
      $agents = DB::table('free_call_agents')->where('voice_status', 'available')->whereNull('active_call_id')->where('last_seen_at', '>=', now()->subSeconds(self::agentFresh()))->orderBy('last_available_at')->get()->all();
      foreach (DB::table('customer_calls')->where('status', 'waiting')->orderBy('requested_at')->get() as $c) {
        $free = array_values(array_filter($agents, fn($a) => !in_array((int)$a->staff_id, $ringing, true)));
        if (!$free) break;
        $tried = self::tried($c); $pick = null;
        foreach ($free as $a) if (!in_array((int)$a->staff_id, $tried, true)) { $pick = $a; break; }
        if (!$pick) { $pick = $free[0]; $tried = []; } // everybody has been tried: start another round
        DB::table('customer_calls')->where('id', $c->id)->update(['status' => 'ringing', 'ring_staff_id' => $pick->staff_id, 'ringing_at' => $now, 'ring_expires_at' => now()->addSeconds(self::ringTimeout()), 'tried_staff' => json_encode($tried), 'updated_at' => now()]);
        self::ev($c->id, 'agent_ringing', (int)$pick->staff_id); $ringing[] = (int)$pick->staff_id;
      }
    } finally { if ($lock) { try { $lock->release(); } catch (\Throwable $e) {} } }
  }
  public static function accept(int $id, int $staff): ?string {
    $c = DB::table('customer_calls')->find($id); if (!$c) return 'Call not found.';
    if ($c->status !== 'ringing' || (int)$c->ring_staff_id !== $staff) return 'This call is no longer ringing for you.';
    $a = self::touchAgent($staff); if ($a->active_call_id) return 'You are already on a call.';
    $n = DB::table('customer_calls')->where('id', $id)->where('status', 'ringing')->update(['status' => 'accepted', 'assigned_staff_id' => $staff, 'answered_at' => now(), 'waiting_seconds' => max(0, time() - strtotime($c->requested_at . ' UTC')), 'agent_seen_at' => now(), 'ring_expires_at' => null, 'updated_at' => now()]);
    if (!$n) return 'This call was already handled.';
    DB::table('free_call_agents')->where('staff_id', $staff)->update(['active_call_id' => $id, 'updated_at' => now()]);
    self::ev($id, 'agent_assigned', $staff); self::ev($id, 'accepted', $staff); return null;
  }
  public static function decline(int $id, int $staff): void {
    $c = DB::table('customer_calls')->find($id); if (!$c || $c->status !== 'ringing' || (int)$c->ring_staff_id !== $staff) return;
    $t = self::tried($c); $t[] = $staff; self::ev($id, 'agent_declined', $staff);
    DB::table('customer_calls')->where('id', $id)->update(['status' => 'waiting', 'ring_staff_id' => null, 'ring_expires_at' => null, 'tried_staff' => json_encode(array_values(array_unique($t))), 'updated_at' => now()]);
    self::dispatch();
  }
  public static function connected(int $id): void {
    $n = DB::table('customer_calls')->where('id', $id)->where('status', 'accepted')->update(['status' => 'connected', 'connected_at' => now(), 'updated_at' => now()]); if ($n) self::ev($id, 'connected');
  }
  /** End a call, free the agent, drop the temporary signalling data, and keep routing the queue. */
  public static function finish(int $id, string $status, string $reason): void {
    $c = DB::table('customer_calls')->find($id); if (!$c || !in_array($c->status, self::LIVE, true)) return;
    $start = $c->connected_at ?: $c->answered_at; $dur = $start ? max(0, time() - strtotime($start . ' UTC')) : null;
    DB::table('customer_calls')->where('id', $id)->update(['status' => $status, 'ended_at' => now(), 'duration_seconds' => $dur, 'disconnect_reason' => $reason, 'waiting_seconds' => $c->waiting_seconds ?? max(0, time() - strtotime($c->requested_at . ' UTC')), 'ring_staff_id' => null, 'ring_expires_at' => null, 'updated_at' => now()]);
    DB::table('free_call_agents')->where('active_call_id', $id)->update(['active_call_id' => null, 'updated_at' => now()]);
    DB::table('customer_call_signals')->where('call_id', $id)->delete();
    self::ev($id, in_array($status, ['completed'], true) ? 'completed' : ($status === 'failed' ? 'failed' : 'disconnected'), $c->assigned_staff_id ? (int)$c->assigned_staff_id : null, ['reason' => $reason, 'status' => $status]);
    self::dispatch();
  }
  /** Heartbeat / ghost cleanup: nobody stays Busy or in the queue forever. */
  public static function sweep(bool $force = false): void {
    if (!$force && !Cache::add('fc.sweep', 1, 2)) return;
    $hb = max(8, (int)self::set('heartbeat', '20')); $wait = max(30, (int)self::set('wait_hb', '90')); $maxWait = max(60, (int)self::set('max_wait', '900'));
    foreach (DB::table('customer_calls')->whereIn('status', self::LIVE)->get() as $c) {
      $cs = $c->cust_seen_at ? time() - strtotime($c->cust_seen_at . ' UTC') : 9999; $as = $c->agent_seen_at ? time() - strtotime($c->agent_seen_at . ' UTC') : 0;
      $age = time() - strtotime($c->requested_at . ' UTC');
      if (in_array($c->status, ['waiting', 'ringing'], true)) {
        if ($cs > $wait) self::finish($c->id, 'cancelled', 'customer_lost');
        elseif ($age > $maxWait) self::finish($c->id, $c->ringing_at ? 'missed' : 'no_agent', 'max_wait');
      } elseif ($c->status === 'accepted') {
        if ($cs > $hb) self::finish($c->id, 'cancelled', 'customer_lost'); elseif ($as > $hb) self::finish($c->id, 'failed', 'agent_lost');
        elseif (time() - strtotime($c->answered_at . ' UTC') > 60) self::finish($c->id, 'failed', 'connection_failed');
      } else { if ($cs > $hb) self::finish($c->id, 'dropped', 'customer_lost'); elseif ($as > $hb) self::finish($c->id, 'dropped', 'agent_lost'); }
    }
    foreach (DB::table('free_call_agents')->whereNotNull('active_call_id')->get() as $a) { $c = DB::table('customer_calls')->find($a->active_call_id); if (!$c || !in_array($c->status, self::LIVE, true)) DB::table('free_call_agents')->where('staff_id', $a->staff_id)->update(['active_call_id' => null]); }
    self::dispatch();
  }
  public static function signal(int $callId, string $to, string $type, string $payload): void {
    if (!in_array($type, ['offer', 'answer', 'ice', 'hangup', 'status'], true) || !in_array($to, ['agent', 'customer'], true)) return;
    DB::table('customer_call_signals')->insert(['call_id' => $callId, 'to_side' => $to, 'type' => $type, 'payload' => mb_substr($payload, 0, 20000), 'created_at' => now()]);
  }
  public static function signals(int $callId, string $to, int $after): array {
    return DB::table('customer_call_signals')->where('call_id', $callId)->where('to_side', $to)->where('id', '>', $after)->orderBy('id')->limit(80)->get(['id', 'type', 'payload'])->map(fn($r) => ['id' => (int)$r->id, 'type' => $r->type, 'payload' => $r->payload])->all();
  }

  // ---------------------------------------------------------------- context for the agent
  public static function ctx(object $c): array {
    $x = json_decode((string)$c->context, true) ?: []; $cu = $c->customer_id ? DB::table('customers')->find($c->customer_id) : DB::table('customers')->whereRaw('right(phone,11) = ?', [$c->mobile])->first();
    $o = ['id' => (int)$c->id, 'uuid' => $c->call_uuid, 'name' => $cu->name ?? ($x['name'] ?? '') ?: ($c->guest_name ?: ''), 'mobile' => $c->mobile, 'guest' => !$cu, 'customer_id' => $cu->id ?? null,
      'level' => $cu ? ucfirst((string)($cu->customer_level ?: 'Regular')) : null, 'product' => $x['product'] ?? null, 'code' => $x['code'] ?? null, 'page' => $c->source_url, 'page_title' => $x['page_title'] ?? null, 'cart' => $x['cart'] ?? null,
      'waiting' => max(0, time() - strtotime($c->requested_at . ' UTC')), 'status' => $c->status, 'order' => null, 'latest' => null, 'counts' => null];
    $q = DB::table('orders')->whereRaw('right(phone,11) = ?', [$c->mobile]);
    $o['counts'] = ['total' => (clone $q)->count(), 'delivered' => (clone $q)->where('status', 'Delivered')->count(), 'returned' => (clone $q)->where('status', 'Returned')->count(), 'cancelled' => (clone $q)->where('status', 'Cancel')->count()];
    $l = (clone $q)->orderByDesc('id')->first(); if ($l) $o['latest'] = ['id' => (int)$l->id, 'invoice' => $l->invoice, 'status' => $l->status, 'total' => (float)$l->total];
    if ($c->order_id) { $r = DB::table('orders')->find($c->order_id); if ($r) $o['order'] = ['id' => (int)$r->id, 'invoice' => $r->invoice, 'status' => $r->status, 'total' => (float)$r->total, 'courier' => $r->courier ?? null]; }
    return $o;
  }
  public static function agentName(?int $id): string { if (!$id) return ''; $n = (string)DB::table('users')->where('id', $id)->value('name'); return trim(explode(' ', trim($n))[0] ?? '') ?: 'Customer Care'; }
}
