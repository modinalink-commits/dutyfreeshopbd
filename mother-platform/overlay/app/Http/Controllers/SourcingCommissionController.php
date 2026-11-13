<?php
namespace App\Http\Controllers;
use App\Support\SourcingCommission as SC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Sourcing commission: agreement terms, sourcing entries waiting for approval, and the monthly statement that feeds Payroll. */
class SourcingCommissionController extends Controller {
  private function role(): string { return (string)optional(auth()->user())->role; }
  private function boss(): bool { return in_array($this->role(), ['super', 'admin'], true); }
  private function checker(): bool { return in_array($this->role(), ['super', 'admin', 'manager'], true); }
  private function audit(string $act, string $subject): void { try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => optional(auth()->user())->name, 'action' => $act, 'subject' => mb_substr($subject, 0, 250), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {} }

  public function index(Request $r) {
    abort_unless(SC::ready(), 404);
    $me = auth()->id(); $all = DB::table('sourcing_agreements')->orderBy('id')->get();
    // a sourcing person sees only the own agreement; admin/manager see all
    $ags = $this->checker() ? $all : $all->where('user_id', $me)->values();
    abort_if($ags->isEmpty() && !$this->boss(), 403);
    $ag = $ags->firstWhere('id', (int)$r->query('a')) ?: $ags->first(); $mon = SC::month($r->query('month'));
    $st = $ag ? DB::table('sourcing_statements')->where('agreement_id', $ag->id)->where('month', $mon)->first() : null;
    $calc = null; $locked = false;
    if ($ag) {
      if ($st && $st->approved_at) { $snap = json_decode((string)$st->snapshot, true) ?: []; $locked = true; $calc = ['lines' => $snap['lines'] ?? [], 'totals' => ['gross_profit' => (float)$st->gross_profit, 'marketing_cost' => (float)$st->marketing_cost, 'carry_in' => (float)$st->carry_in, 'shareable' => (float)$st->shareable, 'staff_share' => (float)$st->staff_share, 'company_share' => (float)$st->company_share, 'carry_out' => (float)$st->carry_out, 'line_count' => (int)$st->line_count]]; }
      else $calc = SC::compute($ag, $mon);
    }
    $pending = collect(); $waiting = 0;
    if ($ag) {
      $pq = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.sourced_by', $ag->user_id);
      $pending = (clone $pq)->where('i.sourced_status', 'pending')->orderByDesc('i.sourced_at')->limit(200)->get(['i.id', 'i.order_id', 'i.name', 'i.qty', 'i.price', 'i.src_price', 'i.src_store', 'i.src_url', 'i.sourced_cost', 'i.sourced_at', 'i.vendor_ref', 'o.invoice', 'o.status']);
      $statuses = array_values(array_filter(array_map('trim', explode(',', (string)$ag->count_statuses)))) ?: ['Delivered'];
      $waiting = (clone $pq)->where('i.sourced_status', 'approved')->whereNotIn('o.status', array_merge($statuses, ['Cancel', 'Cancelled', 'Returned', 'Return Pending']))->count();
    }
    $stores = Schema::hasTable('stores') ? DB::table('stores')->orderBy('name')->get(['id', 'name']) : collect();
    $users = DB::table('users')->orderBy('name')->get(['id', 'name', 'email']);
    $emps = Schema::hasTable('employees') ? DB::table('employees')->orderBy('name')->get(['id', 'name']) : collect();
    $history = $ag ? DB::table('sourcing_statements')->where('agreement_id', $ag->id)->whereNotNull('approved_at')->orderByDesc('month')->limit(24)->get() : collect();
    return view('sourcing_commission', ['ags' => $ags, 'ag' => $ag, 'mon' => $mon, 'calc' => $calc, 'locked' => $locked, 'st' => $st, 'pending' => $pending, 'waiting' => $waiting, 'stores' => $stores, 'users' => $users, 'emps' => $emps, 'history' => $history, 'boss' => $this->boss(), 'checker' => $this->checker()]);
  }

  /** Create or edit an agreement (admin only). Past approved months keep the terms saved in their snapshot. */
  public function save(Request $r) {
    abort_unless($this->boss() && SC::ready(), 403);
    $d = $r->validate(['id' => 'nullable|integer', 'user_id' => 'required|integer|exists:users,id', 'employee_id' => 'nullable|integer', 'packaging_cost' => 'required|numeric|min:0|max:100000', 'marketing_cost' => 'required|numeric|min:0|max:100000000', 'share_pct' => 'required|numeric|min:0|max:100', 'count_statuses' => 'required|string|max:200', 'starts_on' => 'nullable|date', 'store_ids' => 'nullable|array', 'store_ids.*' => 'integer']);
    $u = DB::table('users')->where('id', $d['user_id'])->first();
    $row = ['user_id' => $d['user_id'], 'employee_id' => $d['employee_id'] ?? null, 'name' => $u->name ?: ('User #'.$u->id), 'packaging_cost' => $d['packaging_cost'], 'marketing_cost' => $d['marketing_cost'], 'share_pct' => $d['share_pct'], 'count_statuses' => $d['count_statuses'], 'starts_on' => $d['starts_on'] ?? null, 'store_ids' => json_encode(array_values(array_map('intval', $d['store_ids'] ?? []))),
      'deduct_discount' => $r->boolean('deduct_discount'), 'add_shipping_income' => $r->boolean('add_shipping_income'), 'carry_deficit' => $r->boolean('carry_deficit'), 'active' => $r->boolean('active'), 'updated_at' => now()];
    if (!empty($d['id'])) { DB::table('sourcing_agreements')->where('id', $d['id'])->update($row); $id = (int)$d['id']; } else $id = DB::table('sourcing_agreements')->insertGetId($row + ['created_at' => now()]);
    $this->audit('update', 'sourcing_agreement.'.$id.' pack='.$d['packaging_cost'].' mkt='.$d['marketing_cost'].' share='.$d['share_pct']);
    return redirect('/admin/sourcing-commission?a='.$id)->with('ok', 'Agreement saved.');
  }

  /** Approve or reject one sourcing entry (admin / manager). The buy price can be corrected while approving. */
  public function check(Request $r, $id) {
    abort_unless($this->checker() && SC::ready(), 403);
    $d = $r->validate(['do' => 'required|in:approve,reject', 'sourced_cost' => 'nullable|numeric|min:0|max:100000000']);
    $l = DB::table('order_items')->where('id', $id)->whereNotNull('sourced_by')->first(); abort_unless($l, 404);
    if (SC::locked($l)) return back()->with('ok', 'This line belongs to an approved month and is locked.');
    abort_if((int)$l->sourced_by === (int)auth()->id() && !$this->boss(), 403, 'You cannot approve your own entry.');
    $up = ['sourced_status' => $d['do'] === 'approve' ? 'approved' : 'rejected', 'sourced_checked_by' => auth()->id(), 'sourced_checked_at' => now()];
    if ($d['do'] === 'approve' && isset($d['sourced_cost'])) $up['sourced_cost'] = $d['sourced_cost'];
    DB::table('order_items')->where('id', $id)->update($up);
    $this->audit('update', 'sourcing_entry.'.$id.' '.$up['sourced_status'].' cost='.($up['sourced_cost'] ?? $l->sourced_cost));
    return back()->with('ok', 'Entry '.$up['sourced_status'].'.');
  }

  /** Lock the month and send the staff share to Payroll (admin only). */
  public function approve(Request $r) {
    abort_unless($this->boss() && SC::ready(), 403);
    $d = $r->validate(['agreement_id' => 'required|integer', 'month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);
    $ag = DB::table('sourcing_agreements')->where('id', $d['agreement_id'])->first(); abort_unless($ag, 404);
    $res = SC::approve($ag, $d['month'], (int)auth()->id());
    if ($res['ok']) $this->audit('approve', 'sourcing_statement.'.$ag->id.'.'.$d['month']);
    return back()->with('ok', $res['msg']);
  }
}
