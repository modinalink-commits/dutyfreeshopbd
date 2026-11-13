<?php
namespace App\Http\Controllers;
use App\Support\Procure;
use App\Support\ProfitFloor;
use App\Support\StaffIdle as S;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Staff idle check: the staff member's screen (ping, notice, reason, break) and the owner's ticket list + settings. */
class StaffIdleController extends Controller {
  public function ping() { return response()->json(S::state(auth()->user())); }
  public function notice() {
    $n = S::open((int)auth()->id()); if (!$n) return redirect('/admin');
    return view('staff_idle_notice', ['n' => $n]);
  }
  public function submit(Request $r) {
    $d = $r->validate(['reason' => 'required|string|min:10|max:1000'], ['reason.min' => 'কারণটা অন্তত ১০ অক্ষরে পরিষ্কার করে লিখুন।', 'reason.required' => 'কারণ লিখতে হবে।']);
    S::submit((int)auth()->id(), trim($d['reason']));
    return redirect('/admin')->with('ok', 'কারণ জমা হয়েছে এবং অ্যাডমিনের কাছে পাঠানো হয়েছে।');
  }
  public function brk(Request $r) {
    if ($r->input('end')) { S::endBreak((int)auth()->id()); return response()->json(['ok' => true]); }
    if (S::open((int)auth()->id())) return response()->json(['ok' => false], 423);
    S::startBreak((int)auth()->id(), (string)$r->input('kind', 'other'), (int)$r->input('min', 15));
    return response()->json(['ok' => true]);
  }
  /** Owner / admin: tickets, per-staff totals and settings. */
  public function index(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $ready = S::ready();
    $rows = $ready ? DB::table('staff_idle_notices')->orderByRaw("case status when 'submitted' then 0 when 'open' then 1 else 2 end")->orderByDesc('id')->limit(150)->get() : collect();
    $sum = $ready ? DB::table('staff_idle_notices')->where('created_at', '>=', now()->subDays(30))->selectRaw("user_name, count(*) n, coalesce(sum(idle_minutes),0) m, sum(case when created_at >= ? then 1 else 0 end) today", [now('Asia/Dhaka')->startOfDay()->utc()])->groupBy('user_name')->orderByDesc('n')->get() : collect();
    return view('staff_idle_admin', ['rows' => $rows, 'sum' => $sum, 'ready' => $ready, 's' => fn($k, $d = '') => S::set($k, $d), 'roles' => DB::table('users')->whereNotIn('role', ['super', 'admin'])->distinct()->pluck('role')->filter()->values()]);
  }
  public function save(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $d = $r->validate(['limit' => 'required|integer|min:10|max:240', 'from' => 'required|integer|min:0|max:23', 'to' => 'required|integer|min:0|max:23', 'roles' => 'nullable|array', 'roles.*' => 'string|max:40']);
    Procure::put('staff_idle_limit', (string)$d['limit']); Procure::put('staff_idle_from', (string)$d['from']); Procure::put('staff_idle_to', (string)$d['to']);
    Procure::put('staff_idle_roles', implode(',', $d['roles'] ?? []));
    foreach (['enabled', 'need_waiting'] as $k) Procure::put('staff_idle_' . $k, $r->boolean($k) ? '1' : '0');
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? '', 'action' => 'staff.idle_settings', 'subject' => 'Staff idle settings: limit ' . $d['limit'] . ' min, duty ' . $d['from'] . '-' . $d['to'] . ', ' . ($r->boolean('enabled') ? 'on' : 'off'), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return back()->with('ok', 'সেভ হয়েছে।');
  }
  public function review(Request $r, $id) {
    abort_unless(ProfitFloor::admin(), 403);
    S::review((int)$id, (string)$r->input('verdict', 'accepted'), (string)$r->input('note', ''), (string)(auth()->user()->name ?? ''));
    return back()->with('ok', 'টিকিট নিষ্পত্তি হয়েছে।');
  }
}
