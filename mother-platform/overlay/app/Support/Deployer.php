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
  private static function rd(string $f): string { return is_file(self::D . '/' . $f) ? trim((string)@file_get_contents(self::D . '/' . $f)) : ''; }

  public static function setting(string $k, string $def = ''): string { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null ? $def : (string)$v; }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function mode(): string { return self::setting('deploy_mode', 'auto') === 'hold' ? 'hold' : 'auto'; }
  public static function branch(): string { $b = self::setting('deploy_branch', 'main'); return preg_match('/^[A-Za-z0-9._\/-]{1,60}$/', $b) ? $b : 'main'; }

  /** Latest commit on the production branch (cached one minute). */
  public static function head(bool $fresh = false): ?array {
    $key = 'deploy_head_' . self::branch();
    if (!$fresh && ($c = \Cache::get($key))) return $c;
    try {
      $r = Http::withHeaders(['User-Agent' => 'mother-deploy', 'Accept' => 'application/vnd.github+json'])->timeout(8)->get('https://api.github.com/repos/' . self::REPO . '/commits/' . self::branch());
      if (!$r->ok()) return \Cache::get($key . '_last');
      $j = $r->json(); [$subj, $notes] = self::parse((string)($j['commit']['message'] ?? ''));
      $h = ['sha' => (string)$j['sha'], 'subject' => $subj, 'notes' => $notes, 'at' => (string)($j['commit']['committer']['date'] ?? '')];
      \Cache::put($key, $h, 60); \Cache::forever($key . '_last', $h); return $h;
    } catch (\Throwable $e) { return \Cache::get($key . '_last'); }
  }

  public static function commit(string $sha): ?array {
    try {
      $r = Http::withHeaders(['User-Agent' => 'mother-deploy'])->timeout(8)->get('https://api.github.com/repos/' . self::REPO . '/commits/' . $sha);
      if (!$r->ok()) return null; $j = $r->json(); [$s, $n] = self::parse((string)($j['commit']['message'] ?? ''));
      return ['sha' => (string)$j['sha'], 'subject' => $s, 'notes' => $n];
    } catch (\Throwable $e) { return null; }
  }

  /** Commit message -> subject (first line, or a "Subject:" line) and bullet notes. */
  public static function parse(string $msg): array {
    $lines = preg_split('/\R/', trim($msg)); $subj = trim((string)array_shift($lines)); $notes = [];
    foreach ($lines as $l) { $l = trim($l); if ($l === '' || preg_match('/^(Co-Authored-By|Claude-Session|Signed-off-by)/i', $l)) continue; if (preg_match('/^subject:\s*(.+)$/i', $l, $m)) { $subj = trim($m[1]); continue; } $notes[] = ltrim($l, "-*• "); }
    return [mb_substr($subj, 0, 240), implode("\n", $notes)];
  }

  private static function audit(string $action, string $subject): void {
    try { DB::table('audit_logs')->insert(['user_name' => 'Deployment', 'action' => $action, 'subject' => mb_substr($subject, 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }

  /** Queue a release for deploy.sh. Returns [ok, message, id]. */
  public static function queue(string $sha, string $source, array $meta = [], bool $breaking = false, ?string $by = null): array {
    if (!self::enabled()) return [false, 'Release layout is not active', 0];
    if (is_file(self::D . '/request') || DB::table('deployments')->whereIn('status', self::ACTIVE)->exists()) return [false, 'Another deployment is already running or waiting', 0];
    $rollback = $sha === 'rollback';
    if (!$rollback && !preg_match('/^[0-9a-f]{40}$/', $sha)) return [false, 'Invalid commit id', 0];
    $info = $rollback ? null : (($meta['subject'] ?? null) ? $meta : (self::commit($sha) ?: []));
    $id = DB::table('deployments')->insertGetId(['sha' => $rollback ? null : $sha, 'subject' => $rollback ? 'Rollback to previous release' : ($info['subject'] ?? 'Update ' . substr($sha, 0, 7)), 'notes' => $info['notes'] ?? null,
      'source' => $source, 'status' => 'Queued', 'progress' => 0, 'stage' => 'Queued', 'from_release' => self::liveId(), 'breaking' => $breaking, 'requested_by' => $by, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('deployments')->where('id', $id)->update(['version' => 'v1.' . $id]);
    @file_put_contents(self::D . '/status', 'queued'); @chmod(self::D . '/status', 0666);
    file_put_contents(self::D . '/request', $rollback ? 'rollback' : $sha . ($breaking ? ' allow-breaking' : ''));
    self::audit('deploy queued', ($rollback ? 'Rollback' : ($info['subject'] ?? $sha)) . ' (' . ($rollback ? 'previous release' : substr($sha, 0, 7)) . ')');
    return [true, 'Queued', $id];
  }

  public static function cancel(): bool {
    $row = DB::table('deployments')->where('status', 'Queued')->latest('id')->first();
    if (!$row || !is_file(self::D . '/request')) return false;
    @unlink(self::D . '/request');
    DB::table('deployments')->where('id', $row->id)->update(['status' => 'Failed', 'stage' => 'Cancelled', 'error' => 'Cancelled by admin before it started', 'finished_at' => now(), 'updated_at' => now()]);
    self::audit('deploy cancelled', (string)$row->subject); return true;
  }

  /** Move the active deployment forward by reading what deploy.sh wrote. Safe to call often. */
  public static function reconcile(): void {
    $row = DB::table('deployments')->whereIn('status', self::ACTIVE)->latest('id')->first(); if (!$row) return;
    $st = self::rd('status'); $mt = (int)@filemtime(self::D . '/status'); $since = strtotime((string)$row->created_at . ' UTC') - 5;
    $fresh = $mt >= $since; $sha7 = $row->sha ? substr($row->sha, 0, 7) : null; $log = self::rd('last.log');
    $u = ['updated_at' => now()];
    if (is_file(self::D . '/request')) { $u += ['status' => 'Queued', 'stage' => 'Queued', 'progress' => 0]; }
    elseif ($fresh && str_starts_with($st, 'ok:') && ($sha7 ? str_contains($st, $sha7) : str_contains($st, 'rolled back'))) {
      $rb = !$row->sha; $rid = $rb ? null : trim(substr($st, 3), ' ') ; $rid = $rid ? preg_replace('/ live$/', '', $rid) : null;
      $u += ['status' => $rb ? 'Rolled Back' : 'Completed', 'stage' => $rb ? 'Rolled back' : 'Completed', 'progress' => 100, 'release_id' => $rid, 'log' => mb_substr($log, -9000), 'finished_at' => now()];
      if ($rb && $row->from_release) DB::table('deployments')->where('release_id', $row->from_release)->where('id', '<>', $row->id)->update(['status' => 'Rolled Back']);
      self::audit($rb ? 'deploy rolled back' : 'deploy live', ($row->subject ?? '') . ' — now LIVE');
    } elseif ($fresh && str_starts_with($st, 'failed:')) {
      $rolled = str_contains($st, 'rolled back');
      $u += ['status' => $rolled ? 'Rolled Back' : 'Failed', 'stage' => $rolled ? 'Rolled back' : 'Failed', 'error' => trim(substr($st, 7)), 'log' => mb_substr($log, -9000), 'finished_at' => now()];
      self::audit($rolled ? 'deploy rolled back' : 'deploy failed', ($row->subject ?? '') . ' — previous version kept');
    } elseif ($fresh && $row->status !== 'Queued' && time() - max((int)@filemtime(self::D . '/stage'), (int)@filemtime(self::D . '/status'), (int)@filemtime(self::D . '/last.log')) > 1200) {
      $u += ['status' => 'Failed', 'stage' => 'Failed', 'error' => 'Interrupted: no progress for 20 minutes (the server was restarted during this deployment). Press Deploy Now to run it again.', 'finished_at' => now()];
      self::audit('deploy interrupted', (string)$row->subject);
    } elseif ($fresh && $st !== '') {
      $sf = self::D . '/stage'; $stale = is_file($sf) && filemtime($sf) < strtotime((string)$row->created_at . ' UTC') - 5;
      [$stage, $pct] = $stale ? ['', '0'] : array_pad(explode('|', self::rd('stage')), 2, '0');
      $map = ['Downloading' => 'Downloading', 'Building' => 'Building', 'Testing' => 'Testing', 'Deploying' => 'Deploying', 'Verifying' => 'Deploying', 'Rolling back' => 'Deploying'];
      if (isset($map[$stage])) $u += ['status' => $map[$stage], 'stage' => $stage, 'progress' => min(99, max((int)$row->progress, (int)$pct))];
      if ($row->status === 'Queued' && isset($map[$stage])) self::audit('deploy started', (string)$row->subject);
    } elseif (!$fresh && $row->status !== 'Queued' && time() - strtotime((string)$row->updated_at . ' UTC') > 1500) {
      $u += ['status' => 'Failed', 'stage' => 'Failed', 'error' => 'No progress for 25 minutes', 'finished_at' => now()];
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
