<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Automatic Deployment Center: watches the production branch, queues approved releases for deploy.sh, and tracks their progress. */
class Deployer
{
  const D = '/srv/deploy';
  const REPO = 'modinalink-commits/dutyfreeshopbd';
  const ACTIVE = ['Queued', 'Downloading', 'Building', 'Testing', 'Deploying'];
  const STAGES = ['Downloading', 'Building', 'Testing', 'Deploying', 'Verifying'];

  public static function enabled(): bool { return is_link('/srv/app') && is_dir(self::D); }
  public static function liveId(): string { return basename((string)realpath('/srv/app')); }
  /** True while deploy.sh is really running (it holds an flock on /srv/deploy/lock) - the reliable "heartbeat". */
  public static function lockHeld(): bool {
    $f = self::D . '/lock'; if (!is_file($f)) return false;
    $h = @fopen($f, 'c'); if (!$h) return false;
    $got = @flock($h, LOCK_EX | LOCK_NB); if ($got) flock($h, LOCK_UN); fclose($h);
    return !$got;
  }
  /** True when a deploy.sh process is really running right now (the lock can be free for a moment between the request being picked up and the lock being taken, e.g. while code is fetched). Unknown = false. */
  public static function procAlive(): bool {
    try { if (!function_exists('exec')) return false; $o = []; @exec("pgrep -f '/srv/deploy/deploy\\.sh|deploy\\.sh' 2>/dev/null", $o); return count(array_filter($o, fn($x) => (int)$x > 0 && (int)$x !== getmypid())) > 0; } catch (\Throwable $e) { return false; }
  }
  /** Previous stable release kept by deploy.sh (the target of Rollback), with an integrity check. */
  public static function previous(): ?array {
    $p = self::rd('previous'); if ($p === '' || !is_dir($p)) return null;
    $id = basename($p); $row = DB::table('deployments')->where('release_id', $id)->orderByDesc('id')->first();
    return ['id' => $id, 'row' => $row, 'valid' => is_file($p . '/artisan') && is_file($p . '/vendor/autoload.php') && is_file($p . '/RELEASE')];
  }
  /** Quick / Standard from what the engine really did (it skips composer and migrations on its own). */
  public static function guessType(?string $log): ?string {
    if (!$log) return null; $skipC = str_contains($log, 'SKIPPED (no composer'); $skipM = str_contains($log, 'migration check: SKIPPED');
    return ($skipC && $skipM) ? 'quick' : 'standard';
  }
  /** Quick Deploy is only offered when GitHub says the change is views/CSS/JS/PHP code: no migration, no composer change. */
  public static function quickCheck(string $sha): array {
    $base = DB::table('deployments')->where('release_id', self::liveId())->whereNotNull('sha')->orderByDesc('id')->value('sha');
    if (!$base) return [false, 'Quick Deploy needs the live commit to compare against - use Standard Deploy.'];
    if ($base === $sha) return [true, 'Same commit as live'];
    try {
      $r = Http::withHeaders(self::gh())->timeout(10)->get('https://api.github.com/repos/' . self::REPO . '/compare/' . $base . '...' . $sha);
      if (!$r->ok()) return [false, 'Could not compare the changes with GitHub - use Standard Deploy.'];
      $files = $r->json('files') ?? []; if (count($files) >= 300) return [false, 'Too many changed files for Quick Deploy - use Standard Deploy.'];
      foreach ($files as $f) { $n = (string)($f['filename'] ?? ''); if (preg_match('#database/migrations/|composer\.(json|lock)$#', $n)) return [false, 'This update contains ' . (str_contains($n, 'migrations') ? 'a database migration' : 'a dependency change') . ' (' . basename($n) . ') - use Standard Deploy.']; }
      return [true, count($files) . ' changed files, no migration or dependency change'];
    } catch (\Throwable $e) { return [false, 'Could not compare the changes with GitHub - use Standard Deploy.']; }
  }
  /** Changed file names + message of one commit (never file contents, never .env). */
  public static function commitFiles(string $sha): ?array {
    return \Cache::remember('dh.changes.' . $sha, 86400, function () use ($sha) {
      try {
        $r = Http::withHeaders(self::gh())->timeout(10)->get('https://api.github.com/repos/' . self::REPO . '/commits/' . $sha); if (!$r->ok()) return null; $j = $r->json();
        $files = collect($j['files'] ?? [])->map(fn($f) => ['name' => (string)$f['filename'], 'status' => (string)($f['status'] ?? ''), 'add' => (int)($f['additions'] ?? 0), 'del' => (int)($f['deletions'] ?? 0)])->reject(fn($f) => preg_match('/(^|\/)\.env/', $f['name']))->values()->take(120)->all();
        return ['sha' => (string)$j['sha'], 'message' => mb_substr((string)($j['commit']['message'] ?? ''), 0, 1200), 'author' => (string)($j['commit']['author']['name'] ?? ''), 'date' => (string)($j['commit']['committer']['date'] ?? ''), 'branch' => self::branch(), 'files' => $files, 'total' => count($j['files'] ?? [])];
      } catch (\Throwable $e) { return null; }
    });
  }
  /** Remove secrets before any log leaves the server. */
  public static function clean(string $t): string {
    $t = preg_replace('/(ghp_|gho_|github_pat_|sk-ant-|sk-)[A-Za-z0-9_\-]{12,}/', '[hidden]', $t);
    $t = preg_replace('/(authorization|bearer)\s*[:=]?\s*\S+/i', '$1 [hidden]', $t);
    return (string)preg_replace('/\b(password|passwd|pwd|secret|token|api[_-]?key|app_key|db_password)\b\s*[=:]\s*\S+/i', '$1=[hidden]', $t);
  }
  /** One-click Recovery Check: looks, and only clears stale deployment state (never deletes a release, never restarts anything). */
  public static function recover(): array {
    $o = []; $add = function (string $n, string $st, string $t) use (&$o) { $o[] = ['name' => $n, 'state' => $st, 'text' => $t]; };
    $live = self::liveId(); $ok = self::enabled() && is_file(base_path('artisan')) && is_file(base_path('vendor/autoload.php'));
    $add('Current release', $ok ? 'ok' : 'fail', $ok ? 'Valid: ' . $live : 'Incomplete: ' . $live . ' (use Rollback)');
    $held = self::lockHeld(); $act = DB::table('deployments')->whereIn('status', self::ACTIVE)->latest('id')->first(); $req = self::D . '/request';
    if ($held) $add('Deployment lock', 'ok', 'A deployment is really running - nothing touched');
    else {
      $cleared = [];
      if (is_file($req) && time() - (int)@filemtime($req) > 600) { @unlink($req); $cleared[] = 'stale request file'; }
      if ($act && !is_file($req) && time() - max((int)@filemtime(self::D . '/stage'), (int)@filemtime(self::D . '/status'), strtotime((string)$act->updated_at . ' UTC')) > 120 && !str_starts_with(self::rd('status'), 'ok:') && !self::procAlive()) {
        DB::table('deployments')->where('id', $act->id)->update(['status' => 'Failed', 'stage' => 'Failed', 'error' => 'Interrupted: the deployment process is no longer running (server restart or crash). The last healthy release stayed active.', 'finished_at' => now(), 'updated_at' => now()]);
        DeployHealth::raise('deploy_failed:' . $act->id, 'high', 'Deployment', 'Deployment was interrupted', 'The process stopped before it finished. Production kept the last healthy release.', 'Server or container restart during the deployment.', 'Review the log, then retry the deployment.', (int)$act->id);
        $cleared[] = 'interrupted deployment #' . $act->id;
      }
      $add('Deployment lock', 'ok', $cleared ? 'Cleared: ' . implode(', ', $cleared) : 'Free - no stale state found');
    }
    $l = DeployHealth::light(true); foreach ($l['cells'] as $c) if (in_array($c['key'], ['website', 'database', 'containers', 'scheduler'], true)) $add($c['label'], $c['state'], $c['text'] . ($c['detail'] ? ' · ' . $c['detail'] : ''));
    $q = DeployHealth::queueInfo(); $add('Queue', $q['failed'] ? 'warn' : 'ok', ($q['driver'] === 'sync' ? 'Sync mode' : ($q['pending'] ?? 0) . ' pending') . ' · ' . $q['failed'] . ' failed');
    return $o;
  }

  private static function rd(string $f): string { return is_file(self::D . '/' . $f) ? trim(mb_scrub((string)@file_get_contents(self::D . '/' . $f), 'UTF-8')) : ''; } // a log line cut in the middle of a Bengali letter must never reach the database as broken bytes

  public static function setting(string $k, string $def = ''): string { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function mode(): string { return self::setting('deploy_mode', 'auto') === 'hold' ? 'hold' : 'auto'; }
  public static function branch(): string { $b = self::setting('deploy_branch', 'main'); return preg_match('/^[A-Za-z0-9._\/-]{1,60}$/', $b) ? $b : 'main'; }

  /** GitHub API headers; an optional read-only token (saved in Automation settings) lifts the anonymous 60-requests/hour limit. */
  private static function gh(array $h = []): array {
    $h += ['User-Agent' => 'mother-deploy', 'Accept' => 'application/vnd.github+json'];
    try { $t = \App\Support\Sms::secret('deploy_github_token'); if ($t) $h['Authorization'] = 'Bearer ' . $t; } catch (\Throwable $e) {}
    return $h;
  }
  /** Latest commit on the production branch (cached one minute). */
  public static function head(bool $fresh = false): ?array {
    $key = 'deploy_head_' . self::branch();
    if (!$fresh && ($c = \Cache::get($key))) return $c;
    try {
      $r = Http::withHeaders(self::gh())->timeout(8)->get('https://api.github.com/repos/' . self::REPO . '/commits/' . self::branch());
      if (!$r->ok()) return \Cache::get($key . '_last');
      $j = $r->json(); [$subj, $notes] = self::parse((string)($j['commit']['message'] ?? ''));
      $h = ['sha' => (string)$j['sha'], 'subject' => $subj, 'notes' => $notes, 'at' => (string)($j['commit']['committer']['date'] ?? '')];
      \Cache::put($key, $h, 60); \Cache::forever($key . '_last', $h); return $h;
    } catch (\Throwable $e) { return \Cache::get($key . '_last'); }
  }

  /** Commits on the production branch that are newer than the live release (pushed, not live yet), newest first. Cached 45 s. */
  public static function pendingCommits(?string $liveSha, ?string $headSha): array {
    if (!$liveSha || !$headSha || $liveSha === $headSha) return [];
    $key = 'deploy_pend_' . substr($liveSha, 0, 12) . '_' . substr($headSha, 0, 12);
    $c = \Cache::get($key); if (is_array($c)) return $c;
    try {
      $r = Http::withHeaders(self::gh())->timeout(8)->get('https://api.github.com/repos/' . self::REPO . '/compare/' . $liveSha . '...' . $headSha);
      if (!$r->ok()) return [];
      $out = [];
      foreach (array_slice(array_reverse((array)($r->json()['commits'] ?? [])), 0, 30) as $j) { [$subj] = self::parse((string)($j['commit']['message'] ?? '')); $out[] = ['sha' => (string)($j['sha'] ?? ''), 'subject' => $subj, 'at' => (string)($j['commit']['committer']['date'] ?? '')]; }
      \Cache::put($key, $out, 45); return $out;
    } catch (\Throwable $e) { return []; }
  }

  public static function commit(string $sha): ?array {
    try {
      $r = Http::withHeaders(self::gh())->timeout(8)->get('https://api.github.com/repos/' . self::REPO . '/commits/' . $sha);
      if (!$r->ok()) return null; $j = $r->json(); [$s, $n] = self::parse((string)($j['commit']['message'] ?? ''));
      return ['sha' => (string)$j['sha'], 'subject' => $s, 'notes' => $n];
    } catch (\Throwable $e) { return null; }
  }

  /** Commit message -> subject (first line, or a "Subject:" line) and bullet notes. */
  public static function parse(string $msg): array {
    // Split on real line breaks only and match in UTF-8 mode: without /u the byte 0x85 inside Bengali letters is taken for a line break and cuts the text mid-letter.
    $msg = mb_scrub($msg, 'UTF-8'); $lines = preg_split('/\r\n|\r|\n/', trim($msg)); $subj = trim((string)array_shift($lines)); $notes = [];
    foreach ($lines as $l) { $l = trim($l); if ($l === '' || preg_match('/^(Co-Authored-By|Claude-Session|Signed-off-by)/i', $l)) continue; if (preg_match('/^subject:\s*(.+)$/iu', $l, $m)) { $subj = trim($m[1]); continue; } $notes[] = (string)preg_replace('/^[\-\*•\s]+/u', '', $l); }
    return [mb_substr($subj, 0, 240), implode("\n", $notes)];
  }

  public static function audit(string $action, string $subject, ?string $by = null, array $meta = []): void {
    try {
      $u = function_exists('auth') ? optional(auth()->user()) : null; $ip = null; try { $ip = request()->ip(); } catch (\Throwable $e) {}
      DB::table('audit_logs')->insert(['user_id' => $u->id ?? null, 'user_name' => $by ?: ($u->name ?? 'Deployment'), 'action' => $action, 'subject' => mb_substr($subject, 0, 240), 'meta' => $meta ? json_encode($meta) : null, 'ip' => $ip, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {}
  }

  /** Queue a release for deploy.sh. Returns [ok, message, id]. meta: subject/notes (commit), dtype (quick|standard|full|rollback), note (deployment note) or reason (rollback). */
  public static function queue(string $sha, string $source, array $meta = [], bool $breaking = false, ?string $by = null): array {
    if (!self::enabled()) return [false, 'Release layout is not active', 0];
    if (self::lockHeld() || is_file(self::D . '/request') || DB::table('deployments')->whereIn('status', self::ACTIVE)->exists()) return [false, 'Deployment currently in progress.', 0];
    $rollback = $sha === 'rollback';
    if (!$rollback && !preg_match('/^[0-9a-f]{40}$/', $sha)) return [false, 'Invalid commit id', 0];
    $prev = null;
    if ($rollback) { $prev = self::previous(); if (!$prev || !$prev['valid']) return [false, 'The previous release is missing or incomplete - rollback is not safe.', 0]; }
    $info = $rollback ? null : (($meta['subject'] ?? null) ? $meta : (self::commit($sha) ?: []));
    $dtype = $rollback ? 'rollback' : (in_array($meta['dtype'] ?? '', ['quick', 'standard', 'full'], true) ? $meta['dtype'] : null);
    $desc = $rollback ? ('Reason: ' . mb_substr((string)($meta['reason'] ?? ''), 0, 400)) : (($meta['note'] ?? '') !== '' ? mb_substr((string)$meta['note'], 0, 1000) : null);
    $subject = $rollback ? 'Rollback to ' . ($prev['row']->version ?? $prev['id']) : ($info['subject'] ?? 'Update ' . substr($sha, 0, 7));
    $row = ['sha' => $rollback ? null : $sha, 'subject' => $subject, 'description' => $desc, 'notes' => $info['notes'] ?? null,
      'source' => $source, 'status' => 'Queued', 'progress' => 0, 'stage' => 'Queued', 'from_release' => self::liveId(), 'breaking' => $breaking, 'requested_by' => $by, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()];
    if (\Illuminate\Support\Facades\Schema::hasColumn('deployments', 'dtype')) $row['dtype'] = $dtype;
    $id = DB::table('deployments')->insertGetId($row);
    DB::table('deployments')->where('id', $id)->update(['version' => 'v1.' . $id]);
    @file_put_contents(self::D . '/status', 'queued'); @chmod(self::D . '/status', 0666);
    file_put_contents(self::D . '/request', $rollback ? 'rollback' : $sha . ($breaking ? ' allow-breaking' : ''));
    self::audit($rollback ? 'Rollback Started' : 'Deployment Started', ($rollback ? $subject . ' · ' . ($meta['reason'] ?? '') : $subject . ' (' . substr($sha, 0, 7) . ', ' . ($dtype ?: $source) . ')'), $by, ['old' => self::liveId(), 'new' => $rollback ? $prev['id'] : substr($sha, 0, 7), 'result' => 'queued', 'deployment' => $id]);
    return [true, 'Queued', $id];
  }

  public static function cancel(): bool {
    $row = DB::table('deployments')->where('status', 'Queued')->latest('id')->first();
    if (!$row || !is_file(self::D . '/request')) return false;
    @unlink(self::D . '/request');
    DB::table('deployments')->where('id', $row->id)->update(['status' => 'Failed', 'stage' => 'Cancelled', 'error' => 'Cancelled by admin before it started', 'finished_at' => now(), 'updated_at' => now()]);
    self::audit('Deployment Cancelled', (string)$row->subject); return true;
  }

  /** Move the active deployment forward by reading what deploy.sh wrote. Safe to call often. */
  public static function reconcile(): void {
    $row = DB::table('deployments')->whereIn('status', self::ACTIVE)->latest('id')->first();
    if (!$row) { // a deployment wrongly marked Interrupted that then finished and is the live release: correct the record
      try {
        $bad = DB::table('deployments')->where('status', 'Failed')->where('error', 'like', 'Interrupted:%')->where('finished_at', '>', now()->subHours(2))->latest('id')->first();
        if ($bad && $bad->sha && str_contains((string)self::liveId(), substr($bad->sha, 0, 7))) {
          DB::table('deployments')->where('id', $bad->id)->update(['status' => 'Completed', 'stage' => 'Completed', 'progress' => 100, 'error' => null, 'release_id' => self::liveId(), 'updated_at' => now()]);
          DeployHealth::resolve('deploy_failed:', 'System (release is live)', true);
        }
      } catch (\Throwable $e) {}
      return;
    }
    $st = self::rd('status'); $mt = (int)@filemtime(self::D . '/status'); $since = strtotime((string)$row->created_at . ' UTC') - 5;
    $fresh = $mt >= $since; $sha7 = $row->sha ? substr($row->sha, 0, 7) : null; $log = self::rd('last.log');
    $u = ['updated_at' => now()];
    if (is_file(self::D . '/request')) { $u += ['status' => 'Queued', 'stage' => 'Queued', 'progress' => 0]; }
    elseif ($fresh && str_starts_with($st, 'ok:') && ($sha7 ? str_contains($st, $sha7) : str_contains($st, 'rolled back'))) {
      $rb = !$row->sha; $rid = $rb ? null : trim(substr($st, 3), ' ') ; $rid = $rid ? preg_replace('/ live$/', '', $rid) : null;
      $u += ['status' => $rb ? 'Rolled Back' : 'Completed', 'stage' => $rb ? 'Rolled back' : 'Completed', 'progress' => 100, 'release_id' => $rid, 'log' => mb_substr($log, -9000), 'finished_at' => now()];
      if ($rb && $row->from_release) DB::table('deployments')->where('release_id', $row->from_release)->where('id', '<>', $row->id)->update(['status' => 'Rolled Back']);
      if (!$rb && \Illuminate\Support\Facades\Schema::hasColumn('deployments', 'dtype') && !$row->dtype) $u['dtype'] = self::guessType($log);
      self::audit($rb ? 'Rollback Completed' : 'Deployment Completed', ($row->subject ?? '') . ' — now LIVE', null, ['old' => $row->from_release, 'new' => $rid, 'result' => 'success', 'deployment' => $row->id]);
      if (!$rb) DeployHealth::resolve('deploy_failed:', 'System (new release is live)', true);
      if (function_exists('opcache_reset')) @opcache_reset(); // the old release's scripts would otherwise stay in PHP's code cache until it fills up and the site slows down
    } elseif ($fresh && str_starts_with($st, 'failed:')) {
      $rolled = str_contains($st, 'rolled back');
      $u += ['status' => $rolled ? 'Rolled Back' : 'Failed', 'stage' => $rolled ? 'Rolled back' : 'Failed', 'error' => trim(substr($st, 7)), 'log' => mb_substr($log, -9000), 'finished_at' => now()];
      self::audit($rolled ? 'Rollback Completed' : 'Deployment Failed', ($row->subject ?? '') . ' — previous version kept', null, ['old' => $row->from_release, 'new' => $row->from_release, 'result' => trim(substr($st, 7)), 'deployment' => $row->id]);
      DeployHealth::raise('deploy_failed:' . $row->id, 'high', 'Deployment', $rolled ? 'Deployment failed - automatic rollback completed' : 'Deployment failed - previous release stays live', trim(substr($st, 7)), $rolled ? 'The new release passed the pre-check but failed the live health check.' : 'A build, migration or health check step failed before the switch.', 'Review the log, fix the cause, then retry. Production was not affected.', (int)$row->id);
    } elseif ($fresh && $row->status !== 'Queued' && !self::lockHeld() && !self::procAlive() && time() - max((int)@filemtime(self::D . '/stage'), (int)@filemtime(self::D . '/status'), (int)@filemtime(self::D . '/last.log')) > 180) {
      $u += ['status' => 'Failed', 'stage' => 'Failed', 'error' => 'Interrupted: the deployment process is no longer running (server restart or crash). The last healthy release stayed active. Retry when ready.', 'finished_at' => now()];
      self::audit('Deployment Failed', 'Interrupted: ' . $row->subject, null, ['result' => 'interrupted', 'deployment' => $row->id]);
      DeployHealth::raise('deploy_failed:' . $row->id, 'high', 'Deployment', 'Deployment was interrupted', 'The process stopped before it finished. Production kept the last healthy release.', 'Server or container restart during the deployment.', 'Review the log, then retry the deployment.', (int)$row->id);
    } elseif ($fresh && $st !== '') {
      $sf = self::D . '/stage'; $stale = is_file($sf) && filemtime($sf) < strtotime((string)$row->created_at . ' UTC') - 5;
      [$stage, $pct] = $stale ? ['', '0'] : array_pad(explode('|', self::rd('stage')), 2, '0');
      $map = ['Downloading' => 'Downloading', 'Building' => 'Building', 'Testing' => 'Testing', 'Deploying' => 'Deploying', 'Verifying' => 'Deploying', 'Rolling back' => 'Deploying'];
      if (isset($map[$stage])) $u += ['status' => $map[$stage], 'stage' => $stage, 'progress' => min(99, max((int)$row->progress, (int)$pct))];
      
    } elseif (!$fresh && $row->status !== 'Queued' && !self::lockHeld() && time() - strtotime((string)$row->updated_at . ' UTC') > 600) {
      $u += ['status' => 'Failed', 'stage' => 'Failed', 'error' => 'No heartbeat for 10 minutes: marked interrupted. The last healthy release stayed active.', 'finished_at' => now()];
    } elseif (!$fresh && time() - strtotime((string)$row->created_at . ' UTC') > 120 && !is_file(self::D . '/request')) {
      $u += ['status' => 'Failed', 'stage' => 'Failed', 'error' => 'The deploy worker did not pick this up', 'finished_at' => now()];
    }
    if (count($u) > 1) {
      DB::table('deployments')->where('id', $row->id)->update($u);
    }
  }

  /** One-time: record the releases that already exist so the history is not empty. */
  private static function backfill(): void {
    if (DB::table('deployments')->exists()) return;
    $dirs = glob('/srv/releases/*', GLOB_ONLYDIR) ?: []; usort($dirs, fn($a, $b) => filemtime($a) <=> filemtime($b));
    foreach ($dirs as $d) {
      $id = basename($d); if (!preg_match('/-([0-9a-f]{7})$/', $id, $m)) continue;
      $c = self::commit($m[1]); $sha = $c['sha'] ?? null; $at = date('Y-m-d H:i:s', filemtime($d));
      $new = DB::table('deployments')->insertGetId(['sha' => $sha, 'subject' => $c['subject'] ?? 'Release ' . $m[1], 'notes' => $c['notes'] ?? null, 'source' => 'manual', 'status' => 'Completed', 'progress' => 100, 'stage' => 'Completed', 'release_id' => $id, 'started_at' => $at, 'finished_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
      DB::table('deployments')->where('id', $new)->update(['version' => 'v1.' . $new]);
    }
  }

  /** Called every ~30 s by the background worker: track progress and pick up new approved commits. */
  public static function tick(bool $force = false): void {
    if (!self::enabled()) return;
    try { self::reconcile(); } catch (\Throwable $e) {}
    $f = self::D . '/sync_at'; if (!$force && is_file($f) && time() - (int)@filemtime($f) < 90) return;
    @touch($f); @chmod($f, 0666);
    self::backfill();
    if (self::mode() === 'hold') return; // held: the admin presses Deploy Now
    if (is_file(self::D . '/request') || DB::table('deployments')->whereIn('status', self::ACTIVE)->exists()) return;
    $h = self::head($force); if (!$h) return;
    if (DB::table('deployments')->where('sha', $h['sha'])->exists()) return; // already deployed / tried: Retry is manual
    if (preg_match('/\[skip deploy\]|^wip\b/i', $h['subject'])) return;
    self::queue($h['sha'], 'auto', $h);
  }
}
