<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Expense;
use App\Models\ExpenseClaim;
use App\Models\MoneyAccount;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\TripCalc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** HR & payroll. Salary data is confidential: Super Admin, Admin and Accounts only. */
class HrController extends Controller
{
    private const TZ = 'Asia/Dhaka';

    /** Cylinders sold on trips where this employee was the salesman, in one month. */
    public static function cylinders(Employee $e, string $ym): int
    {
        $from = $ym.'-01'; $to = Carbon::parse($from)->endOfMonth()->toDateString();
        return (int) Trip::query()->where('salesman_id', $e->id)->whereBetween('trip_date', [$from, $to])->where('status', '!=', 'draft')->get()->sum(fn ($t) => TripCalc::of($t)['soldTotal']);
    }

    public static function claimsFor(Employee $e)
    {
        return ExpenseClaim::query()->with('category')->where('payment', 'salary')->where('status', 'posted')->get()->filter(fn ($c) => ($c->extra['employee'] ?? null) === $e->name);
    }

    public function index()
    {
        $now = now()->timezone(self::TZ);
        $emps = Employee::query()->orderBy('department')->orderBy('name')->get();
        $active = $emps->where('is_active', true);
        $last = Payroll::query()->with('lines.employee')->whereIn('status', ['approved', 'paid'])->orderByDesc('month')->first();
        $bal = $emps->mapWithKeys(fn ($e) => [$e->id => $e->balances()]);
        $reimbCats = \App\Models\ExpenseCategory::query()->where('form', 'reimb')->pluck('id');
        $claims = ExpenseClaim::query()->with(['category', 'trip.vehicle'])->whereIn('category_id', $reimbCats)->whereIn('status', ['submitted', 'posted', 'draft'])->latest('id')->limit(30)->get();
        $approvedNov = (float) ExpenseClaim::query()->whereIn('category_id', $reimbCats)->whereIn('status', ['posted', 'paid'])->where('approved_at', '>=', $now->copy()->startOfMonth()->utc())->sum('amount');
        $depts = $last ? $last->lines->groupBy(fn ($l) => $l->employee->department ?: 'Other')->map(fn ($g) => $g->sum(fn ($l) => $l->gross()))->sortDesc() : collect();
        $vehRows = collect();
        $cylMonth = 0;
        if ($last) {
            $from = $last->month.'-01'; $to = Carbon::parse($from)->endOfMonth()->toDateString();
            $trips = Trip::query()->whereBetween('trip_date', [$from, $to])->where('status', '!=', 'draft')->get();
            $cylMonth = $trips->sum(fn ($t) => TripCalc::of($t)['soldTotal']);
            foreach (Vehicle::query()->with(['salesman', 'driver'])->orderBy('code')->get() as $v) {
                $ids = array_filter([$v->salesman_id, $v->driver_id]);
                if (! $ids) continue;
                $cost = $last->lines->whereIn('employee_id', $ids)->sum(fn ($l) => $l->gross());
                $sold = $trips->where('vehicle_id', $v->id)->sum(fn ($t) => TripCalc::of($t)['soldTotal']);
                $vehRows->push(['v' => $v->code, 'p' => collect([$v->salesman?->name, $v->driver?->name])->filter()->implode(' · '), 'c' => $cost, 'per' => $sold ? $cost / $sold : null]);
            }
        }
        $next = Payroll::query()->where('status', '!=', 'paid')->orderBy('month')->first();
        return view('hr.index', compact('emps', 'active', 'last', 'bal', 'claims', 'approvedNov', 'depts', 'vehRows', 'cylMonth', 'next', 'now'));
    }

    public function employee(Employee $employee)
    {
        $rows = EmployeeLedger::query()->where('employee_id', $employee->id)->orderBy('entry_date')->orderBy('id')->get();
        $line = PayrollLine::query()->with('payroll')->where('employee_id', $employee->id)->whereHas('payroll', fn ($q) => $q->whereIn('status', ['approved', 'paid']))->get()->sortByDesc(fn ($l) => $l->payroll->month)->first();
        return view('hr.employee', ['e' => $employee, 'rows' => $rows, 'bal' => $employee->balances(), 'line' => $line, 'accounts' => MoneyAccount::query()->orderBy('id')->get(),
            'vehicle' => Vehicle::query()->where('salesman_id', $employee->id)->orWhere('driver_id', $employee->id)->value('code'),
            'pendingClaims' => self::claimsFor($employee), 'cyl' => self::cylinders($employee, now()->timezone(self::TZ)->format('Y-m'))]);
    }

    public function saveEmployee(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'department' => ['nullable', 'string', 'max:60'], 'designation' => ['nullable', 'string', 'max:60'], 'joined_on' => ['nullable', 'date'],
            'basic' => ['nullable', 'numeric', 'min:0'], 'allowance' => ['nullable', 'numeric', 'min:0'], 'commission_rate' => ['nullable', 'numeric', 'min:0'],
            'bank_account' => ['nullable', 'string', 'max:60'], 'phone' => ['nullable', 'string', 'max:20'],
        ]);
        if (! $request->user()->isSuperAdmin()) unset($data['basic'], $data['allowance'], $data['commission_rate']);
        $old = $employee->monthly();
        $employee->update($data);
        if (abs($old - $employee->monthly()) > 0.004) AuditLog::record('salary_change', "{$employee->code} {$employee->name} ৳".number_format($old).' → ৳'.number_format($employee->monthly()));
        else AuditLog::record('employee_saved', "{$employee->code} {$employee->name}");
        return back()->with('ok', "{$employee->name} saved.");
    }

    public function entry(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['opening', 'advance', 'loan', 'advance_recovery', 'loan_recovery', 'adjustment'])], 'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'], 'account_id' => ['nullable', 'exists:money_accounts,id'], 'note' => ['required_if:kind,adjustment', 'nullable', 'string', 'max:255'],
        ]);
        $a = (float) $data['amount'];
        $row = ['employee_id' => $employee->id, 'entry_date' => $data['entry_date'], 'kind' => $data['kind'], 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id,
            'reference' => strtoupper(substr($data['kind'], 0, 3)).'-'.Carbon::parse($data['entry_date'])->format('dm').'-'.str_pad((string) (EmployeeLedger::query()->count() + 1), 3, '0', STR_PAD_LEFT)];
        $needAcc = in_array($data['kind'], ['advance', 'loan', 'advance_recovery', 'loan_recovery'], true);
        if ($needAcc && empty($data['account_id'])) return back()->withErrors(['amount' => 'Pick the money account the cash went out of / came into.']);
        $bal = $employee->balances();
        if ($data['kind'] === 'advance_recovery' && $a > $bal['advance'] + 0.004) return back()->withErrors(['amount' => 'Only ৳'.number_format($bal['advance']).' advance is open.']);
        if ($data['kind'] === 'loan_recovery' && $a > $bal['loan'] + 0.004) return back()->withErrors(['amount' => 'Only ৳'.number_format($bal['loan']).' loan is open.']);
        $row += match ($data['kind']) {
            'opening' => $a >= 0 ? ['earned' => $a] : ['deducted' => -$a],
            'advance' => ['advance_delta' => $a, 'account_id' => $data['account_id'], 'cash_out' => $a],
            'loan' => ['loan_delta' => $a, 'account_id' => $data['account_id'], 'cash_out' => $a],
            'advance_recovery' => ['advance_delta' => -$a, 'account_id' => $data['account_id'], 'cash_out' => -$a],
            'loan_recovery' => ['loan_delta' => -$a, 'account_id' => $data['account_id'], 'cash_out' => -$a],
            'adjustment' => $a >= 0 ? ['earned' => $a] : ['deducted' => -$a],
        };
        EmployeeLedger::create($row);
        AuditLog::record('employee_'.$data['kind'], "{$employee->code} {$employee->name} ৳{$a}");
        return back()->with('ok', EmployeeLedger::KINDS[$data['kind']].' saved.');
    }

    // ---------------- Payroll run ----------------

    private function month(Request $request): string
    {
        $m = (string) $request->query('m', $request->input('m', ''));
        return preg_match('/^\d{4}-\d{2}$/', $m) ? $m : now()->timezone(self::TZ)->format('Y-m');
    }

    public function payroll(Request $request)
    {
        $m = $this->month($request);
        $p = Payroll::query()->with(['lines.employee', 'preparer', 'checker', 'approver', 'account'])->where('month', $m)->first();
        $changes = \App\Models\AuditLog::query()->with('user')->where('action', 'salary_change')->where('created_at', '>=', Carbon::parse($m.'-01', self::TZ)->subMonth()->utc())->latest('id')->limit(10)->get();
        return view('hr.payroll', ['m' => $m, 'p' => $p, 'changes' => $changes, 'accounts' => MoneyAccount::query()->orderBy('id')->get(),
            'count' => Employee::query()->where('is_active', true)->whereNotNull('basic')->count()]);
    }

    public function prepare(Request $request)
    {
        $m = $this->month($request);
        try {
            DB::transaction(function () use ($m, $request) {
                $p = Payroll::query()->lockForUpdate()->firstOrCreate(['month' => $m], ['status' => 'draft']);
                if (! in_array($p->status, ['draft', 'checked'], true)) throw new RuntimeException("Payroll for {$m} is already approved.");
                $days = Carbon::parse($m.'-01')->daysInMonth;
                $emps = Employee::query()->where('is_active', true)->whereNotNull('basic')->get();
                if ($emps->isEmpty()) throw new RuntimeException('Set the monthly salary of each employee first (open the employee and fill Salary structure).');
                foreach ($emps as $e) {
                    $line = PayrollLine::query()->firstOrNew(['payroll_id' => $p->id, 'employee_id' => $e->id]);
                    $cyl = self::cylinders($e, $m);
                    $line->fill(['days' => $line->exists ? $line->days : $days, 'basic' => $e->basic ?? 0, 'allowance' => $e->allowance ?? 0, 'cylinders' => $cyl,
                        'commission' => round($cyl * (float) ($e->commission_rate ?? 0), 2), 'reimbursement' => (float) self::claimsFor($e)->sum('amount')]);
                    if (! $line->exists) $line->advance_recovery = 0;
                    $line->net = $line->computeNet();
                    $line->save();
                }
                PayrollLine::query()->where('payroll_id', $p->id)->whereNotIn('employee_id', $emps->pluck('id'))->delete();
                $p->update(['status' => 'draft', 'prepared_by' => $request->user()->id, 'prepared_at' => now(), 'checked_by' => null, 'checked_at' => null]);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['m' => \App\Support\Ui::msg($e)]);
        }
        AuditLog::record('payroll_prepared', $m);
        return redirect()->route('hr.payroll', ['m' => $m])->with('ok', "Payroll {$m} prepared.");
    }

    public function saveLines(Request $request, Payroll $payroll)
    {
        if (! in_array($payroll->status, ['draft', 'checked'], true)) return back()->withErrors(['m' => 'This payroll is approved and locked.']);
        $data = $request->validate(['l' => ['array'], 'l.*.days' => ['nullable', 'integer', 'min:0', 'max:31'], 'l.*.leave_days' => ['nullable', 'integer', 'min:0', 'max:31'],
            'l.*.overtime_hours' => ['nullable', 'numeric', 'min:0'], 'l.*.overtime' => ['nullable', 'numeric', 'min:0'], 'l.*.bonus' => ['nullable', 'numeric', 'min:0'],
            'l.*.advance_recovery' => ['nullable', 'numeric', 'min:0'], 'l.*.loan_recovery' => ['nullable', 'numeric', 'min:0'], 'l.*.deductions' => ['nullable', 'numeric', 'min:0']]);
        foreach ($payroll->lines()->with('employee')->get() as $line) {
            $in = $data['l'][$line->id] ?? null;
            if (! $in) continue;
            $line->fill(array_map(fn ($v) => $v ?? 0, $in));
            $b = $line->employee->balances();
            if ((float) $line->advance_recovery > $b['advance'] + 0.004) return back()->withErrors(['m' => "{$line->employee->name} has only ৳".number_format($b['advance']).' advance open.']);
            if ((float) $line->loan_recovery > $b['loan'] + 0.004) return back()->withErrors(['m' => "{$line->employee->name} has only ৳".number_format($b['loan']).' loan open.']);
            $line->net = $line->computeNet();
            if ($line->net < 0) return back()->withErrors(['m' => "{$line->employee->name}'s net pay would be negative."]);
            $line->save();
        }
        $payroll->update(['status' => 'draft', 'checked_by' => null, 'checked_at' => null]);
        AuditLog::record('payroll_edited', $payroll->month);
        return back()->with('ok', 'Salary sheet saved.');
    }

    public function check(Request $request, Payroll $payroll)
    {
        if ($payroll->status !== 'draft') return back()->withErrors(['m' => 'Only a prepared payroll can be checked.']);
        if ($payroll->prepared_by === $request->user()->id && ! $request->user()->isSuperAdmin()) return back()->withErrors(['m' => 'Someone other than the preparer must check it.']);
        $payroll->update(['status' => 'checked', 'checked_by' => $request->user()->id, 'checked_at' => now()]);
        AuditLog::record('payroll_checked', $payroll->month);
        return back()->with('ok', "Payroll {$payroll->month} checked.");
    }

    public function approve(Request $request, Payroll $payroll)
    {
        if ($payroll->status !== 'checked') return back()->withErrors(['m' => 'Payroll must be checked before approval.']);
        $end = Carbon::parse($payroll->month.'-01')->endOfMonth()->toDateString();
        DB::transaction(function () use ($payroll, $end, $request) {
            $ref = 'PAY-'.str_replace('-', '', substr($payroll->month, 2));
            foreach ($payroll->lines()->with('employee')->get() as $l) {
                $post = fn ($kind, $f) => EmployeeLedger::create(['employee_id' => $l->employee_id, 'entry_date' => $end, 'kind' => $kind, 'reference' => $ref, 'payroll_id' => $payroll->id, 'created_by' => $request->user()->id] + $f);
                if ((float) $l->basic + (float) $l->allowance > 0) $post('salary', ['earned' => (float) $l->basic + (float) $l->allowance, 'note' => 'Basic + allowance · '.$payroll->month]);
                if ((float) $l->commission > 0) $post('commission', ['earned' => $l->commission, 'note' => $l->cylinders.' cylinders']);
                if ((float) $l->overtime > 0) $post('overtime', ['earned' => $l->overtime, 'note' => $l->overtime_hours.' h']);
                if ((float) $l->bonus > 0) $post('bonus', ['earned' => $l->bonus]);
                if ((float) $l->reimbursement > 0) $post('reimbursement', ['earned' => $l->reimbursement, 'note' => 'Approved expense claims']);
                if ((float) $l->advance_recovery > 0) $post('advance_recovery', ['deducted' => $l->advance_recovery, 'advance_delta' => -$l->advance_recovery]);
                if ((float) $l->loan_recovery > 0) $post('loan_recovery', ['deducted' => $l->loan_recovery, 'loan_delta' => -$l->loan_recovery]);
                if ((float) $l->deductions > 0) $post('deduction', ['deducted' => $l->deductions]);
            }
            $payroll->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        });
        AuditLog::record('payroll_approved', $payroll->month.' gross ৳'.number_format($payroll->load('lines')->gross()));
        return back()->with('ok', "Payroll {$payroll->month} approved and posted to the employee ledgers.");
    }

    public function pay(Request $request, Payroll $payroll)
    {
        $data = $request->validate(['account_id' => ['required', 'exists:money_accounts,id']]);
        if ($payroll->status !== 'approved') return back()->withErrors(['m' => 'Approve the payroll before paying.']);
        DB::transaction(function () use ($payroll, $data, $request) {
            $ref = 'PMT-'.now()->timezone(self::TZ)->format('dm').'-'.substr($payroll->month, 5);
            $day = now()->timezone(self::TZ)->toDateString();
            foreach ($payroll->lines()->with('employee')->get() as $l) {
                if ((float) $l->net <= 0) continue;
                // Reimbursement claims are paid as their own expense entries, so they are never counted as salary.
                foreach (self::claimsFor($l->employee)->filter(fn ($c) => $c->approved_at && $payroll->prepared_at && $c->approved_at <= $payroll->prepared_at) as $c) {
                    $e = Expense::create(['expense_date' => $c->expense_date->toDateString(), 'head' => $c->category->name, 'amount' => $c->amount, 'account_id' => $data['account_id'],
                        'note' => $c->claim_no.' with salary '.$payroll->month, 'created_by' => $request->user()->id, 'claim_id' => $c->id]);
                    $c->update(['status' => 'paid', 'paid_by' => $request->user()->id, 'paid_at' => now(), 'expense_id' => $e->id, 'account_id' => $data['account_id']]);
                }
                EmployeeLedger::create(['employee_id' => $l->employee_id, 'entry_date' => $day, 'kind' => 'payment', 'reference' => $ref, 'deducted' => $l->net, 'payroll_id' => $payroll->id,
                    'account_id' => $data['account_id'], 'cash_out' => (float) $l->net - (float) $l->reimbursement, 'created_by' => $request->user()->id]);
            }
            $payroll->update(['status' => 'paid', 'paid_by' => $request->user()->id, 'paid_at' => now(), 'account_id' => $data['account_id']]);
        });
        AuditLog::record('payroll_paid', $payroll->month);
        return back()->with('ok', "Payroll {$payroll->month} paid.");
    }
}
