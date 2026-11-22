<?php
namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Auto recovery (owner, 23 Nov 2026): when a background system stops or gets stuck, bring it back without waiting for a person.
 *
 * The container runs five shell loops (push-worker every 30 s, courier-sync / guard every 10 min, images every 2 min, sync-feed hourly).
 * Each loop already starts its command again after it ends, so the only ways a system stays down are:
 *   1. a command HANGS (for example an outside API never answers)      -> the watchdog kills that one PHP process; its loop starts a fresh one
 *   2. a loop itself is gone                                           -> a relief run is started from another loop or from an open admin screen
 *   3. one task of the worker throws every time                         -> counted; an Issues Center alert after 3 failures in a row, closed by itself when it works again
 *   4. one task hangs the worker again and again                        -> that task alone rests for 30 minutes so the other 50 keep running
 * The loops watch each other (worker watches the four others; guard and courier-sync watch the worker; the Live Dashboard watches all of them),
 * so no single stuck process can stop the watching. Every action is written to the recovery log and the audit trail. Nothing here touches orders, prices or customers.
 */
class AutoHeal {
  /** Seconds a single process may run before it is treated as stuck, and the artisan command that is its relief run. */
  const LOOPS = ['courier-sync' => 1500, 'guard' => 1500, 'images' => 1200, 'sync-feed' => 2700];
  /** Seconds between runs of each loop (how long without a heartbeat means "the loop itself is gone" = 3 missed runs, at least 15 min). */
  const EVERY = ['courier-sync' => 600, 'guard' => 600, 'images' => 120, 'sync-feed' => 3600];
  const WORKER_STUCK = 600;   // one worker task running longer than 10 minutes
  const WORKER_GONE = 600;    // no worker heartbeat and no worker process for 10 minutes
  const LABEL = ['push-worker' => 'ওয়ার্কার', 'courier-sync' => 'কুরিয়ার সিঙ্ক', 'guard' => 'গার্ড', 'images' => 'ছবি অপ্টিমাইজ', 'sync-feed' => 'প্রোডাক্ট ফিড'];

  private static array $failedNow = [];

  // ---------- heartbeat and markers ----------
  public static function beat(string $loop): void { try { Cache::put('heal.beat.' . $loop, time(), 604800); } catch (\Throwable $e) {} }

  /** Start of one worker task: remembers which task is running (so a hang can be named) and says whether the task is resting. */
  public static function begin(string $task): bool {
    try {
      $q = (int)Cache::get('heal.q.' . $task, 0); if ($q > time()) return false;
      Cache::put('heal.at', ['n' => $task, 't' => time()], 7200);
    } catch (\Throwable $e) {}
    return true;
  }
  public static function failed(string $task, string $msg): void { self::$failedNow[$task] = $msg; }

  /** End of one worker run: count tasks that failed again, alert after 3 in a row, close the alert when they work. */
  public static function endOfRun(): void {
    try {
      Cache::put('heal.at', ['n' => 'idle', 't' => time()], 7200);
      $st = Cache::get('heal.failing', []); if (!is_array($st)) $st = [];
      foreach (self::$failedNow as $task => $msg) {
        $n = (int)($st[$task]['n'] ?? 0) + 1; $st[$task] = ['n' => $n, 'm' => $msg, 'since' => $st[$task]['since'] ?? time()];
        if ($n === 3) {
          self::log('"' . $task . '" কাজটি পরপর ৩ বার ব্যর্থ, প্রতি চক্রে আবার চেষ্টা চলছে: ' . $msg, 'warn');
          try { DeployHealth::raise('task_fail_' . $task, 'warning', 'Worker', 'Background task "' . $task . '" keeps failing', 'It failed 3 runs in a row and is retried every run. Last error: ' . $msg, 'An outside service is down, a setting is missing, or a recent change broke this task.', 'Open Live Dashboard for the task, check the related settings page; it closes by itself when the task works again.'); } catch (\Throwable $e) {}
        }
      }
      foreach (array_keys($st) as $task) if (!isset(self::$failedNow[$task])) {
        if ((int)$st[$task]['n'] >= 3) { self::log('"' . $task . '" কাজটি আবার ঠিকভাবে চলছে', 'ok'); try { DeployHealth::resolve('task_fail_' . $task); } catch (\Throwable $e) {} }
        unset($st[$task]);
      }
      Cache::put('heal.failing', $st, 86400); self::$failedNow = [];
    } catch (\Throwable $e) {}
  }

  // ---------- recovery log ----------
  public static function log(string $msg, string $level = 'ok'): void {
    try {
      $a = Cache::get('heal.log', []); if (!is_array($a)) $a = [];
      array_unshift($a, ['t' => time(), 'm' => $msg, 'l' => $level]); Cache::put('heal.log', array_slice($a, 0, 40), 604800);
      $k = 'heal.n.' . now('Asia/Dhaka')->format('Ymd'); if (!Cache::add($k, 1, 172800)) Cache::increment($k);
    } catch (\Throwable $e) {}
    try { Deployer::audit('Auto Recovery', $msg, 'System (auto)', ['result' => $level]); } catch (\Throwable $e) {}
  }
  public static function summary(): array {
    try {
      $a = Cache::get('heal.log', []); $rest = [];
      $at = Cache::get('heal.at');
      return ['today' => (int)Cache::get('heal.n.' . now('Asia/Dhaka')->format('Ymd'), 0), 'log' => array_slice(is_array($a) ? $a : [], 0, 8), 'task' => is_array($at) ? $at['n'] : null];
    } catch (\Throwable $e) { return ['today' => 0, 'log' => [], 'task' => null]; }
  }
  public static function resting(string $task): bool { try { return (int)Cache::get('heal.q.' . $task, 0) > time(); } catch (\Throwable $e) { return false; } }

  // ---------- processes ----------
  /** PHP processes running "artisan mother:<cmd>" (never the shell wrappers, never this process): [pid => seconds running]. */
  public static function procs(string $cmd): array {
    $out = [];
    try {
      foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $f) {
        $pid = (int)basename(dirname($f)); if ($pid === getmypid()) continue;
        $c = @file_get_contents($f); if ($c === false || $c === '') continue;
        $argv = explode("\0", rtrim($c, "\0")); if (!preg_match('/^php/', basename($argv[0]))) continue;
        if (!in_array('mother:' . $cmd, $argv, true)) continue;
        $st = @filemtime('/proc/' . $pid); $out[$pid] = $st ? max(0, time() - $st) : 0;
      }
    } catch (\Throwable $e) {}
    return $out;
  }
  private static function kill(int $pid): bool {
    try { if (function_exists('posix_kill')) return @posix_kill($pid, 9); if (function_exists('exec')) { @exec('kill -9 ' . $pid); return true; } } catch (\Throwable $e) {}
    return false;
  }
  /** Start one detached run of a command (used from a web request, where running it in-line would hold the page). */
  private static function spawn(string $cmd): bool {
    try {
      if (!function_exists('exec')) return false;
      @exec('cd ' . escapeshellarg(base_path()) . ' && nohup php artisan mother:' . $cmd . ' > /dev/null 2>&1 &'); return true;
    } catch (\Throwable $e) { return false; }
  }

  // ---------- the watchdog ----------
  /**
   * $from: 'worker' (start of every worker run: watches the four other loops), 'cli' (guard / courier-sync: watch the worker),
   * 'web' (Live Dashboard refresh: watches everything). At most once every 50 seconds per caller kind.
   */
  public static function watchdog(string $from): void {
    try {
      if (!Cache::add('heal.wd.' . $from, 1, 50)) return;
      if ($from !== 'worker') self::watchWorker($from);
      if ($from !== 'cli') foreach (self::LOOPS as $loop => $max) self::watchLoop($loop, $max, $from);
    } catch (\Throwable $e) {}
  }

  private static function watchWorker(string $from): void {
    $at = Cache::get('heal.at'); $pids = self::procs('push-worker'); $beat = null; try { $beat = DeployHealth::beatAge(); } catch (\Throwable $e) {}
    if ($pids) {
      $oldest = max($pids); $task = is_array($at) ? (string)$at['n'] : ''; $taskAge = is_array($at) ? time() - (int)$at['t'] : null;
      $stuck = ($task !== '' && $task !== 'idle' && $taskAge !== null && $taskAge > self::WORKER_STUCK && $oldest > self::WORKER_STUCK) || $oldest > 3 * self::WORKER_STUCK;
      if (!$stuck) return;
      $killed = 0; foreach ($pids as $pid => $age) if ($age > self::WORKER_STUCK && self::kill($pid)) $killed++;
      if (!$killed) return;
      $name = $task !== '' && $task !== 'idle' ? $task : 'unknown';
      self::log('ওয়ার্কার "' . $name . '" কাজে ' . (int)floor(($taskAge ?? $oldest) / 60) . ' মিনিট আটকে ছিল। আটকে থাকা প্রক্রিয়া বন্ধ করা হয়েছে, ৩০ সেকেন্ডের মধ্যে নতুন করে চালু হবে।', 'warn');
      if ($name !== 'unknown') {
        $k = 'heal.hang.' . $name; if (!Cache::add($k, 1, 21600)) Cache::increment($k);
        if ((int)Cache::get($k, 0) >= 2) {
          Cache::put('heal.q.' . $name, time() + 1800, 1800); Cache::forget($k);
          self::log('"' . $name . '" কাজটি বারবার ওয়ার্কার আটকে দিচ্ছে, তাই ৩০ মিনিট বিরতিতে রাখা হলো। বাকি সব কাজ চলছে।', 'warn');
          try { DeployHealth::raise('task_hang_' . $name, 'high', 'Worker', 'Background task "' . $name . '" keeps hanging', 'It blocked the worker twice within 6 hours. It is skipped for 30 minutes so every other task keeps running, then tried again.', 'An outside service does not answer and the call has no time limit.', 'Check the service this task talks to. The task is retried automatically after the rest.'); } catch (\Throwable $e) {}
        }
      }
      Cache::put('heal.at', ['n' => 'idle', 't' => time()], 7200);
      return;
    }
    // no worker process at all: normal for the 30 s between runs; a problem only when the heartbeat is old too
    if ($beat === null || $beat <= self::WORKER_GONE) return;
    if (!Cache::add('heal.relief.push-worker', 1, 90)) return;
    if (Cache::add('heal.relief.note.push-worker', 1, 1800)) self::log('ওয়ার্কারের লুপ ' . (int)floor($beat / 60) . ' মিনিট ধরে চলছে না। বিকল্প পথে ওয়ার্কার চালানো শুরু হয়েছে (প্রতি দেড় মিনিটে একবার), আসল লুপ ফিরলে নিজে থেকে থামবে।', 'warn');
    if ($from === 'cli') { try { Artisan::call('mother:push-worker'); } catch (\Throwable $e) {} } else self::spawn('push-worker');
  }

  private static function watchLoop(string $loop, int $max, string $from): void {
    $pids = self::procs($loop);
    if ($pids) {
      $killed = 0; foreach ($pids as $pid => $age) if ($age > $max && self::kill($pid)) $killed++;
      if ($killed) self::log(self::LABEL[$loop] . ' ' . (int)floor(max($pids) / 60) . ' মিনিট আটকে ছিল। আটকে থাকা প্রক্রিয়া বন্ধ করা হয়েছে, পরের চক্রে নতুন করে চলবে।', 'warn');
      return;
    }
    $b = (int)Cache::get('heal.beat.' . $loop, 0); if (!$b) return; // no heartbeat recorded yet (first hours after this was installed): nothing to judge
    $gone = max(900, 3 * self::EVERY[$loop] + 300); $age = time() - $b; if ($age <= $gone) return;
    if (!Cache::add('heal.relief.' . $loop, 1, max(300, self::EVERY[$loop]))) return;
    if (Cache::add('heal.relief.note.' . $loop, 1, 3600)) self::log(self::LABEL[$loop] . ' লুপ ' . (int)floor($age / 60) . ' মিনিট ধরে চলছে না। বিকল্প পথে চালানো শুরু হয়েছে।', 'warn');
    if ($from === 'worker') { try { Artisan::call('mother:' . $loop); } catch (\Throwable $e) {} } else self::spawn($loop);
  }
}
