<?php
namespace App\Http\Controllers;
use App\Support\ImageAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImageAuditController extends Controller {
  public function index() {
    $rep = json_decode((string)DB::table('settings')->where('key', 'image_audit_report')->value('value'), true);
    $log = DB::table('image_audit_log')->where('kind', 'repair')->orderByDesc('id')->limit(60)->get();
    $fixed = (int)DB::table('image_audit_log')->where('kind', 'repair')->where('result', 'fixed')->count();
    $failed = (int)DB::table('image_audit_log')->where('kind', 'repair')->where('result', 'failed')->count();
    return view('image_audit', ['rep' => $rep, 'log' => $log, 'fixed' => $fixed, 'failed' => $failed]);
  }
  public function scan() { @set_time_limit(120); $r = ImageAudit::scan(); unset($r['bad']); return response()->json($r); }
  /** One small batch; the page calls it repeatedly. */
  public function repair(Request $r) {
    @set_time_limit(120); $skip = array_map('intval', array_filter((array)$r->input('skip', []))); $n = max(1, min(25, (int)($r->input('n') ?: 12)));
    $rep = ImageAudit::scan(); $done = []; $t0 = microtime(true); $fixed = 0; $failed = [];
    $todo = array_values(array_filter($rep['bad'], fn($p) => $p['state'] !== 'none' && !in_array($p['id'], $skip, true)));
    foreach (array_slice($todo, 0, $n) as $p) {
      if (microtime(true) - $t0 > 80) break;
      [$res] = ImageAudit::repair($p); if ($res === 'fixed') $fixed++; else $failed[] = $p['id'];
    }
    foreach (['store.home2', 'store.home4', 'store.home5', 'store.home6', 'store.cats', 'store.live2', 'store.live3', 'store.live4'] as $k) \Illuminate\Support\Facades\Cache::forget($k);
    return response()->json(['fixed' => $fixed, 'failed' => $failed, 'left' => max(0, count($todo) - $fixed - count($failed))]);
  }
}
