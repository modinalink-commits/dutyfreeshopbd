<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Staff idle check (owner rule, 14 Nov 2026). "Work" = a real action (a successful save / reply / status change), never just opening pages.
 *  A notice is raised only when work was actually waiting (customers needing a person, or new orders) while the staff member was signed in and did nothing.
 *  The notice locks the admin for that person until they write a reason; the reason goes to the owner as a ticket. Owner and admins are exempt. */
class StaffIdle {
  const BREAKS = ['namaz' => 'নামাজ', 'meal' => 'খাবার', 'packing' => 'প্যাকিং / অফলাইন কাজ', 'call' => 'কাস্টমারের সাথে ফোনে', 'other' => 'অন্য বিরতি'];
  private static ?bool $ready = null;

  public static function ready(): bool { if (self::$ready) return true; try { $ok = Schema::hasTable('staff_idle_notices'); if ($ok) self::$ready = true; return $ok; } catch (\Throwable $e) { return false; } }
  public static function set(string $k, string $d = ''): string { return Procure::set('staff_idle_' . $k, $d); }
  public static function on(): bool { return self::set('enabled', '0') === '1' && self::ready(); }
  public static function limit(): int { return max(10, min(240, (int)self::set('limit', '30'))); }
  public static function warnAt(): int { return max(5, self::limit() - 5); }
  /** Duty hours in Dhaka time, e.g. 10 -> 22. */
  public static function dutyNow(): bool {
    $h = (int)now('Asia/Dhaka')->format('G'); $a = (int)self::set('from', '10'); $b = (int)self::set('to', '22');
    return $a === $b ? true : ($a < $b ? ($h >= $a && $h < $b) : ($h >= $a || $h < $b));
  }
  public static function applies($u): bool {
    if (!$u || in_array((string)($u->role ?? ''), ['super', 'admin'], true)) return false;
    $only = array_filter(array_map('trim', explode(',', self::set('roles', '')))); // empty = every staff role
    return !$only || in_array((string)($u->role ?? ''), $only, true);
  }
  private static function k(string $p, int $uid): string { return 'staff.idle.' . $p . '.' . $uid . '.' . now('Asia/Dhaka')->format('Ymd'); }

  /** A real action was done: the idle clock starts again. */
  public static function work(int $uid): void { try { Cache::put(self::k('w', $uid), time(), 86400); } catch (\Throwable $e) {} }
  /** The person is signed in and their screen is talking to us. */
  public static function seen(int $uid): void {
    try {
      $last = (int)Cache::get(self::k('s', $uid), 0); $now = time();
      // Away for more than 10 minutes (PC off, logged out, outside): the clock restarts when they come back, so time away from the screen is not counted as idling at the desk.
      if ($last === 0 || $now - $last > 600) Cache::put(self::k('w', $uid), $now, 86400);
      Cache::put(self::k('s', $uid), $now, 86400);
    } catch (\Throwable $e) {}
  }
  public static function onBreak(int $uid): ?array { try { $b = Cache::get('staff.idle.break.' . $uid); return is_array($b) && ($b['until'] ?? 0) > time() ? $b : null; } catch (\Throwable $e) { return null; } }
  public static function startBreak(int $uid, string $kind, int $min): void {
    $kind = isset(self::BREAKS[$kind]) ? $kind : 'other'; $min = max(5, min(45, $min));
    Cache::put('staff.idle.break.' . $uid, ['kind' => $kind, 'until' => time() + $min * 60, 'from' => time()], $min * 60 + 60);
    try { DB::table('audit_logs')->insert(['user_id' => $uid, 'user_name' => auth()->user()->name ?? '', 'action' => 'staff.break', 'subject' => 'Break: ' . self::BREAKS[$kind] . ' (' . $min . ' min)', 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  public static function endBreak(int $uid): void { Cache::forget('staff.idle.break.' . $uid); self::work($uid); }

  /** Work that needs a person right now: [chats that need a human, new orders older than 10 minutes]. Cached 30 s for everybody. */
  public static function waiting(): array {
    return Cache::remember('staff.idle.waiting', 30, function () {
      $c = 0; $o = 0;
      try { $c = (int)Chat::humanScope(DB::table('chat_threads'))->count(); } catch (\Throwable $e) {}
      try { $o = (int)DB::table('orders')->where('status', 'New')->where('created_at', '<', now()->subMinutes(10))->where('created_at', '>', now()->subDays(2))->count(); } catch (\Throwable $e) {}
      return [$c, $o];
    });
  }
  public static function open(int $uid): ?object { try { return DB::table('staff_idle_notices')->where('user_id', $uid)->where('status', 'open')->orderByDesc('id')->first(); } catch (\Throwable $e) { return null; } }

  /** Current state for one signed-in staff member. Creates the notice when the limit is crossed. */
  public static function state($u): array {
    $off = ['on' => false, 'lock' => false, 'warn' => false, 'idle' => 0];
    try {
      if (!self::on() || !self::applies($u)) return $off;
      $uid = (int)$u->id;
      if (self::open($uid)) return ['on' => true, 'lock' => true, 'warn' => false, 'idle' => 0];
      if (!self::dutyNow()) return $off;
      self::seen($uid);
      if ($b = self::onBreak($uid)) return ['on' => true, 'lock' => false, 'warn' => false, 'idle' => 0, 'break' => self::BREAKS[$b['kind']] ?? '', 'break_left' => (int)ceil(($b['until'] - time()) / 60)];
      $w = (int)Cache::get(self::k('w', $uid), time()); $idle = (int)floor((time() - $w) / 60);
      [$chats, $orders] = self::waiting();
      // Waiting work only counts against people whose job it is: messages for those who may answer customers, new orders for those who may handle orders.
      $P = \App\Http\Middleware\ModulePermission::class;
      if (!$P::can($u, $P::moduleFor('admin/chat'), 'edit')) $chats = 0; if (!$P::can($u, 'orders', 'edit')) $orders = 0;
      $has = ($chats + $orders) > 0 || self::set('need_waiting', '1') !== '1';
      if ($idle >= self::limit() && $has) {
        DB::table('staff_idle_notices')->insert(['user_id' => $uid, 'user_name' => (string)($u->name ?? ''), 'idle_from' => date('Y-m-d H:i:s', $w), 'idle_minutes' => $idle, 'waiting_chats' => $chats, 'waiting_orders' => $orders, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        return ['on' => true, 'lock' => true, 'warn' => false, 'idle' => $idle];
      }
      return ['on' => true, 'lock' => false, 'warn' => $has && $idle >= self::warnAt(), 'idle' => $idle, 'left' => max(0, self::limit() - $idle), 'chats' => $chats, 'orders' => $orders];
    } catch (\Throwable $e) { return $off; }
  }

  /** The staff member's written reason: unlocks their screen and files the ticket with the owner. */
  public static function submit(int $uid, string $reason): bool {
    $n = self::open($uid); if (!$n) return false;
    DB::table('staff_idle_notices')->where('id', $n->id)->update(['status' => 'submitted', 'reason' => mb_substr($reason, 0, 1000), 'submitted_at' => now(), 'updated_at' => now()]);
    self::work($uid);
    try { DB::table('audit_logs')->insert(['user_id' => $uid, 'user_name' => (string)$n->user_name, 'action' => 'staff.idle_reason', 'subject' => 'Idle ' . $n->idle_minutes . ' min · reason: ' . mb_substr($reason, 0, 200), 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    try { DeployHealth::raise('staff_idle:' . $n->id, 'medium', 'Staff', 'কারণ দর্শানো: ' . $n->user_name . ' ' . $n->idle_minutes . ' মিনিট কাজ করেননি', 'কারণ: ' . mb_substr($reason, 0, 300), 'কাজ অপেক্ষায় ছিল (মেসেজ ' . $n->waiting_chats . ', অর্ডার ' . $n->waiting_orders . ') অথচ কোনো কাজ হয়নি।', 'Staff Idle পাতায় (/admin/staff-idle) দেখে গ্রহণ বা অগ্রহণ করুন।'); } catch (\Throwable $e) {}
    return true;
  }
  public static function review(int $id, string $verdict, string $note, string $by): void {
    DB::table('staff_idle_notices')->where('id', $id)->where('status', 'submitted')->update(['status' => 'reviewed', 'verdict' => $verdict === 'rejected' ? 'rejected' : 'accepted', 'admin_note' => mb_substr($note, 0, 400), 'reviewed_by' => $by, 'reviewed_at' => now(), 'updated_at' => now()]);
    try { DeployHealth::resolve('staff_idle:' . $id, $by); } catch (\Throwable $e) {}
  }
}
