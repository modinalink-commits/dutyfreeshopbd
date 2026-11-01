<?php
namespace App\Http\Controllers;

use App\Support\Deployer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Deployment Center: automatic zero-downtime releases (see App\Support\Deployer and deploy.sh). */
class DeployController extends Controller
{
  private function guard() { abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403); }

  private function data(): array {
    Deployer::tick();
    $live = Deployer::liveId();
    $tz = fn($t) => $t ? \Carbon\Carbon::parse($t, 'UTC')->timezone('Asia/Dhaka') : null;
    $all = DB::table('deployments')->orderBy('id')->limit(400)->get();
    $groups = [];
    foreach ($all as $d) { $k = $d->sha ?: 'r' . $d->id; $groups[$k][] = $d; }
    $out = []; $n = 0; $files = fn($d) => ($d->log && preg_match('/(\d+) files changed/', $d->log, $m)) ? (int)$m[1] : null;
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
      $out[] = ['id' => $main->id, 'version' => $done ? 'v1.' . $n : '', 'subject' => $main->subject, 'description' => $main->description, 'notes' => $main->notes, 'sha' => $main->sha,
        'status' => $status, 'badge' => $isLive ? 'Live' : ($isRb && !$act ? 'Rollback' : $status), 'cat' => $cat, 'live' => (bool)$isLive, 'stage' => $main->stage, 'progress' => (int)$main->progress,
        'source' => $main->source, 'release_id' => $main->release_id, 'dur' => $done ? $dur($done) : $dur($main), 'at' => $fin ? $fin->format('d M Y · g:i A') : '', 'short' => $fin ? $fin->format('g:i A') : '',
        'started_ts' => $main->started_at ? strtotime($main->started_at . ' UTC') : null, 'files' => $files($done ?: $main), 'error' => ($done ? null : $last->error),
        'attempts' => array_map(fn($x, $i) => ['n' => $i + 1, 'id' => $x->id, 'status' => $x->status, 'dur' => $dur($x), 'at' => ($t = $tz($x->started_at)) ? $t->format('d M · g:i A') : '', 'error' => $x->error], $att, array_keys($att)),
        'retry' => (!$act && !$done && $last->sha) ? $last->id : null];
    }
    $rows = array_slice(array_reverse($out), 0, 60);
    $cur = null; foreach ($rows as $r) if ($r['live']) { $cur = $r; break; }
    $act = null; foreach ($rows as $r) if ($r['cat'] === 'running') { $act = $r; $act['changed'] = (int)@file_get_contents(Deployer::D . '/changed'); break; }
    $head = Deployer::head(); $headRow = $head ? DB::table('deployments')->where('sha', $head['sha'])->orderByDesc('id')->first() : null;
    $log = is_file(Deployer::D . '/last.log') ? implode("\n", array_slice(explode("\n", (string)@file_get_contents(Deployer::D . '/last.log')), -80)) : '';
    return ['enabled' => Deployer::enabled(), 'liveId' => $live, 'current' => $cur, 'active' => $act, 'history' => $rows, 'head' => $head, 'ts' => time(),
      'headState' => !$head ? 'unknown' : ($headRow ? ($headRow->release_id === $live ? 'live' : strtolower($headRow->status)) : (Deployer::mode() === 'hold' ? 'waiting' : 'pending')),
      'tasks' => \Illuminate\Support\Facades\Schema::hasTable('work_log') ? DB::table('work_log')->orderBy('id')->get(['id', 'title', 'status', 'percent', 'note', 'updated_at'])->map(fn($t) => (array)$t + ['at' => \Carbon\Carbon::parse($t->updated_at, 'UTC')->timezone('Asia/Dhaka')->format('d M · g:i A')])->all() : [],
      'mode' => Deployer::mode(), 'branch' => Deployer::branch(), 'log' => $log, 'now' => now()->timezone('Asia/Dhaka')->format('h:i:s A')];
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
  public function refresh() { $this->guard(); Deployer::tick(true); \Cache::forget('deploy_head_' . Deployer::branch()); Deployer::head(true); return response()->json($this->data())->header('Cache-Control', 'no-store'); }

  /** Deploy Now: a given commit, or the newest commit on the production branch. */
  public function run(Request $r) {
    $this->guard();
    $ref = strtolower(trim((string)$r->input('ref')));
    if ($ref === 'rollback') [$ok, $msg] = Deployer::queue('rollback', 'manual', [], false, auth()->user()->name ?? null);
    else {
      if ($ref === '') { $h = Deployer::head(true); if (!$h) return response()->json(['ok' => false, 'msg' => 'Could not read the production branch'], 502); $ref = $h['sha']; $meta = $h; }
      if (!preg_match('/^[0-9a-f]{40}$/', $ref)) return response()->json(['ok' => false, 'msg' => 'Commit id must be 40 characters (or leave empty for the latest)'], 422);
      [$ok, $msg] = Deployer::queue($ref, 'manual', $meta ?? [], $r->boolean('breaking'), auth()->user()->name ?? null);
    }
    return response()->json(['ok' => $ok, 'msg' => $msg], $ok ? 200 : 409);
  }

  public function retry($id) {
    $this->guard(); $d = DB::table('deployments')->find($id);
    if (!$d || !$d->sha || !in_array($d->status, ['Failed', 'Rolled Back'], true)) return response()->json(['ok' => false, 'msg' => 'Only a failed or rolled-back deployment can be retried'], 422);
    [$ok, $msg] = Deployer::queue($d->sha, 'manual', ['subject' => $d->subject, 'notes' => $d->notes], (bool)$d->breaking, auth()->user()->name ?? null);
    return response()->json(['ok' => $ok, 'msg' => $msg], $ok ? 200 : 409);
  }

  public function cancel() { $this->guard(); $ok = Deployer::cancel(); return response()->json(['ok' => $ok, 'msg' => $ok ? 'Cancelled' : 'Nothing waiting to cancel (a running build cannot be stopped safely)'], $ok ? 200 : 409); }

  public function edit(Request $r, $id) {
    $this->guard();
    DB::table('deployments')->where('id', $id)->update(['subject' => mb_substr(trim((string)$r->input('subject')), 0, 240), 'description' => mb_substr((string)$r->input('description'), 0, 2000), 'notes' => mb_substr((string)$r->input('notes'), 0, 4000), 'updated_at' => now()]);
    return response()->json(['ok' => true]);
  }

  public function log($id) { $this->guard(); $d = DB::table('deployments')->find($id); abort_unless($d, 404); return response()->json(['log' => $d->log ?: (in_array($d->status, Deployer::ACTIVE, true) ? (string)@file_get_contents(Deployer::D . '/last.log') : 'No log saved.')]); }

  public function settings(Request $r) {
    $this->guard();
    Deployer::put('deploy_mode', $r->input('mode') === 'hold' ? 'hold' : 'auto');
    $b = trim((string)$r->input('branch', 'main')); if (preg_match('/^[A-Za-z0-9._\/-]{1,60}$/', $b)) Deployer::put('deploy_branch', $b);
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
