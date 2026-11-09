<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\RecurringExpense as R;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecurringExpenseController extends Controller {
  private function chk(string $need = 'view') { abort_unless(P::can(Auth::user(), 'accounts', $need), 403); abort_unless(R::ready(), 503, 'Recurring expenses are not set up yet'); }
  public function index(Request $r) {
    $this->chk(); $today = R::today(); $m0 = $today->copy()->startOfMonth(); $m1 = $today->copy()->endOfMonth();
    $masters = DB::table('recurring_expenses')->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")->orderBy('name')->get();
    $acc = DB::table('money_accounts')->where('active', true)->orderBy('name')->get(['id', 'name']);
    $accName = $acc->pluck('name', 'id');
    $inst = DB::table('recurring_expense_instances as i')->join('recurring_expenses as m', 'm.id', '=', 'i.recurring_id')->orderByDesc('i.due_date')->orderByDesc('i.id')->limit(120)
      ->get(['i.*', 'm.name', 'm.category', 'm.expense_type', 'm.is_loan']);
    $month = $inst->filter(fn($x) => $x->due_date >= $m0->toDateString() && $x->due_date <= $m1->toDateString());
    $upcoming = [];
    foreach ($masters->where('status', 'active') as $m) foreach (R::occurrences($m, $today, $today->copy()->addDays(45)) as [$p, $d]) {
      if (DB::table('recurring_expense_instances')->where('recurring_id', $m->id)->where('billing_period', $p)->exists()) continue;
      [$a] = R::amountOn($m, $d); $upcoming[] = (object)['mid' => $m->id, 'period' => $p, 'acc' => $m->account_id, 'name' => $m->name, 'category' => $m->category, 'due' => $d, 'amount' => $m->is_loan ? ($m->principal + $m->interest + $m->fee ?: $a) : $a, 'auto' => $m->auto_post, 'type' => $m->expense_type];
    }
    usort($upcoming, fn($a, $b) => strcmp($a->due, $b->due));
    $owing = $inst->filter(fn($x) => $x->payment_status !== 'paid' && !in_array($x->status, ['skipped', 'cancelled']));
    $overdue = $owing->filter(fn($x) => $x->due_date < $today->toDateString());
    $monthUp = collect($upcoming)->filter(fn($x) => $x->due <= $m1->toDateString());
    $k = ['total' => $month->whereNotIn('status', ['skipped', 'cancelled'])->sum('amount') + $monthUp->sum('amount'), 'paid' => $month->where('payment_status', 'paid')->sum('amount'),
      'upcoming' => $monthUp->sum('amount'), 'overdue' => $overdue->sum('amount'),
      'loan' => $month->where('is_loan', 1)->whereNotIn('status', ['skipped', 'cancelled'])->sum('amount') + $monthUp->where('type', 'loan')->sum('amount'),
      'salary' => $month->where('expense_type', 'payroll')->sum('amount') + $monthUp->where('type', 'payroll')->sum('amount'),
      'subs' => $month->where('expense_type', 'subscription')->sum('amount') + $monthUp->where('type', 'subscription')->sum('amount')];
    $cats = DB::table('expense_purposes')->where('is_expense', true)->orderBy('name')->pluck('name');
    return view('recurring_expenses', compact('masters', 'acc', 'accName', 'inst', 'upcoming', 'owing', 'k', 'cats', 'today') + ['can' => P::can(Auth::user(), 'accounts', 'edit')]);
  }
  private function rules(): array {
    return ['name' => 'required|string|max:160', 'category' => 'required|string|max:80', 'expense_type' => 'required|in:fixed,variable,loan,payroll,subscription,utility', 'amount' => 'required|numeric|min:0',
      'frequency' => 'required|in:monthly,weekly,quarterly,half_yearly,yearly,custom', 'interval_months' => 'nullable|integer|min:1|max:36', 'pay_day' => 'required|integer|min:1|max:31', 'start_date' => 'required|date', 'end_date' => 'nullable|date',
      'account_id' => 'nullable|integer', 'expense_for' => 'nullable|string|max:40', 'store' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:1000',
      'loan_provider' => 'nullable|string|max:120', 'loan_amount' => 'nullable|numeric', 'principal' => 'nullable|numeric|min:0', 'interest' => 'nullable|numeric|min:0', 'fee' => 'nullable|numeric|min:0', 'remaining_balance' => 'nullable|numeric',
      'currency' => 'nullable|string|max:8', 'orig_amount' => 'nullable|numeric', 'exch_rate' => 'nullable|numeric'];
  }
  private function cols(Request $r, array $d): array {
    $loan = $d['expense_type'] === 'loan'; $pr = $loan ? (float)($d['principal'] ?? 0) : 0; $in = $loan ? (float)($d['interest'] ?? 0) : 0; $fee = $loan ? (float)($d['fee'] ?? 0) : 0;
    return ['name' => trim($d['name']), 'category' => trim($d['category']), 'expense_type' => $d['expense_type'], 'amount' => $loan && ($pr + $in + $fee) > 0 ? $pr + $in + $fee : (float)$d['amount'], 'frequency' => $d['frequency'], 'interval_months' => (int)($d['interval_months'] ?? 1) ?: 1,
      'pay_day' => (int)$d['pay_day'], 'start_date' => $d['start_date'], 'end_date' => $d['end_date'] ?? null, 'account_id' => ($d['account_id'] ?? null) ?: null, 'expense_for' => $d['expense_for'] ?? 'General Company', 'store' => $d['store'] ?? null, 'notes' => $d['notes'] ?? null,
      'auto_post' => $r->boolean('auto_post'), 'auto_deduct' => $r->boolean('auto_deduct') && !empty($d['account_id']), 'is_loan' => $loan, 'loan_provider' => $d['loan_provider'] ?? null, 'loan_amount' => $d['loan_amount'] ?? null, 'principal' => $pr, 'interest' => $in, 'fee' => $fee,
      'remaining_balance' => $d['remaining_balance'] ?? null, 'currency' => $d['currency'] ?: 'BDT', 'orig_amount' => $d['orig_amount'] ?? null, 'exch_rate' => $d['exch_rate'] ?? null];
  }
  public function store(Request $r) {
    $this->chk('edit'); $d = $r->validate($this->rules()); $c = $this->cols($r, $d);
    $id = DB::table('recurring_expenses')->insertGetId($c + ['status' => $r->boolean('draft') ? 'draft' : 'active', 'source' => $d['expense_type'] === 'payroll' ? 'payroll' : 'manual', 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('recurring_expense_amounts')->insert(['recurring_id' => $id, 'amount' => $c['amount'], 'principal' => $c['principal'], 'interest' => $c['interest'], 'fee' => $c['fee'], 'effective_from' => $c['start_date'], 'changed_by' => Auth::user()->name, 'reason' => 'Created', 'created_at' => now(), 'updated_at' => now()]);
    R::purpose($c['category']);
    $n = R::run();
    return back()->with('ok', 'Recurring expense saved'.($n ? ' · '.$n.' due item(s) posted now' : '').'.');
  }
  public function update(Request $r, $id) {
    $this->chk('edit'); $m = DB::table('recurring_expenses')->find($id); abort_unless($m, 404);
    $d = $r->validate($this->rules() + ['effective_from' => 'nullable|date', 'reason' => 'nullable|string|max:240']); $c = $this->cols($r, $d);
    $changed = abs($c['amount'] - (float)$m->amount) > 0.004 || abs($c['principal'] - (float)$m->principal) > 0.004 || abs($c['interest'] - (float)$m->interest) > 0.004 || abs($c['fee'] - (float)$m->fee) > 0.004;
    DB::table('recurring_expenses')->where('id', $id)->update($c + ['updated_at' => now()]);
    if ($changed) DB::table('recurring_expense_amounts')->insert(['recurring_id' => $id, 'amount' => $c['amount'], 'principal' => $c['principal'], 'interest' => $c['interest'], 'fee' => $c['fee'], 'effective_from' => $d['effective_from'] ?: R::today()->toDateString(), 'changed_by' => Auth::user()->name, 'reason' => $d['reason'] ?: 'Amount changed', 'created_at' => now(), 'updated_at' => now()]);
    R::purpose($c['category']);
    return back()->with('ok', 'Updated'.($changed ? ' — new amount applies from '.($d['effective_from'] ?: 'today').'; past entries are unchanged' : '').'.');
  }
  public function status(Request $r, $id) {
    $this->chk('edit'); $to = $r->validate(['to' => 'required|in:active,paused,ended'])['to']; abort_unless(DB::table('recurring_expenses')->where('id', $id)->exists(), 404);
    DB::table('recurring_expenses')->where('id', $id)->update(['status' => $to, 'updated_at' => now()]); return back()->with('ok', 'Status: '.$to.' (past entries unchanged).');
  }
  public function pay(Request $r, $iid) {
    $this->chk('edit'); $i = DB::table('recurring_expense_instances')->find($iid); abort_unless($i, 404); $m = DB::table('recurring_expenses')->find($i->recurring_id);
    $acc = (int)($r->input('account_id') ?: $i->account_id ?: $m->account_id);
    if (!$m->is_loan && is_numeric($r->input('amount')) && (float)$r->input('amount') > 0) DB::table('recurring_expense_instances')->where('id', $iid)->update(['amount' => (float)$r->input('amount'), 'updated_at' => now()]); // the real bill amount typed for this month
    DB::table('recurring_expense_instances')->where('id', $iid)->update(['status' => $i->status === 'due' ? 'posted' : $i->status, 'posted_at' => $i->posted_at ?: now()]);
    [$ok, $msg] = R::settle(DB::table('recurring_expense_instances')->find($iid), $m, $acc, Auth::user()->name, true);
    if (!$ok) DB::table('recurring_expense_instances')->where('id', $iid)->update(['payment_status' => 'failed', 'note' => $msg]);
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'Paid from the account.' : 'Not paid: '.$msg);
  }
  /** Pay an upcoming period straight from the list: creates that period's item and pays it from the chosen account (booked in the item's own month). */
  public function payUpcoming(Request $r, $id) {
    $this->chk('edit'); $d = $r->validate(['period' => 'required|string|max:20', 'due' => 'required|date', 'account_id' => 'nullable|integer']);
    $m = DB::table('recurring_expenses')->find($id); abort_unless($m, 404);
    R::post($m, $d['period'], $d['due']);
    $i = DB::table('recurring_expense_instances')->where('recurring_id', $m->id)->where('billing_period', $d['period'])->first(); abort_unless($i, 404);
    $acc = (int)($d['account_id'] ?? 0) ?: (int)($i->account_id ?: $m->account_id);
    if (!$m->is_loan && is_numeric($r->input('amount')) && (float)$r->input('amount') > 0) DB::table('recurring_expense_instances')->where('id', $i->id)->update(['amount' => (float)$r->input('amount'), 'updated_at' => now()]);
    DB::table('recurring_expense_instances')->where('id', $i->id)->update(['status' => $i->status === 'due' ? 'posted' : $i->status, 'posted_at' => $i->posted_at ?: now()]);
    [$ok, $msg] = R::settle(DB::table('recurring_expense_instances')->find($i->id), $m, $acc, Auth::user()->name, true);
    if (!$ok) DB::table('recurring_expense_instances')->where('id', $i->id)->update(['payment_status' => 'failed', 'note' => $msg]);
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'Paid from the account.' : 'Not paid: '.$msg);
  }
  public function skip(Request $r, $iid) {
    $this->chk('edit'); $i = DB::table('recurring_expense_instances')->find($iid); abort_unless($i && $i->payment_status !== 'paid', 404);
    DB::table('recurring_expense_instances')->where('id', $iid)->update(['status' => 'skipped', 'note' => 'Skipped by '.Auth::user()->name, 'updated_at' => now()]); return back()->with('ok', 'Skipped.');
  }
  public function run(Request $r) { $this->chk('edit'); $n = R::run(); return back()->with('ok', $n.' due item(s) posted.'); }
}
