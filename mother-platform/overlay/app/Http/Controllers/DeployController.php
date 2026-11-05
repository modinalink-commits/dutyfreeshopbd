<?php
namespace App\Http\Controllers;

use App\Support\DeployHealth;
use App\Support\Deployer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Deployment Center: automatic zero-downtime releases (see App\Support\Deployer and deploy.sh), health monitoring, issues, rollback and recovery. */
class DeployController extends Controller
{
  private function guard() { abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403); }
  private function isSuper(): bool { return optional(auth()->user())->role === 'super'; }
  /** Server-wide / destructive actions: Full Deploy, Rollback, restart, cache, recovery are for the owner-level (super) role only. */
  private function superOnly() { if (!$this->isSuper()) abort(response()->json(['ok' => false, 'msg' => 'Only the Super Admin can do this action.'], 403)); }
  private function who(): string { return optional(auth()->user())->name ?: 'Admin'; }

  /** Spec steps from the engine's own log markers (1/9 ... 9/9) and its "SKIPPED" lines. */
  private function steps(string $log, ?string $status): array {
    $names = [1 => 'Preparing Release', 2 => 'Fetching Code', 3 => 'Checking Dependencies', 4 => 'Running Migration', 6 => 'Building Cache', 7 => 'Health Check (new release)', 8 => 'Switching Release', 9 => 'Health Check (live)'];
    $pos = []; foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n) { $i = strpos($log, $n . '/9 '); if ($i !== false) $pos[$n] = $i; }
    $mx = $pos ? max(array_keys($pos)) : 0; $fin = in_array($status, ['Completed', 'Rolled Back'], true); $fail = $status === 'Failed' || $status === 'Rolled Back';
    $seg = function (int $n) use ($pos, $log) { if (!isset($pos[$n])) return ''; $nx = array_values(array_filter(array_keys($pos), fn($k) => $k > $n)); $e = $nx ? $pos[min($nx)] : strlen($log); return substr($log, $pos[$n], $e - $pos[$n]); };
    $out = [];
    foreach ($names as $n => $label) {
      $seen = isset($pos[$n]) || ($n === 4 && isset($pos[5])); $skip = false;
      if ($n === 3) $skip = str_contains($seg(3), 'SKIPPED'); if ($n === 4) $skip = str_contains($seg(4), 'SKIPPED') || str_contains($seg(5), 'SKIPPED');
      $mxEff = max($mx, 0); $myMax = $n === 4 ? 5 : $n;
      if ($fin && !$fail) $st = 'done'; elseif (!$seen) $st = 'wait'; elseif ($mxEff > $myMax) $st = 'done'; else $st = $fail ? 'fail' : 'run';
      $out[] = ['label' => $label, 'state' => $st, 'skip' => $skip && $st === 'done'];
    }
    $out[] = ['label' => 'Complete', 'state' => ($fin && !$fail) ? 'done' : 'wait', 'skip' => false];
    return $out;
  }

  private function data(bool $deep = false): array {
    Deployer::tick();
    $live = Deployer::liveId(); $isSuper = $this->isSuper();
    $tz = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka') : null;
    $all = DB::table('deployments')->orderByDesc('id')->limit(500)->get()->reverse()->values();
    $hasIssues = \Illuminate\Support\Facades\Schema::hasTable('deploy_issues');
    $ic = $hasIssues ? DB::table('deploy_issues')->whereNotNull('deployment_id')->groupBy('deployment_id')->selectRaw('deployment_id, count(*) n')->pluck('n', 'deployment_id')->all() : [];
    $groups = [];
    foreach ($all as $d) { $k = $d->sha ?: 'r' . $d->id; $groups[$k][] = $d; }
    $inWin = $all->pluck('sha')->filter()->unique()->all(); $cut = $all->first()->id ?? 0;
    $n = (int)DB::table('deployments')->where('id', '<', $cut)->where('status', 'Completed')->whereNotNull('sha')->when($inWin, fn($q) => $q->whereNotIn('sha', $inWin))->distinct()->count('sha'); // release numbers keep counting from the first release even though only the newest rows are loaded
    $out = []; $files = fn($d) => ($d->log && preg_match('/(\d+) files changed/', $d->log, $m)) ? (int)$m[1] : null;
    foreach ($groups as $att) {
      $done = null; $act = null;
      foreach ($att as $x) { if ($x->status === 'Completed') $done = $x; if (in_array($x->status, Deployer::ACTIVE, true)) $act = $x; }
      $last = end($att); $main = $act ?: ($done ?: $last);
      $isRb = !$main->sha; $ok = $done || ($isRb && $main->status === 'Rolled Back');
      if ($ok && $done) $n++;
      $isLive = $done && $done->release_id && $done->release_id === $live;
      $status = $act ? $act->status : ($done ? 'Completed' : $last->status);
      $cat = $act ? 'running' : ($isLive ? 'live' : (($done || $isRb) ? 'completed' : 'failed'));
      $dur = fn($x) => ($x->started_at && $x->finished_at) ? max(1, strtotime($x->finished_at . ' UTC') - strtotime($x->started_at . ' UTC')) : null;
      $fin = $tz(($done ?: $main)->finished_at ?: ($main->started_at));
      $src = $done ?: $main; $dtype = $src->dtype ?? null; if (!$dtype && $done) $dtype = Deployer::guessType($done->log);
      $ids = array_map(fn($x) => $x->id, $att); $issueN = 0; foreach ($ids as $i) $issueN += (int)($ic[$i] ?? 0);
      $out[] = ['id' => $main->id, 'no' => $done ? $n : null, 'version' => $done ? 'v1.' . $n : '', 'subject' => $main->subject, 'description' => $main->description, 'notes' => $main->notes, 'sha' => $main->sha,
        'status' => $status, 'badge' => $isLive ? 'Live' : ($isRb && !$act ? 'Rollback' : $status), 'cat' => $cat, 'live' => (bool)$isLive, 'stage' => $main->stage, 'progress' => (int)$main->progress,
        'source' => $main->source, 'release_id' => $main->release_id, 'dur' => $done ? $dur($done) : $dur($main), 'at' => $fin ? $fin->format('d M Y · g:i A') : '', 'short' => $fin ? $fin->format('d M') : '', 'time' => $fin ? $fin->format('g:i A') : '',
        'started_ts' => $main->started_at ? strtotime($main->started_at . ' UTC') : null, 'started' => ($t = $tz($main->started_at)) ? $t->format('d M Y g:i:s A') : '', 'finished' => ($t = $tz(($done ?: $main)->finished_at)) ? $t->format('d M Y g:i:s A') : '',
        'files' => $files($done ?: $main), 'error' => ($done ? null : $last->error), 'dtype' => $dtype, 'by' => $main->requested_by ?: ($main->source === 'auto' ? 'System (auto)' : 'System'), 'from' => $main->from_release, 'issues' => $issueN, 'breaking' => (bool)$main->breaking,
        'attempts' => array_map(fn($x, $i) => ['n' => $i + 1, 'id' => $x->id, 'status' => $x->status, 'dur' => $dur($x), 'at' => ($t = $tz($x->started_at)) ? $t->format('d M · g:i A') : '', 'error' => $x->error], $att, array_keys($att)),
        'retry' => (!$act && !$done && $last->sha) ? $last->id : null, 'steps' => null];
    }
    $rows = array_slice(array_reverse($out), 0, 60);
    $cur = null; foreach ($rows as $r) if ($r['live']) { $cur = $r; break; }
    $act = null; foreach ($rows as $r) if ($r['cat'] === 'running') { $act = $r; $act['changed'] = (int)@file_get_contents(Deployer::D . '/changed'); break; }
    $head = Deployer::head(); $headRow = $head ? DB::table('deployments')->where('sha', $head['sha'])->orderByDesc('id')->first() : null;
    $raw = is_file(Deployer::D . '/last.log') ? (string)@file_get_contents(Deployer::D . '/last.log') : '';
    $log = Deployer::clean(implode("\n", array_slice(explode("\n", $raw), -140)));
    $lastRow = $rows[0] ?? null; $focus = $act ?: $lastRow; $focusLog = $focus ? ($act ? $raw : ($lastRow && $lastRow['id'] ? (string)DB::table('deployments')->where('id', $lastRow['id'])->value('log') : '')) : '';
    $steps = $focus ? $this->steps($focusLog ?: $raw, $act ? 'Running' : $focus['status']) : [];
    $prev = Deployer::previous(); $prevOut = $prev ? ['id' => $prev['id'], 'version' => $prev['row']->version ?? null, 'subject' => $prev['row']->subject ?? null, 'valid' => $prev['valid'], 'at' => ($prev['row'] && ($t = $tz($prev['row']->finished_at))) ? $t->format('d M Y · g:i A') : ''] : null;
    $issues = DeployHealth::active(); $health = DeployHealth::light();
    $d = ['enabled' => Deployer::enabled(), 'liveId' => $live, 'current' => $cur, 'active' => $act, 'history' => $rows, 'head' => $head, 'ts' => time(), 'locked' => Deployer::lockHeld() || (bool)$act,
      'headState' => !$head ? 'unknown' : ($headRow ? ($headRow->release_id === $live ? 'live' : strtolower($headRow->status)) : (Deployer::mode() === 'hold' ? 'waiting' : 'pending')),
      'tasks' => \Illuminate\Support\Facades\Schema::hasTable('work_log') ? DB::table('work_log')->orderBy('id')->get(['id', 'title', 'status', 'percent', 'note', 'updated_at'])->map(fn($t) => (array)$t + ['at' => \Carbon\Carbon::parse($t->updated_at, 'UTC')->timezone('Asia/Dhaka')->format('d M · g:i A')])->all() : [],
      'mode' => Deployer::mode(), 'branch' => Deployer::branch(), 'log' => $log, 'now' => now()->timezone('Asia/Dhaka')->format('h:i:s A'),
      'health' => $health, 'issues' => $issues, 'issueHist' => $hasIssues ? (int)DB::table('deploy_issues')->whereIn('status', ['resolved', 'ignored'])->count() : 0, 'previous' => $prevOut, 'steps' => $steps, 'stepsFor' => $focus['id'] ?? null,
      'super' => $isSuper, 'lastHealth' => \Illuminate\Support\Facades\Cache::get('dh.full'), 'queue' => DeployHealth::queueInfo(), 'cache' => DeployHealth::cacheInfo(),
      'migs' => count(DeployHealth::pendingMigrations()), 'retention' => 3];
    return $d;
  }

  public function index() { $this->guard(); return view('deploy', ['d' => $this->data()]); }
  public function taskSave(Request $r) {
    $this->guard(); $v = $r->validate(['id' => 'nullable|integer', 'title' => 'nullable|string|max:200', 'status' => 'nullable|in:pending,partial,done,blocked', 'percent' => 'nullable|integer|min:0|max:100', 'note' => 'nullable|string|max:1000']);
    $u = array_filter(['title' => $v['title'] ?? null, 'status' => $v['status'] ?? null, 'percent' => $v['percent'] ?? null, 'note' => $v['note'] ?? null], fn($x) => $x !== null);
    if (($v['status'] ?? '') === 'done' && !isset($u['percent'])) $u['percent'] = 100;
    if (!empty($v['id'])) DB::table('work_log')->where('id', $v['id'])->update($u + ['updated_at' => now()]);
    elseif (!empty($u['title'])) $v['id'] = DB::table('work_log')->insertGetId($u + ['created_at' => now(), 'updated_at' => now()]);
    return response()->json(['ok' => true, 'id' => $v['id'] ?? null]);
  }
  public function status() { $this->guard(); return response()->json($this->data())->header('Cache-Control', 'no-store'); }
  public function refresh() { $this->guard(); Deployer::tick(true); \Cache::forget('deploy_head_' . Deployer::branch()); \Cache::forget('dh.light'); Deployer::head(true); return response()->json($this->data())->header('Cache-Control', 'no-store'); }

  /** Deploy: type quick | standard | full | rollback (rollback needs a reason). A given commit, or the newest commit on the production branch. */
  public function run(Request $r) {
    $this->guard();
    $type = (string)$r->input('type', ''); $ref = strtolower(trim((string)$r->input('ref')));
    if ($ref === 'rollback') $type = 'rollback';
    if ($type === 'rollback') {
      $this->superOnly(); $reason = trim((string)$r->input('reason'));
      if (mb_strlen($reason) < 3) return response()->json(['ok' => false, 'msg' => 'Please write a short reason for the rollback.'], 422);
      [$ok, $msg] = Deployer::queue('rollback', 'manual', ['reason' => $reason, 'dtype' => 'rollback'], false, $this->who());
      return response()->json(['ok' => $ok, 'msg' => $ok ? 'Rollback started.' : $msg], $ok ? 200 : 409);
    }
    if (!in_array($type, ['quick', 'standard', 'full'], true)) $type = 'standard';
    if ($type === 'full') $this->superOnly();
    if ($ref === '') { $h = Deployer::head(true); if (!$h) return response()->json(['ok' => false, 'msg' => 'Could not read the production branch'], 502); $ref = $h['sha']; $meta = $h; }
    if (!preg_match('/^[0-9a-f]{40}$/', $ref)) return response()->json(['ok' => false, 'msg' => 'Commit id must be 40 characters (or leave empty for the latest)'], 422);
    if ($type === 'quick') { [$qok, $qmsg] = Deployer::quickCheck($ref); if (!$qok) return response()->json(['ok' => false, 'msg' => $qmsg, 'suggest' => 'standard'], 409); }
    $meta = ($meta ?? []) + ['dtype' => $type, 'note' => trim((string)$r->input('note'))];
    [$ok, $msg] = Deployer::queue($ref, 'manual', $meta, $type === 'full' || $r->boolean('breaking'), $this->who());
    return response()->json(['ok' => $ok, 'msg' => $ok ? ucfirst($type) . ' deployment started.' : $msg], $ok ? 200 : 409);
  }

  public function retry($id) {
    $this->guard(); $d = DB::table('deployments')->find($id);
    if (!$d || !$d->sha || !in_array($d->status, ['Failed', 'Rolled Back'], true)) return response()->json(['ok' => false, 'msg' => 'Only a failed or rolled-back deployment can be retried'], 422);
    [$ok, $msg] = Deployer::queue($d->sha, 'manual', ['subject' => $d->subject, 'notes' => $d->notes, 'dtype' => $d->dtype ?? 'standard', 'note' => $d->description], (bool)$d->breaking, $this->who());
    return response()->json(['ok' => $ok, 'msg' => $ok ? 'Retry started.' : $msg], $ok ? 200 : 409);
  }

  public function cancel() { $this->guard(); $ok = Deployer::cancel(); return response()->json(['ok' => $ok, 'msg' => $ok ? 'Cancelled' : 'Nothing waiting to cancel (a running build cannot be stopped safely)'], $ok ? 200 : 409); }

  public function edit(Request $r, $id) {
    $this->guard();
    DB::table('deployments')->where('id', $id)->update(['subject' => mb_substr(trim((string)$r->input('subject')), 0, 240), 'description' => mb_substr((string)$r->input('description'), 0, 2000), 'notes' => mb_substr((string)$r->input('notes'), 0, 4000), 'updated_at' => now()]);
    return response()->json(['ok' => true]);
  }

  public function log($id) {
    $this->guard(); $d = DB::table('deployments')->find($id); abort_unless($d, 404);
    $t = $d->log ?: (in_array($d->status, Deployer::ACTIVE, true) ? (string)@file_get_contents(Deployer::D . '/last.log') : 'No log saved.');
    return response()->json(['log' => Deployer::clean((string)$t), 'error' => $d->error ? Deployer::clean((string)$d->error) : null]);
  }

  /** Run Health Check (the full list). */
  public function healthRun() {
    $this->guard(); $r = DeployHealth::full();
    Deployer::audit('Health Check Run', 'Result: ' . $r['overall'], null, ['result' => $r['overall'], 'old' => null, 'new' => null]);
    return response()->json(['ok' => true] + $r)->header('Cache-Control', 'no-store');
  }
  public function recover() {
    $this->guard(); $this->superOnly(); $r = Deployer::recover();
    Deployer::audit('Recovery Check Run', count(array_filter($r, fn($x) => $x['state'] === 'fail')) . ' problem(s)', null, ['result' => json_encode(array_column($r, 'state'))]);
    return response()->json(['ok' => true, 'items' => $r, 'data' => $this->data()])->header('Cache-Control', 'no-store');
  }

  /** Clear / rebuild only the safe storefront caches (never sessions, never compiled config/routes). */
  public function cacheAct(Request $r) {
    $this->guard(); $this->superOnly(); $keys = ['store.home8', 'store.cats', 'store.subs', 'store.rules', 'store.catslugs', 'store.globe_products', 'store.ann', 'store.live4', 'store.offline', 'dh.light', 'dh.full'];
    foreach ($keys as $k) \Cache::forget($k);
    $rebuild = $r->input('mode') === 'rebuild'; $warm = null;
    if ($rebuild) { $warm = DeployHealth::probe('/', 15); DeployHealth::light(true); }
    Deployer::audit($rebuild ? 'Cache Rebuilt' : 'Safe Cache Cleared', count($keys) . ' storefront cache keys', null, ['result' => $rebuild ? ($warm['ok'] ? 'warmed' : 'warm failed') : 'cleared']);
    return response()->json(['ok' => true, 'msg' => $rebuild ? ('Cache cleared and the front page was rebuilt (' . ($warm['ms'] ?? 0) . ' ms).') : 'Safe cache cleared. It refills automatically on the next visits.']);
  }
  public function queueRestart() {
    $this->guard(); $this->superOnly(); \Illuminate\Support\Facades\Artisan::call('queue:restart');
    Deployer::audit('Queue Restarted', 'queue:restart signal sent (workers finish the current job, then restart)', null, ['result' => 'signal sent']);
    return response()->json(['ok' => true, 'msg' => 'Restart signal sent. Workers restart after their current job.']);
  }
  public function failedJobs() {
    $this->guard(); $rows = [];
    try { if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) $rows = DB::table('failed_jobs')->orderByDesc('id')->limit(15)->get(['id', 'queue', 'failed_at', 'exception'])->map(fn($j) => ['id' => $j->id, 'queue' => $j->queue, 'at' => (string)$j->failed_at, 'error' => Deployer::clean(mb_substr(strtok((string)$j->exception, "\n"), 0, 220))])->all(); } catch (\Throwable $e) {}
    return response()->json(['rows' => $rows]);
  }
  public function migrations() { $this->guard(); return response()->json(['pending' => DeployHealth::pendingMigrations(), 'ran' => (int)DB::table('migrations')->count(), 'last' => DB::table('migrations')->orderByDesc('id')->limit(5)->pluck('migration')->all()]); }
  public function changes($id) {
    $this->guard(); $d = DB::table('deployments')->find($id); abort_unless($d, 404);
    return response()->json(['info' => $d->sha ? Deployer::commitFiles($d->sha) : null]);
  }

  public function issues() { $this->guard(); return response()->json(['active' => DeployHealth::active(), 'history' => DeployHealth::history()])->header('Cache-Control', 'no-store'); }
  public function issueSet(Request $r, $id) {
    $this->guard(); $st = (string)$r->input('status'); abort_unless(in_array($st, ['open', 'investigating', 'resolved', 'ignored'], true), 422);
    $row = DB::table('deploy_issues')->find($id); abort_unless($row, 404);
    $u = ['status' => $st, 'note' => mb_substr((string)$r->input('note', $row->note), 0, 800), 'updated_at' => now()];
    if (in_array($st, ['resolved', 'ignored'], true)) $u += ['resolved_at' => now(), 'resolved_by' => $this->who()]; else $u += ['resolved_at' => null, 'resolved_by' => null];
    DB::table('deploy_issues')->where('id', $id)->update($u);
    Deployer::audit('Issue ' . ucfirst($st), $row->title, null, ['old' => $row->status, 'new' => $st, 'result' => 'ok']);
    return response()->json(['ok' => true]);
  }

  public function settings(Request $r) {
    $this->guard();
    Deployer::put('deploy_mode', $r->input('mode') === 'hold' ? 'hold' : 'auto');
    $b = trim((string)$r->input('branch', 'main')); if (preg_match('/^[A-Za-z0-9._\/-]{1,60}$/', $b)) Deployer::put('deploy_branch', $b);
    $gt = trim((string)$r->input('gh_token', ''));
    if ($gt !== '' && preg_match('/^[A-Za-z0-9_]{20,255}$/', $gt)) \App\Support\Sms::setSecret('deploy_github_token', $gt);
    \Cache::forget('deploy_head_' . Deployer::branch());
    return response()->json(['ok' => true]);
  }

  /** Public, cheap readiness probe used by deploy.sh before and after switching a release. */
  public function health() {
    $ok = true; $c = [];
    try { \DB::select('select 1'); $c['database'] = 'ok'; } catch (\Throwable $e) { $ok = false; $c['database'] = 'fail'; }
    $f = storage_path('framework/health.tmp'); $w = @file_put_contents($f, '1') !== false; @unlink($f); $c['storage'] = $w ? 'ok' : 'fail'; $ok = $ok && $w;
    try { $c['orders'] = \DB::table('orders')->limit(1)->exists() ? 'ok' : 'ok'; } catch (\Throwable $e) { $ok = false; $c['orders'] = 'fail'; }
    $c['queue'] = config('queue.default') === 'sync' ? 'sync' : 'ok';
    return response()->json(['ok' => $ok] + $c, $ok ? 200 : 503)->header('Cache-Control', 'no-store');
  }
}
