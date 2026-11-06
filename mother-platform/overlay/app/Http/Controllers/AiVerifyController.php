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
  public function page(int $id) { abort_unless(DB::table('orders')->where('id', $id)->exists(), 404); return view('ai_verify_page', ['id' => $id]); }
  public function override(Request $r, int $id) {
    abort_unless(in_array(auth()->user()->role ?? '', ['super', 'admin'], true), 403);
    $d = $r->validate(['status' => 'required|in:verified,review,high,failed,pending', 'reason' => 'required|string|min:5|max:300']);
    $res = AiVerify::override($id, $d['status'], trim($d['reason']));
    abort_unless($res, 404);
    $res['can_override'] = true; $res['can_cost'] = \App\Support\Costing::canSee();
    return response()->json($res);
  }
  /** AI Approve button: needs Orders edit permission; Approved is not a restricted status. */
  public function approve(Request $r, int $id) {
    abort_unless(\App\Http\Middleware\ModulePermission::can(auth()->user(), 'orders', 'edit'), 403);
    abort_unless(DB::table('orders')->where('id', $id)->exists(), 404);
    $lk = \Illuminate\Support\Facades\Cache::lock('ai.approve.' . $id, 30); if (!$lk->get()) return response()->json(['ok' => true, 'already' => true, 'message' => 'Already in progress']);
    try { return response()->json(AiVerify::approveNow($id))->header('Cache-Control', 'no-store'); } finally { $lk->release(); }
  }
  public function statuses(Request $r) {
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string)$r->query('ids')))), 0, 200); if (!$ids) return response()->json(['m' => [], 'left' => 0]);
    // Every order on the page gets a result: orders without one (admin-created, AI/legacy channels, or placed before AI Verify) are verified now, newest first, a few per request.
    $have = DB::table('order_ai_verifications')->whereIn('order_id', $ids)->pluck('order_id')->all();
    $miss = DB::table('orders')->whereIn('id', array_diff($ids, $have))->where('status', '<>', 'Incomplete')->orderByDesc('id')->pluck('id')->all();
    foreach (array_slice($miss, 0, 4) as $mid) { try { AiVerify::run((int)$mid, 'auto', true, false); } catch (\Throwable $e) {} }
    $left = max(0, count($miss) - 4);
    // Orders edited after their last verification (customer, address, items, payment, discount, status) are re-verified by themselves; unchanged data is skipped by the fingerprint.
    $stale = DB::table('order_ai_verifications as v')->join('orders as o', 'o.id', '=', 'v.order_id')->whereIn('v.order_id', $ids)->whereColumn('o.updated_at', '>', 'v.verified_at')->orderByDesc('o.updated_at')->limit(4)->pluck('v.order_id')->all();
    foreach ($stale as $sid) { try { AiVerify::run((int)$sid, 'auto-update', true, false); } catch (\Throwable $e) {} }
    // Results that could not get a fraud answer yet (older automatic runs without the live courier check) are completed by themselves.
    $nofraud = DB::table('order_ai_verifications')->whereIn('order_id', $ids)->whereRaw("summary->>'fraud_risk' = 'Unknown'")->whereNull('override_status')->orderByDesc('order_id')->limit(4)->pluck('order_id')->all();
    foreach ($nofraud as $nid) { try { AiVerify::run((int)$nid, 'auto-fraud', true, true); } catch (\Throwable $e) {} }
    // Results made by an older rule version (e.g. before the sudden-high-amount block) are checked again with the current rules.
    $oldv = DB::table('order_ai_verifications')->whereIn('order_id', $ids)->where('rule_version', '<>', AiVerify::VERSION)->whereNull('override_status')->orderByDesc('order_id')->limit(4)->pluck('order_id')->all();
    foreach ($oldv as $vid) { try { AiVerify::run((int)$vid, 'rule-update', true, true); } catch (\Throwable $e) {} }
    $left += (count($nofraud) || count($oldv)) ? 1 : 0;
    $rows = DB::table('order_ai_verifications')->whereIn('order_id', $ids)->get(['order_id', 'verification_status', 'override_status', 'verification_score']);
    $o = []; foreach ($rows as $x) { $s = $x->override_status ?: $x->verification_status; $o[$x->order_id] = ['s' => $s, 'l' => AiVerify::LABEL[$s] ?? $s, 'n' => (int)$x->verification_score]; }
    return response()->json(['m' => $o, 'left' => $left])->header('Cache-Control', 'no-store');
  }
}
