<?php
namespace App\Http\Controllers;

use App\Support\AiVerify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** AI Verify endpoints: show/run the verification of one order, staff override, and a light status map for the order list. */
class AiVerifyController extends Controller {
  public function show(Request $r, int $id) {
    abort_unless(DB::table('orders')->where('id', $id)->exists(), 404);
    $res = AiVerify::latest($id);
    if ($r->boolean('run') || !$res) $res = AiVerify::run($id, $r->boolean('run') ? 're-verify' : 'manual', true, true);
    $res['can_override'] = in_array(auth()->user()->role ?? '', ['super', 'admin'], true);
    $res['can_cost'] = \App\Support\Costing::canSee();
    return response()->json($res)->header('Cache-Control', 'no-store');
  }
  public function override(Request $r, int $id) {
    abort_unless(in_array(auth()->user()->role ?? '', ['super', 'admin'], true), 403);
    $d = $r->validate(['status' => 'required|in:verified,review,high,failed,pending', 'reason' => 'required|string|min:5|max:300']);
    $res = AiVerify::override($id, $d['status'], trim($d['reason']));
    abort_unless($res, 404);
    $res['can_override'] = true; $res['can_cost'] = \App\Support\Costing::canSee();
    return response()->json($res);
  }
  public function statuses(Request $r) {
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string)$r->query('ids')))), 0, 200); if (!$ids) return response()->json(['m' => [], 'left' => 0]);
    // Every order on the page gets a result: orders without one (admin-created, AI/legacy channels, or placed before AI Verify) are verified now, newest first, a few per request.
    $have = DB::table('order_ai_verifications')->whereIn('order_id', $ids)->pluck('order_id')->all();
    $miss = DB::table('orders')->whereIn('id', array_diff($ids, $have))->where('status', '<>', 'Incomplete')->orderByDesc('id')->pluck('id')->all();
    foreach (array_slice($miss, 0, 8) as $mid) { try { AiVerify::run((int)$mid, 'auto', false, false); } catch (\Throwable $e) {} }
    $left = max(0, count($miss) - 8);
    $rows = DB::table('order_ai_verifications')->whereIn('order_id', $ids)->get(['order_id', 'verification_status', 'override_status', 'verification_score']);
    $o = []; foreach ($rows as $x) { $s = $x->override_status ?: $x->verification_status; $o[$x->order_id] = ['s' => $s, 'l' => AiVerify::LABEL[$s] ?? $s, 'n' => (int)$x->verification_score]; }
    return response()->json(['m' => $o, 'left' => $left])->header('Cache-Control', 'no-store');
  }
}
