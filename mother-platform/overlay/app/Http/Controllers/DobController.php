<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin approval of customer date-of-birth correction requests (the customer can save a DOB once; later changes need approval). */
class DobController extends Controller
{
  public function index(Request $r) {
    $st = in_array($r->query('status'), ['pending', 'approved', 'rejected'], true) ? $r->query('status') : 'pending';
    $rows = DB::table('dob_change_requests as d')->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')->where('d.status', $st)->orderByDesc('d.id')->limit(200)
      ->get(['d.*', 'c.name', 'c.phone', 'c.email']);
    $n = DB::table('dob_change_requests')->selectRaw("status, count(*) n")->groupBy('status')->pluck('n', 'status')->all();
    return view('dob_requests', ['rows' => $rows, 'st' => $st, 'n' => $n]);
  }
  public function decide(Request $r, $id) {
    $q = DB::table('dob_change_requests')->where('id', $id)->where('status', 'pending')->first(); if (!$q) return redirect('/admin/dob-requests')->with('ok', 'Already decided');
    $ok = $r->input('action') === 'approve'; $note = mb_substr(trim((string)$r->input('note')), 0, 300); $by = (string)(optional(auth()->user())->name ?? 'admin');
    DB::transaction(function () use ($q, $ok, $note, $by) {
      DB::table('dob_change_requests')->where('id', $q->id)->update(['status' => $ok ? 'approved' : 'rejected', 'admin_note' => $note ?: null, 'decided_by' => $by, 'decided_at' => now(), 'updated_at' => now()]);
      if ($ok) DB::table('customers')->where('id', $q->customer_id)->update(['dob' => $q->new_dob, 'updated_at' => now()]);
    });
    try { \App\Support\Procure::audit('dob.' . ($ok ? 'approved' : 'rejected'), 'Customer #' . $q->customer_id . ' DOB request', $q->old_dob, $ok ? $q->new_dob : null); } catch (\Throwable $e) {}
    return redirect('/admin/dob-requests')->with('ok', $ok ? 'অনুমোদিত — জন্মতারিখ আপডেট হয়েছে' : 'প্রত্যাখ্যাত');
  }
}
