<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Admin screen for zero-downtime releases. The actual work is done by deploy.sh in the app container. */
class DeployController extends Controller
{
  const D = '/srv/deploy';

  private function rd(string $f): string { return is_file(self::D . '/' . $f) ? trim((string)@file_get_contents(self::D . '/' . $f)) : ''; }

  private function guard() { abort_unless(in_array(optional(auth()->user())->role, ['super', 'admin'], true), 403); }

  private function data(): array {
    $rel = []; foreach (glob('/srv/releases/*', GLOB_ONLYDIR) ?: [] as $d) $rel[] = ['id' => basename($d), 'at' => filemtime($d), 'live' => realpath('/srv/app') === realpath($d)];
    usort($rel, fn($a, $b) => $b['at'] <=> $a['at']);
    $log = is_file(self::D . '/last.log') ? implode("\n", array_slice(explode("\n", (string)@file_get_contents(self::D . '/last.log')), -60)) : '';
    return ['enabled' => is_link('/srv/app') && is_dir(self::D), 'live' => basename((string)realpath('/srv/app')), 'status' => $this->rd('status') ?: 'idle', 'releases' => $rel, 'log' => $log, 'pending' => is_file(self::D . '/request')];
  }

  public function index() { $this->guard(); return view('deploy', $this->data()); }
  public function status() { $this->guard(); return response()->json($this->data())->header('Cache-Control', 'no-store'); }

  public function run(Request $r) {
    $this->guard();
    abort_unless($this->data()['enabled'], 409, 'Release layout is not active yet');
    $ref = strtolower(trim((string)$r->input('ref')));
    if ($ref === 'rollback') $req = 'rollback';
    elseif (preg_match('/^[0-9a-f]{40}$/', $ref)) $req = $ref . ($r->boolean('breaking') ? ' allow-breaking' : '');
    else return response()->json(['ok' => false, 'msg' => 'Enter the full 40-character commit id'], 422);
    if (is_file(self::D . '/request')) return response()->json(['ok' => false, 'msg' => 'A request is already waiting'], 409);
    file_put_contents(self::D . '/request', $req);
    @file_put_contents(self::D . '/status', 'queued');
    return response()->json(['ok' => true]);
  }
}
