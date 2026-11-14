<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live presence for the Live Staff Board (owner, 14 Nov 2026): who has the admin open right now, which page, when they last did real work,
 * and how long their screen has been connected today. Kept in the cache only (no table, nothing to migrate); read-only for everyone.
 */
class StaffLive {
  private static function day(): string { return now('Asia/Dhaka')->format('Ymd'); }
  private static function label(string $p): string {
    $p = trim(preg_replace('#^admin/?#', '', $p), '/'); if ($p === '') return 'Dashboard';
    $map = ['orders' => 'Orders', 'chat' => 'Messages', 'products' => 'Products', 'customers' => 'Customers', 'call-center' => 'Call Center', 'purchases' => 'Purchases', 'inventory' => 'Inventory', 'my-day' => 'My Day', 'analytics' => 'Analytics', 'courier' => 'Courier', 'payments' => 'Payments', 'staff-log' => 'Live Staff Board'];
    $a = explode('/', $p); $h = $map[$a[0]] ?? ucwords(str_replace('-', ' ', $a[0]));
    if ($a[0] === 'orders' && isset($a[1]) && ctype_digit($a[1])) return 'Order #' . $a[1];
    return $h . (isset($a[1]) && !ctype_digit($a[1]) ? ' › ' . ucwords(str_replace('-', ' ', $a[1])) : '');
  }
  /** Called on every signed-in admin request. Cheap: at most one small cache write every 20 seconds per person, plus one when a page is opened. */
  public static function touch($u, Request $r): void {
    try {
      $uid = (int)$u->id; $now = time(); $k = 'staff.live.' . $uid; $s = Cache::get($k); if (!is_array($s)) $s = ['seen' => 0, 'page' => '', 'page_at' => 0, 'first' => 0, 'day' => '', 'active' => 0];
      $d = self::day(); if (($s['day'] ?? '') !== $d) { $s['day'] = $d; $s['active'] = 0; $s['first'] = $now; }
      $bg = !$r->isMethod('get') || $r->ajax() || $r->expectsJson() || preg_match('#(poll|typing|ping|seen|heartbeat|my-status|presence|/live|/alerts|/notices|ai-status|/status|/count)$#', $r->path());
      $isPage = !$bg; if (!$isPage && $now - (int)$s['seen'] < 20) return;
      $gap = $now - (int)$s['seen']; if ($s['seen'] && $gap > 0 && $gap <= 120) $s['active'] = (int)$s['active'] + $gap;
      $s['seen'] = $now; if ($isPage) { $s['page'] = self::label($r->path()); $s['page_at'] = $now; }
      Cache::put($k, $s, 172800);
    } catch (\Throwable $e) {}
  }
  /** One row per active user for the board. */
  public static function rows(): array {
    $now = time(); $d0 = now('Asia/Dhaka')->startOfDay(); $out = [];
    $wait = []; try { $wait = DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->whereNotNull('assigned_to')->where('last_at', '>=', now()->subHours(22))->groupBy('assigned_to')->selectRaw('assigned_to, count(*) n')->pluck('n', 'assigned_to')->all(); } catch (\Throwable $e) {}
    $unassigned = 0; try { $unassigned = (int)DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->whereNull('assigned_to')->where('last_at', '>=', now()->subHours(22))->count(); } catch (\Throwable $e) {}
    foreach (DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name', 'role']) as $u) {
      $s = Cache::get('staff.live.' . $u->id); $today = is_array($s) && ($s['day'] ?? '') === self::day();
      $seen = is_array($s) ? (int)($s['seen'] ?? 0) : 0; $ago = $seen ? $now - $seen : null;
      $state = $ago === null || !$today ? 'Offline' : ($ago <= 90 ? 'Online' : ($ago <= 600 ? 'Away' : 'Offline'));
      $work = 0; try { $work = (int)Cache::get('staff.idle.w.' . $u->id . '.' . self::day(), 0); } catch (\Throwable $e) {}
      $brk = null; try { $brk = StaffIdle::onBreak((int)$u->id); } catch (\Throwable $e) {}
      $st = ['chats' => 0, 'replies' => 0, 'calls' => 0, 'orders' => 0, 'late' => 0];
      try { $st = \App\Http\Controllers\StaffDayController::stats($u, $d0->copy()->utc(), $d0->copy()->addDay()->utc()); } catch (\Throwable $e) {}
      $call = 'available'; try { $call = \App\Http\Controllers\CallCenterController::agentStatus((int)$u->id); } catch (\Throwable $e) {}
      $out[] = ['id' => (int)$u->id, 'name' => $u->name, 'role' => $u->role, 'state' => $brk ? 'On break' : $state, 'page' => $today && $state !== 'Offline' ? (string)($s['page'] ?? '') : '', 'page_min' => $today && !empty($s['page_at']) ? (int)floor(($now - (int)$s['page_at']) / 60) : null,
        'seen_min' => $ago === null ? null : (int)floor($ago / 60), 'work_min' => $work ? (int)floor(($now - $work) / 60) : null, 'first' => $today && !empty($s['first']) ? \Carbon\Carbon::createFromTimestamp((int)$s['first'], 'Asia/Dhaka')->format('h:i A') : null,
        'active_min' => $today ? (int)floor((int)($s['active'] ?? 0) / 60) : 0, 'waiting' => (int)($wait[$u->id] ?? 0), 'call' => $call, 'chats' => (int)$st['chats'], 'calls' => (int)$st['calls'], 'orders' => (int)$st['orders'], 'late' => (int)$st['late']];
    }
    usort($out, fn($a, $b) => [array_search($a['state'], ['Online', 'On break', 'Away', 'Offline']), $a['name']] <=> [array_search($b['state'], ['Online', 'On break', 'Away', 'Offline']), $b['name']]);
    return ['rows' => $out, 'unassigned' => $unassigned, 'at' => now('Asia/Dhaka')->format('h:i:s A')];
  }
}
