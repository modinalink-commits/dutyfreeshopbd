<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversation ownership (owner 16 Nov 2026): one customer is answered by one representative.
 *  - chat_threads.assigned_to is the owner. Only the owner's messages are sent; everyone else may read and write staff-only notes.
 *  - Taking a conversation is one conditional UPDATE, so when two people take it at the same moment exactly one succeeds.
 *  - Every send (person, AI, automation) first takes a short lease on the conversation and the lease is given only while the sender is still allowed
 *    to send. The owner cannot change while a message is on its way, and a sender who lost the conversation is refused at the last step.
 *  - While a person owns the conversation the AI does not answer it.
 * All rules are enforced here, on the server; the screen only shows them.
 */
class ChatOwner {
  const LEASE = 180; // seconds one send may hold the conversation (a crashed send frees itself after this)
  private static ?bool $ready = null;
  /** Set by AgentHandoff in the run that hands a chat to a person: the AI's own closing line of that run may still go out. */
  public static array $handoffRun = [];
  /** True when the last guard() call refused the send (nothing left the server). */
  public static bool $refused = false;

  public static function ready(): bool {
    if (self::$ready !== null) return self::$ready;
    try { return self::$ready = Schema::hasColumn('chat_threads', 'assign_ver') && Schema::hasTable('chat_assign_events'); } catch (\Throwable $e) { return false; }
  }
  /** Supervisors may move any conversation: the owner accounts (super / admin) and roles ticked "chat_supervisor". */
  public static function supervisor($u): bool {
    if (!$u) return false;
    if (in_array((string)($u->role ?? ''), ['super', 'admin'], true)) return true;
    try { $r = \App\Http\Middleware\ModulePermission::rules($u); return $r === null || !empty($r['chat_supervisor']); } catch (\Throwable $e) { return false; }
  }
  public static function supervisors() {
    return DB::table('users')->where('active', true)->get()->filter(fn($u) => self::supervisor($u))->values();
  }
  public static function name(?int $uid): string { return $uid ? (string)(DB::table('users')->where('id', $uid)->value('name') ?? 'Staff #'.$uid) : ''; }
  public static function log(int $tid, string $action, ?int $from, ?int $to, ?int $actorId, ?string $actorName, ?string $note = null): void {
    try { DB::table('chat_assign_events')->insert(['thread_id'=>$tid, 'action'=>$action, 'from_user'=>$from, 'to_user'=>$to, 'actor_id'=>$actorId, 'actor_name'=>mb_substr((string)($actorName ?? 'System'), 0, 120), 'note'=>$note !== null && $note !== '' ? mb_substr($note, 0, 1000) : null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) { report($e); }
  }
  private static function free($q) { return $q->where(fn($w) => $w->whereNull('send_lock_until')->orWhere('send_lock_until', '<', now())); }

  /** Change the owner in one statement. $expect is the owner we believe it has now (null = nobody). Returns 'ok', 'changed' (somebody else changed it first) or 'busy' (a message is being sent). */
  private static function swap(int $tid, ?int $expect, ?int $to): string {
    for ($i = 0; $i < 24; $i++) {
      $q = DB::table('chat_threads')->where('id', $tid);
      $expect === null ? $q->whereNull('assigned_to') : $q->where('assigned_to', $expect);
      $n = self::free($q)->update(['assigned_to'=>$to, 'assigned_at'=>$to ? now() : null, 'assign_ver'=>DB::raw('assign_ver + 1'), 'absent_alert_at'=>null, 'updated_at'=>now()]);
      if ($n) return 'ok';
      $cur = DB::table('chat_threads')->where('id', $tid)->first(['assigned_to']);
      if (!$cur || ($cur->assigned_to === null ? null : (int)$cur->assigned_to) !== $expect) return 'changed';
      usleep(250000); // the owner is as expected, so a message is on its way: wait for it to finish
    }
    return 'busy';
  }
  const BUSY = 'A message is being sent in this conversation right now. Try again in a few seconds.';
  public static function heldMsg(?int $owner): string { return 'This conversation is now held by '.self::name($owner).'. You can read and write notes, but you cannot send replies.'; }

  /** A representative takes an unassigned conversation. [ok, message] */
  public static function claim(int $tid, object $user): array {
    $uid = (int)$user->id; $t = DB::table('chat_threads')->where('id', $tid)->first(['id', 'assigned_to']);
    if (!$t) return [false, 'Conversation not found.'];
    if ($t->assigned_to !== null) return (int)$t->assigned_to === $uid ? [true, 'already yours'] : [false, self::heldMsg((int)$t->assigned_to)];
    $r = self::swap($tid, null, $uid);
    if ($r === 'ok') { self::log($tid, 'claim', null, $uid, $uid, $user->name ?? null); return [true, 'claimed']; }
    if ($r === 'busy') return [false, self::BUSY];
    $now = DB::table('chat_threads')->where('id', $tid)->value('assigned_to');
    return $now !== null && (int)$now === $uid ? [true, 'already yours'] : [false, $now !== null ? self::heldMsg((int)$now) : 'Please try again.'];
  }
  /** Used before a staff send: the sender must own the conversation; an unassigned one is taken on the spot (only one person can win that). [ok, message] */
  public static function ensureOwner(int $tid, object $user): array { return self::claim($tid, $user); }

  /**
   * Move or release a conversation. A supervisor may give any conversation to any active staff member (or to nobody = back to the queue).
   * The owner may only release their own conversation. The note is saved as a staff-only note so the next person reads why. [ok, message]
   */
  public static function transfer(int $tid, ?int $to, object $actor, string $note = ''): array {
    $t = DB::table('chat_threads')->where('id', $tid)->first(['id', 'assigned_to']); if (!$t) return [false, 'Conversation not found.'];
    $from = $t->assigned_to === null ? null : (int)$t->assigned_to; $aid = (int)$actor->id; $sup = self::supervisor($actor);
    if (!$sup) {
      if ($from === null && $to === $aid) return self::claim($tid, $actor);
      if (!($from === $aid && $to === null)) return [false, 'Only a supervisor can change the owner. You can only release your own conversation.'];
    }
    if ($to !== null && !DB::table('users')->where('id', $to)->where('active', true)->exists()) return [false, 'This user is not active.'];
    if ($from === $to) return [true, 'no change'];
    $r = self::swap($tid, $from, $to);
    if ($r === 'busy') return [false, self::BUSY];
    if ($r !== 'ok') return [false, 'The owner changed in the meantime. Refresh the page and check again.'];
    $note = trim($note);
    self::log($tid, $to === null ? 'release' : ($from === null ? 'assign' : 'transfer'), $from, $to, $aid, $actor->name ?? null, $note);
    if ($note !== '') { try { DB::table('chat_notes')->insert(['thread_id'=>$tid, 'user_id'=>$aid, 'user_name'=>$actor->name ?? '', 'body'=>'Handover note ('.($from ? self::name($from) : 'nobody').' → '.($to ? self::name($to) : 'queue').'): '.$note, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {} }
    return [true, 'done'];
  }
  /** Owner change made by the system (auto-assignment, hand-off by the AI, reopen, close, staff absent). Returns true when it changed. */
  public static function system(int $tid, ?int $to, string $action, string $why = ''): bool {
    if (!self::ready()) return false;
    $cur = DB::table('chat_threads')->where('id', $tid)->first(['assigned_to']); if (!$cur) return false;
    $from = $cur->assigned_to === null ? null : (int)$cur->assigned_to; if ($from === $to) return false;
    if (self::swap($tid, $from, $to) !== 'ok') return false;
    self::log($tid, $action, $from, $to, null, 'System', $why);
    return true;
  }
  public static function ownerId(int $tid): ?int { if (!self::ready()) return null; $v = DB::table('chat_threads')->where('id', $tid)->value('assigned_to'); return $v === null ? null : (int)$v; }

  /**
   * Run one send while holding the conversation. $who: ['type'=>'human','id'=>..,'name'=>..] (must be the owner), ['type'=>'ai'] (only while nobody owns it),
   * ['type'=>'system'] (automation notices; only kept from colliding with another send). Returns what $fn returns, or [false, reason] when the send is refused.
   */
  public static function guard(object $t, array $who, callable $fn): array {
    self::$refused = false;
    if (!self::ready() || empty($t->id)) return $fn();
    $tid = (int)$t->id; $type = (string)($who['type'] ?? 'system'); $tok = bin2hex(random_bytes(10));
    for ($i = 0; $i < 60; $i++) {
      $q = self::free(DB::table('chat_threads')->where('id', $tid));
      if ($type === 'human') $q->where('assigned_to', (int)($who['id'] ?? 0)); elseif ($type === 'ai') $q->whereNull('assigned_to');
      if ($q->update(['send_lock_until'=>now()->addSeconds(self::LEASE), 'send_lock_tok'=>$tok])) {
        try { return $fn(); }
        finally { try { DB::table('chat_threads')->where('id', $tid)->where('send_lock_tok', $tok)->update(['send_lock_until'=>null, 'send_lock_tok'=>null]); } catch (\Throwable $e) {} }
      }
      $cur = DB::table('chat_threads')->where('id', $tid)->first(['assigned_to']);
      if (!$cur) return [false, 'Conversation not found.'];
      $own = $cur->assigned_to === null ? null : (int)$cur->assigned_to;
      if ($type === 'human' && $own !== (int)($who['id'] ?? 0)) {
        self::log($tid, 'reply_blocked', $own, null, (int)($who['id'] ?? 0), $who['name'] ?? null, 'Send refused: not the owner at the moment of sending.');
        self::$refused = true;
        return [false, $own ? self::heldMsg($own) : 'This conversation is no longer yours. The reply was not sent.'];
      }
      if ($type === 'ai' && $own !== null) { self::$refused = true; return [false, 'Not sent: '.self::name($own).' is handling this conversation.']; }
      usleep(250000); // allowed, but another message is on its way: wait for it
    }
    self::$refused = true;
    return [false, self::BUSY];
  }

  /** Would the AI answer this conversation by itself right now? (When it would not, a new customer message goes to a person.) */
  public static function botHandles(object $t): bool {
    try {
      if (!AiChat::chatOn() || !in_array($t->channel, ['web', 'whatsapp', 'facebook'], true)) return false;
      if ($t->channel !== 'web' && !AiChat::chanOn()) return false;
      if (AiChat::paused($t)) return false;
      return AiSchedule::activeNow() || Chat::aiTakeover((int)$t->id);
    } catch (\Throwable $e) { return true; }
  }
  /** A new customer message arrived (called from Chat::ingest). A closed conversation that is reopened starts without an owner, open to everyone. Nothing is assigned here. */
  public static function onCustomerMessage(object $before, object $fresh): void {
    if (!self::ready()) return;
    try {
      $tid = (int)$fresh->id;
      if ($before->status === 'resolved' && $fresh->assigned_to !== null) { self::system($tid, null, 'release', 'Closed conversation reopened by a new customer message.'); $fresh->assigned_to = null; }
      // Owner rule 16 Nov 2026: a new message is never given to a representative in advance. It waits in the open list; whoever first sends a reply becomes the owner (ChatController::reply).
    } catch (\Throwable $e) { report($e); }
  }

  /** Worker tick: tell the supervisors when an owner has left a customer waiting past the limit, and close sends that were cut off. */
  public static function tick(): void {
    if (!self::ready() || !Cache::add('chat.owner.tick', 1, 50)) return;
    try { DB::table('chat_messages')->where('status', 'sending')->where('created_at', '<', now()->subMinutes(5))->update(['status'=>'failed', 'error'=>'Sending was interrupted before the platform confirmed it. Check the chat before sending again.', 'updated_at'=>now()]); } catch (\Throwable $e) {}
    try { self::unstick(); } catch (\Throwable $e) {}
    try { self::releaseAbsent(); } catch (\Throwable $e) {}
    try {
      $min = max(2, min(240, (int)(Sms::secret('chat_absent_min') ?: 10)));
      $rows = DB::table('chat_threads')->whereNotNull('assigned_to')->where('awaiting_reply', true)->where('status', '!=', 'resolved')
        ->whereNotNull('awaiting_since')->where('awaiting_since', '<=', now()->subMinutes($min))->where('awaiting_since', '>=', now()->subHours(22))
        ->where(fn($w) => $w->whereNull('absent_alert_at')->orWhereColumn('absent_alert_at', '<', 'awaiting_since'))->orderBy('awaiting_since')->limit(30)->get();
      if ($rows->isEmpty()) { self::health($min); return; }
      $sups = self::supervisors();
      foreach ($rows as $t) {
        if (!DB::table('chat_threads')->where('id', $t->id)->where(fn($w) => $w->whereNull('absent_alert_at')->orWhereColumn('absent_alert_at', '<', 'awaiting_since'))->update(['absent_alert_at'=>now()])) continue; // once per waiting period
        $own = (int)$t->assigned_to; $wait = (int)\Carbon\Carbon::parse($t->awaiting_since)->diffInMinutes(now()); $cust = trim((string)($t->name ?: $t->phone ?: 'A customer'));
        self::log((int)$t->id, 'absent_alert', $own, null, null, 'System', $cust.' has waited '.$wait.' minutes for '.self::name($own).'. Supervisors were told.');
        foreach ($sups as $s) { if ((int)$s->id === $own) continue;
          try { DB::table('chat_wait_notices')->insert(['thread_id'=>$t->id, 'user_id'=>$s->id, 'user_name'=>$s->name, 'customer'=>mb_substr($cust.' (owner: '.self::name($own).')', 0, 160), 'wait_min'=>$wait, 'assigned'=>false, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {} }
      }
      self::health($min);
    } catch (\Throwable $e) { report($e); }
  }
  /** Minutes an owner may leave a customer unanswered before the conversation goes back to the open list (Chat Settings; 0 = never). */
  public static function releaseMin(): int { $v = Sms::secret('chat_release_min'); return $v === null || $v === '' ? 20 : max(0, min(480, (int)$v)); }
  /** The owner is away: the conversation returns to the open list for everyone, and the old owner can no longer send in it (sending always checks the owner). */
  public static function releaseAbsent(): void {
    $min = self::releaseMin(); if ($min <= 0) return;
    $rows = DB::table('chat_threads')->whereNotNull('assigned_to')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->whereNotNull('awaiting_since')
      ->where('awaiting_since', '<=', now()->subMinutes($min))->orderBy('awaiting_since')->limit(40)->get(['id', 'assigned_to', 'awaiting_since', 'name', 'phone']);
    foreach ($rows as $t) {
      $own = (int)$t->assigned_to; $nm = self::name($own);
      if (!self::system((int)$t->id, null, 'release', $nm.' did not reply for '.$min.' minutes: back to the open list for everyone.')) continue;
      try { DB::table('chat_notes')->insert(['thread_id'=>$t->id, 'user_id'=>null, 'user_name'=>'System', 'body'=>$nm.' '.$min.' minutes without a reply. The conversation went back to the open list; whoever replies first becomes the owner.', 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    }
  }
  /** A first reply that was cut off before the platform confirmed it must not leave the conversation with that person: when the owner has no delivered reply since taking it, it is open again. */
  private static function unstick(): void {
    $rows = DB::table('chat_messages')->where('status', 'failed')->where('dir', 'out')->whereNotNull('user_id')->where('updated_at', '>=', now()->subMinutes(3))->where('error', 'like', 'Sending was interrupted%')->limit(40)->get(['thread_id', 'user_id']);
    foreach ($rows as $m) self::releaseIfNeverAnswered((int)$m->thread_id, (int)$m->user_id, 'A reply was interrupted before it was delivered.');
  }
  /** Give the conversation back to everyone when $uid owns it but has not delivered one reply since taking it. Returns true when it was released. */
  public static function releaseIfNeverAnswered(int $tid, int $uid, string $why): bool {
    if (!self::ready()) return false;
    $t = DB::table('chat_threads')->where('id', $tid)->first(['assigned_to', 'assigned_at']); if (!$t || $t->assigned_to === null || (int)$t->assigned_to !== $uid) return false;
    $q = DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->where('status', 'sent')->where('user_id', $uid);
    if ($t->assigned_at) $q->where('created_at', '>=', \Carbon\Carbon::parse($t->assigned_at)->subSeconds(5));
    if ($q->exists()) return false;
    return self::system($tid, null, 'release', $why.' No reply was delivered, so the conversation is open to everyone again.');
  }
  /** Who has this conversation open right now (other than $me) and who is typing. Kept for 15 seconds per person; display only, it never gives or blocks anything. */
  public static function presence(int $tid, object $me, bool $typing): array {
    try {
      $k = 'chat.pres.'.$tid; $now = time(); $all = (array)Cache::get($k, []);
      $all[(int)$me->id] = ['n'=>(string)($me->name ?? ''), 't'=>$now, 'ty'=>$typing ? $now : (int)($all[(int)$me->id]['ty'] ?? 0)];
      foreach ($all as $uid => $p) if ($now - (int)($p['t'] ?? 0) > 15) unset($all[$uid]);
      Cache::put($k, $all, 60);
      $out = []; foreach ($all as $uid => $p) if ((int)$uid !== (int)$me->id) $out[] = ['name'=>$p['n'], 'typing'=>$now - (int)$p['ty'] <= 6];
      return $out;
    } catch (\Throwable $e) { return []; }
  }
  /** One line in the owner's Issues Center while any owned conversation is waiting past the limit. */
  private static function health(int $min): void {
    try {
      $late = DB::table('chat_threads')->whereNotNull('assigned_to')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->whereNotNull('awaiting_since')->where('awaiting_since', '<=', now()->subMinutes($min))->where('awaiting_since', '>=', now()->subHours(22))->orderBy('awaiting_since')->limit(40)->get(['id', 'name', 'phone', 'assigned_to', 'awaiting_since']);
      if ($late->isEmpty()) { DeployHealth::resolve('chat.absent', 'System (every owner answered)'); return; }
      $ag = DB::table('users')->whereIn('id', $late->pluck('assigned_to')->unique())->pluck('name', 'id');
      $list = $late->take(6)->map(fn($t) => trim((string)($t->name ?: $t->phone ?: 'Customer')).' ('.(int)\Carbon\Carbon::parse($t->awaiting_since)->diffInMinutes(now()).' min, '.($ag[$t->assigned_to] ?? 'staff').')')->implode('; ');
      DeployHealth::raise('chat.absent', 'high', 'Messages', $late->count().' assigned conversation'.($late->count() === 1 ? '' : 's').' waiting over '.$min.' minutes for the owner', $list.'.', 'The representative who owns the conversation has not replied within '.$min.' minutes.', 'Open Messages, check the conversation and move it to another representative if the owner is away.');
    } catch (\Throwable $e) {}
  }
  /** History of one conversation for the screen: who took it, who moved it, when. */
  public static function events(int $tid, int $limit = 40): array {
    if (!self::ready()) return [];
    try {
      $rows = DB::table('chat_assign_events')->where('thread_id', $tid)->orderByDesc('id')->limit($limit)->get(); if ($rows->isEmpty()) return [];
      $names = DB::table('users')->whereIn('id', $rows->pluck('from_user')->merge($rows->pluck('to_user'))->filter()->unique())->pluck('name', 'id');
      return $rows->map(fn($e) => ['id'=>$e->id, 'action'=>$e->action, 'from'=>$e->from_user ? ($names[$e->from_user] ?? '#'.$e->from_user) : null, 'to'=>$e->to_user ? ($names[$e->to_user] ?? '#'.$e->to_user) : null, 'by'=>$e->actor_name, 'note'=>$e->note, 'at'=>\Carbon\Carbon::parse($e->created_at)->toIso8601String()])->all();
    } catch (\Throwable $e) { return []; }
  }
}
