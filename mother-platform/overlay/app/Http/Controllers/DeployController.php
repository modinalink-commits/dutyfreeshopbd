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
    $rows = DB::table('deployments')->orderByDesc('id')->limit(40)->get()->map(function ($d) use ($live) {
      $a = (array)$d; $a['live'] = $d->release_id && $d->release_id === $live && $d->status === 'Completed';
      $a['badge'] = $a['live'] ? 'Live' : $d->status;
      $a['start'] = $d->started_at ? \Carbon\Carbon::parse($d->started_at, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A') : '';
      $a['end'] = $d->finished_at ? \Carbon\Carbon::parse($d->finished_at, 'UTC')->timezone('Asia/Dhaka')->format('d M, h:i A') : '';
      $a['dur'] = ($d->started_at && $d->finished_at) ? max(1, (int)round((strtotime($d->finished_at . ' UTC') - strtotime($d->started_at . ' UTC')) / 60)) . ' min' : '';
      unset($a['log']); $a['has_log'] = (bool)$d->log; return $a;
    })->values()->all();
    $cur = null; foreach ($rows as $r) if ($r['live']) { $cur = $r; break; }
    $act = null; foreach ($rows as $r) if (in_array($r['status'], Deployer::ACTIVE, true)) { $act = $r; break; }
    $head = Deployer::head(); $headRow = $head ? DB::table('deployments')->where('sha', $head['sha'])->orderByDesc('id')->first() : null;
    $log = is_file(Deployer::D . '/last.log') ? implode("\n", array_slice(explode("\n", (string)@file_get_contents(Deployer::D . '/last.log')), -80)) : '';
    return ['enabled' => Deployer::enabled(), 'liveId' => $live, 'current' => $cur, 'active' => $act, 'history' => $rows, 'head' => $head,
      'headState' => !$head ? 'unknown' : ($headRow ? ($headRow->release_id === $live ? 'live' : strtolower($headRow->status)) : (Deployer::mode() === 'hold' ? 'waiting' : 'pending')),
      'mode' => Deployer::mode(), 'branch' => Deployer::branch(), 'log' => $log, 'now' => now()->timezone('Asia/Dhaka')->format('h:i:s A')];
  }

  public function index() { $this->guard(); return view('deploy', ['d' => $this->data()]); }
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
