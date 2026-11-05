<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\ExpenseRecurring;
use App\Models\ExpenseRule;
use App\Models\MoneyAccount;
use App\Models\Route as RouteModel;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Expense management: entry with approval, dashboard, budgets, recurring drafts and settings. */
class ExpenseController extends Controller
{
    private const TZ = 'Asia/Dhaka';

    public static function costCenters(): array
    {
        $c = ['Head office', 'Main warehouse'];
        foreach (Vehicle::query()->orderBy('code')->pluck('code') as $v) $c[] = 'Vehicle '.$v;
        foreach (RouteModel::query()->orderBy('name')->pluck('name') as $r) $c[] = 'Route '.$r;
        foreach (Employee::query()->where('is_active', true)->orderBy('name')->pluck('name') as $e) $c[] = 'Employee — '.$e;
        return $c;
    }

    /** Make drafts from recurring rules that are due. Nothing is paid automatically. */
    public static function runRecurring(): int
    {
        $today = now()->timezone(self::TZ)->toDateString();
        $n = 0;
        foreach (ExpenseRecurring::query()->where('is_active', true)->whereIn('every', ['month', 'year'])->whereNotNull('next_date')->where('next_date', '<=', $today)->get() as $r) {
            DB::transaction(function () use ($r, &$n) {
                $r = ExpenseRecurring::query()->lockForUpdate()->find($r->id);
                if (! $r->next_date || $r->next_date->toDateString() > now()->timezone(self::TZ)->toDateString()) return;
                ExpenseClaim::create(['claim_no' => ExpenseClaim::nextNo(), 'category_id' => $r->category_id, 'expense_date' => $r->next_date, 'amount' => $r->amount ?? 0,
                    'cost_center' => $r->cost_center, 'payment' => 'payable', 'description' => $r->name.' · '.$r->next_date->format('F Y'), 'status' => 'draft', 'recurring_id' => $r->id]);
                $r->update(['next_date' => $r->every === 'year' ? $r->next_date->copy()->addYear() : $r->next_date->copy()->addMonthNoOverflow()]);
                $n++;
            });
        }
        return $n;
    }

    /** Every expense in a date range as one list: paid entries plus approved bills not yet paid. */
    public static function rows(string $from, string $to)
    {
        $cats = ExpenseCategory::query()->get();
        $byName = $cats->whereNotNull('parent_id')->keyBy(fn ($c) => strtolower($c->name));
        $other = $cats->firstWhere('code', 'EX-901');
        $trips = Trip::query()->with('vehicle')->get()->keyBy('id');
        $claims = ExpenseClaim::query()->whereBetween('expense_date', [$from, $to])->get()->keyBy('id');
        $out = collect();
        foreach (Expense::query()->whereBetween('expense_date', [$from, $to])->get() as $x) {
            $cl = $x->claim_id ? ($claims[$x->claim_id] ?? ExpenseClaim::find($x->claim_id)) : null;
            $cat = $cl ? $cats->firstWhere('id', $cl->category_id) : ($byName[strtolower($x->head)] ?? $other);
            $veh = $x->trip_id ? $trips[$x->trip_id]?->vehicle?->code : null;
            $cc = $cl?->cost_center ?? ($veh ? 'Vehicle '.$veh : null);
            if (! $veh && $cc && str_starts_with($cc, 'Vehicle ')) $veh = substr($cc, 8);
            $out->push(['date' => $x->expense_date->toDateString(), 'amount' => (float) $x->amount, 'cat' => $cat, 'group' => $cat?->parent_id ?? $cat?->id, 'cc' => $cc, 'veh' => $veh, 'paid' => true]);
        }
        foreach ($claims->where('status', 'posted') as $cl) {
            $cat = $cats->firstWhere('id', $cl->category_id);
            $veh = $cl->cost_center && str_starts_with($cl->cost_center, 'Vehicle ') ? substr($cl->cost_center, 8) : null;
            $out->push(['date' => $cl->expense_date->toDateString(), 'amount' => (float) $cl->amount, 'cat' => $cat, 'group' => $cat?->parent_id ?? $cat?->id, 'cc' => $cl->cost_center, 'veh' => $veh, 'paid' => false]);
        }
        $sal = $cats->firstWhere('code', 'EX-401');
        foreach (\App\Models\Payroll::query()->with('lines')->whereIn('status', ['approved', 'paid'])->get() as $p) {
            $d = Carbon::parse($p->month.'-01')->endOfMonth()->toDateString();
            if ($d < $from || $d > $to) continue;
            $out->push(['date' => $d, 'amount' => $p->gross(), 'cat' => $sal, 'group' => $sal?->parent_id, 'cc' => null, 'veh' => null, 'paid' => $p->status === 'paid']);
        }
        return $out;
    }

    public function index(Request $request)
    {
        self::runRecurring();
        $now = now()->timezone(self::TZ);
        $range = in_array($request->query('r'), ['month', 'last', 'year'], true) ? $request->query('r') : 'month';
        [$from, $to, $label] = match ($range) {
            'last' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), $now->copy()->subMonthNoOverflow()->format('F Y')],
            'year' => [$now->copy()->startOfYear(), $now->copy(), 'Year '.$now->year.' to date'],
            default => [$now->copy()->startOfMonth(), $now->copy(), $now->format('F Y').' (1–'.$now->day.')'],
        };
        $groups = ExpenseCategory::query()->whereNull('parent_id')->with('children')->orderBy('code')->get();
        $rows = self::rows($from->toDateString(), $to->toDateString());
        if ($g = (int) $request->query('cat')) $rows = $rows->where('group', $g);
        if ($cc = $request->query('cc')) $rows = $rows->where('cc', $cc);
        $all = fn ($a, $b) => self::rows($a, $b)->sum('amount');
        $today = $now->toDateString();
        $todayRows = self::rows($today, $today);
        $pending = ExpenseClaim::query()->where('status', 'submitted')->get();
        $unpaid = ExpenseClaim::query()->where('status', 'posted')->get();
        $monthRows = $range === 'month' ? $rows : self::rows($now->copy()->startOfMonth()->toDateString(), $today);
        $pace = $now->day / $now->daysInMonth;
        $budgets = $groups->map(function ($g) use ($monthRows) {
            $b = (float) $g->children->sum('monthly_budget') + (float) $g->monthly_budget;
            return ['n' => $g->name, 'a' => (float) $monthRows->where('group', $g->id)->sum('amount'), 'b' => $b];
        })->filter(fn ($x) => $x['b'] > 0)->values();
        $byGroup = $groups->map(fn ($g) => ['n' => $g->name, 'v' => (float) $rows->where('group', $g->id)->sum('amount')])->filter(fn ($x) => $x['v'] > 0)->sortByDesc('v')->values();
        $veh = Vehicle::query()->orderBy('code')->pluck('code')->map(fn ($v) => ['v' => $v, 't' => (float) $rows->where('veh', $v)->sum('amount')]);
        $trend = collect([2, 1, 0])->map(function ($k) use ($now) {
            $m = $now->copy()->subMonthsNoOverflow($k);
            $a = $m->copy()->startOfMonth(); $b = $k ? $m->copy()->endOfMonth() : $now->copy();
            return ['m' => $m->format('F').($k ? '' : ' (1–'.$now->day.')'), 'v' => (float) self::rows($a->toDateString(), $b->toDateString())->sum('amount')];
        });
        $status = $request->query('st');
        $claims = ExpenseClaim::query()->with(['category', 'creator', 'approver', 'account'])->when($status, fn ($q) => $q->where('status', $status))
            ->when(! $status, fn ($q) => $q->whereIn('status', ['draft', 'submitted', 'posted'])->orWhere('updated_at', '>=', now()->subDays(7)))->latest('id')->limit(60)->get();

        return view('expenses.index', compact('range', 'from', 'to', 'label', 'groups', 'rows', 'todayRows', 'pending', 'unpaid', 'budgets', 'byGroup', 'veh', 'trend', 'claims', 'pace', 'now')
            + ['month' => $all($now->copy()->startOfMonth()->toDateString(), $today), 'last' => $all($now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()),
               'ytd' => $all($now->copy()->startOfYear()->toDateString(), $today), 'ccs' => self::costCenters(), 'accounts' => MoneyAccount::query()->orderBy('id')->get(),
               'alert1' => (int) Setting::get('exp_alert1', '80'), 'alert2' => (int) Setting::get('exp_alert2', '100')]);
    }

    public function create(Request $request)
    {
        $claim = $request->query('id') ? ExpenseClaim::findOrFail((int) $request->query('id')) : null;
        if ($claim && $claim->status !== 'draft') return redirect()->route('expenses.index')->withErrors(['amount' => "{$claim->claim_no} is already submitted."]);
        $cats = ExpenseCategory::query()->whereNotNull('parent_id')->where('is_active', true)->with('parent')->orderBy('code')->get();
        $monthStart = now()->timezone(self::TZ)->startOfMonth()->toDateString();
        $used = self::rows($monthStart, now()->timezone(self::TZ)->toDateString())->groupBy(fn ($r) => $r['cat']?->id)->map->sum('amount');
        return view('expenses.entry', ['claim' => $claim, 'cats' => $cats, 'ccs' => self::costCenters(), 'used' => $used,
            'accounts' => MoneyAccount::query()->orderBy('id')->get(), 'vehicles' => Vehicle::query()->orderBy('code')->get(),
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->get(),
            'trips' => Trip::query()->with('vehicle')->where('status', 'dispatched')->orWhere('status', 'returned')->orderByDesc('trip_date')->get(),
            'maxMb' => (int) Setting::get('exp_receipt_mb', '5')]);
    }

    public function store(Request $request)
    {
        $maxKb = 1024 * (int) Setting::get('exp_receipt_mb', '5');
        $data = $request->validate([
            'id' => ['nullable', 'exists:expense_claims,id'], 'category_id' => ['required', 'exists:expense_categories,id'], 'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'], 'cost_center' => ['nullable', 'string', 'max:80'],
            'payment' => ['required', Rule::in(array_keys(ExpenseClaim::PAYMENTS))], 'account_id' => ['nullable', 'exists:money_accounts,id'], 'trip_id' => ['nullable', 'exists:trips,id'],
            'description' => ['nullable', 'string', 'max:255'], 'extra' => ['array'], 'extra.*' => ['nullable', 'string', 'max:120'],
            'split_cc' => ['array'], 'split_cc.*' => ['nullable', 'string', 'max:80'], 'split_pct' => ['array'], 'split_pct.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.$maxKb], 'action' => ['required', Rule::in(['draft', 'submit'])],
        ]);
        $cat = ExpenseCategory::findOrFail($data['category_id']);
        if ($cat->parent_id === null) return back()->withInput()->withErrors(['category_id' => 'Pick a category, not a group.']);
        if ($cat->source) return back()->withInput()->withErrors(['category_id' => "{$cat->name} is entered only in ".ExpenseCategory::SOURCES[$cat->source].'.']);
        if ($data['payment'] === 'trip' && empty($data['trip_id'])) return back()->withInput()->withErrors(['trip_id' => 'Pick the trip whose collection paid this.']);
        $splits = [];
        foreach ($data['split_cc'] ?? [] as $i => $cc) {
            $p = (float) ($data['split_pct'][$i] ?? 0);
            if ($cc && $p > 0) $splits[] = ['cc' => $cc, 'pct' => $p, 'amount' => round((float) $data['amount'] * $p / 100, 2)];
        }
        if ($splits && abs(array_sum(array_column($splits, 'pct')) - 100) > 0.01) return back()->withInput()->withErrors(['split_pct' => 'Split percentages must add up to 100%.']);
        $claim = ! empty($data['id']) ? ExpenseClaim::findOrFail($data['id']) : new ExpenseClaim(['claim_no' => ExpenseClaim::nextNo(), 'created_by' => $request->user()->id]);
        if ($claim->exists && $claim->status !== 'draft') return back()->withErrors(['amount' => 'Only a draft can be changed.']);
        $path = $claim->receipt_path;
        if ($request->hasFile('receipt')) $path = $request->file('receipt')->store('receipts', 'local');
        $claim->fill([
            'category_id' => $cat->id, 'expense_date' => $data['expense_date'], 'amount' => $data['amount'], 'cost_center' => $splits ? 'Split — '.count($splits).' cost centers' : ($data['cost_center'] ?? null),
            'payment' => $data['payment'], 'account_id' => $data['account_id'] ?? null, 'trip_id' => $data['payment'] === 'trip' ? $data['trip_id'] : null,
            'description' => $data['description'] ?? null, 'extra' => array_filter($data['extra'] ?? [], fn ($v) => $v !== null && $v !== ''), 'splits' => $splits ?: null,
            'receipt_path' => $path, 'status' => $data['action'] === 'submit' ? 'submitted' : 'draft', 'submitted_at' => $data['action'] === 'submit' ? now() : null,
        ])->save();
        AuditLog::record($data['action'] === 'submit' ? 'expense_submitted' : 'expense_draft', "{$claim->claim_no} {$cat->name} ৳{$claim->amount}");
        return redirect()->route('expenses.index')->with('ok', "{$claim->claim_no} ".($data['action'] === 'submit' ? 'submitted for approval.' : 'saved as draft.'));
    }

    private function overBudget(ExpenseClaim $claim): ?string
    {
        $cat = $claim->category;
        if (! $cat->monthly_budget) return null;
        $m = Carbon::parse($claim->expense_date);
        $used = self::rows($m->copy()->startOfMonth()->toDateString(), $m->copy()->endOfMonth()->toDateString())->filter(fn ($r) => $r['cat']?->id === $cat->id)->sum('amount');
        $after = $used + (float) $claim->amount;
        return $after > (float) $cat->monthly_budget ? "{$cat->name} would reach ৳".number_format($after).' of its ৳'.number_format((float) $cat->monthly_budget).' budget.' : null;
    }

    public function approve(Request $request, ExpenseClaim $claim)
    {
        $user = $request->user();
        if ($claim->status !== 'submitted') return back()->withErrors(['amount' => "{$claim->claim_no} is not waiting for approval."]);
        $claim->load('category.parent');
        $duty = $claim->payment === 'trip' || $claim->category->parent?->code === 'EX-300';
        $need = ExpenseRule::approverFor((float) $claim->amount, $duty);
        $over = $this->overBudget($claim);
        if ($over && Setting::get('exp_over_budget', 'allow') === 'block') return back()->withErrors(['amount' => $over.' Over-budget expenses are blocked in Expense settings.']);
        if ($over) $need = 'super_admin';
        if ($need === 'super_admin' && ! $user->isSuperAdmin()) return back()->withErrors(['amount' => "{$claim->claim_no} needs Super Admin approval".($over ? ': '.$over : ' (approval limit).')]);
        if ($claim->created_by === $user->id && ! $user->isSuperAdmin()) return back()->withErrors(['amount' => 'You cannot approve your own expense.']);
        try {
            DB::transaction(function () use ($claim, $user) {
                $claim->update(['status' => 'posted', 'approved_by' => $user->id, 'approved_at' => now()]);
                if ($claim->immediate()) $this->pay($claim, $claim->account_id, $user->id);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }
        AuditLog::record('expense_approved', "{$claim->claim_no} ৳{$claim->amount}".($claim->fresh()->status === 'paid' ? ' · paid' : ' · posted, unpaid'));
        return back()->with('ok', "{$claim->claim_no} approved.");
    }

    private function pay(ExpenseClaim $claim, ?int $accountId, int $userId): void
    {
        $type = ['cash' => 'cash', 'trip' => 'cash', 'bank' => 'bank', 'mobile' => 'mobile'][$claim->payment] ?? null;
        $acc = $accountId ? MoneyAccount::find($accountId) : MoneyAccount::query()->when($type, fn ($q) => $q->where('type', $type))->orderByDesc('is_default')->first();
        if (! $acc) throw new RuntimeException('Add a '.($type ? MoneyAccount::TYPES[$type] : 'money').' account in Accounts & profit first.');
        if ($claim->payment === 'trip') {
            $trip = Trip::find($claim->trip_id);
            if (! $trip || ! in_array($trip->status, ['dispatched', 'returned'], true)) throw new RuntimeException('That trip\'s cash is already settled. Pay from office cash instead.');
        }
        $e = Expense::create(['expense_date' => $claim->expense_date->toDateString(), 'head' => $claim->category->name, 'amount' => $claim->amount, 'account_id' => $acc->id,
            'note' => trim($claim->claim_no.' '.($claim->description ?? '')), 'created_by' => $userId, 'trip_id' => $claim->trip_id, 'from_collection' => $claim->payment === 'trip', 'claim_id' => $claim->id]);
        $claim->update(['status' => 'paid', 'paid_by' => $userId, 'paid_at' => now(), 'expense_id' => $e->id, 'account_id' => $acc->id]);
    }

    public function markPaid(Request $request, ExpenseClaim $claim)
    {
        $data = $request->validate(['account_id' => ['required', 'exists:money_accounts,id']]);
        if ($claim->status !== 'posted') return back()->withErrors(['amount' => 'Only an approved, unpaid expense can be paid.']);
        DB::transaction(fn () => $this->pay($claim->load('category'), (int) $data['account_id'], $request->user()->id));
        AuditLog::record('expense_paid', "{$claim->claim_no} ৳{$claim->amount}");
        return back()->with('ok', "{$claim->claim_no} paid.");
    }

    public function reject(Request $request, ExpenseClaim $claim)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if (! in_array($claim->status, ['submitted', 'draft'], true)) return back()->withErrors(['amount' => 'Only a draft or pending expense can be rejected.']);
        $claim->update(['status' => 'rejected', 'reject_reason' => $data['reason']]);
        AuditLog::record('expense_rejected', "{$claim->claim_no} · {$data['reason']}");
        return back()->with('ok', "{$claim->claim_no} rejected.");
    }

    public function receipt(ExpenseClaim $claim)
    {
        abort_unless($claim->receipt_path && Storage::disk('local')->exists($claim->receipt_path), 404);
        return Storage::disk('local')->response($claim->receipt_path);
    }

    // ---------------- Settings ----------------

    public function setup()
    {
        $groups = ExpenseCategory::query()->whereNull('parent_id')->with(['children' => fn ($q) => $q->orderBy('code')])->orderBy('code')->get();
        return view('expenses.setup', ['groups' => $groups, 'recurring' => ExpenseRecurring::query()->with('category')->orderBy('id')->get(), 'rules' => ExpenseRule::query()->orderBy('id')->get(),
            'ccs' => self::costCenters(), 'cats' => ExpenseCategory::query()->whereNotNull('parent_id')->orderBy('code')->get(),
            'alert1' => Setting::get('exp_alert1', '80'), 'alert2' => Setting::get('exp_alert2', '100'), 'over' => Setting::get('exp_over_budget', 'allow'), 'mb' => Setting::get('exp_receipt_mb', '5')]);
    }

    public function saveCategory(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:expense_categories,id'], 'code' => ['required', 'string', 'max:12', Rule::unique('expense_categories', 'code')->ignore($request->input('id'))],
            'name' => ['required', 'string', 'max:80'], 'parent_id' => ['nullable', 'exists:expense_categories,id'], 'gl_account' => ['nullable', 'string', 'max:60'],
            'type' => ['nullable', Rule::in(ExpenseCategory::TYPES)], 'form' => ['required', Rule::in(array_keys(ExpenseCategory::FORMS))], 'approval_rule' => ['nullable', 'string', 'max:60'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $c = ! empty($data['id']) ? ExpenseCategory::findOrFail($data['id']) : new ExpenseCategory();
        unset($data['id']);
        $c->fill($data + ['is_active' => $request->boolean('is_active', true)])->save();
        AuditLog::record('expense_category', "{$c->code} {$c->name}".($c->monthly_budget ? ' budget ৳'.$c->monthly_budget : ''));
        return back()->with('ok', "{$c->code} {$c->name} saved.");
    }

    public function saveRecurring(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'category_id' => ['required', 'exists:expense_categories,id'], 'amount' => ['nullable', 'numeric', 'min:0'],
            'every' => ['required', Rule::in(array_keys(ExpenseRecurring::EVERY))], 'day' => ['nullable', 'integer', 'min:1', 'max:28'], 'cost_center' => ['nullable', 'string', 'max:80'], 'next_date' => ['nullable', 'date']]);
        $r = ExpenseRecurring::create($data);
        AuditLog::record('expense_recurring', $r->name);
        return back()->with('ok', "Recurring {$r->name} saved.");
    }

    public function saveRule(Request $request)
    {
        $data = $request->validate(['who' => ['required', 'string', 'max:60'], 'up_to' => ['nullable', 'numeric', 'min:0'], 'above' => ['nullable', 'numeric', 'min:0'],
            'verified_by' => ['required', 'string', 'max:60'], 'approver' => ['required', Rule::in(array_keys(ExpenseRule::APPROVERS))]]);
        ExpenseRule::create($data);
        AuditLog::record('expense_rule', $data['who']);
        return back()->with('ok', 'Approval rule saved.');
    }

    public function deleteRule(ExpenseRule $rule)
    {
        $rule->delete();
        AuditLog::record('expense_rule_removed', $rule->who);
        return back()->with('ok', 'Rule removed.');
    }

    public function saveAlerts(Request $request)
    {
        $data = $request->validate(['alert1' => ['required', 'integer', 'min:1', 'max:200'], 'alert2' => ['required', 'integer', 'min:1', 'max:300'],
            'over' => ['required', Rule::in(['allow', 'block'])], 'mb' => ['required', 'integer', 'min:1', 'max:20']]);
        Setting::put('exp_alert1', (string) $data['alert1']); Setting::put('exp_alert2', (string) $data['alert2']);
        Setting::put('exp_over_budget', $data['over']); Setting::put('exp_receipt_mb', (string) $data['mb']);
        AuditLog::record('expense_alerts', json_encode($data));
        return back()->with('ok', 'Budget alerts saved.');
    }
}
