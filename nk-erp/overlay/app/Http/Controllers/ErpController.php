<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CompanyEntry;
use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\DutySession;
use App\Models\Employee;
use App\Models\ErpRecord;
use App\Models\Expense;
use App\Models\MoneyAccount;
use App\Models\PayrollLine;
use App\Models\Route as RouteModel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Trip;
use App\Models\TripAdjustment;
use App\Models\Vehicle;
use App\Support\Money;
use App\Support\TripCalc;
use App\Support\Ui;
use Illuminate\Http\Request;

/**
 * The 15 ERP modules from the design, on one shared layout: KPIs, tabbed forms, a draft → approval flow,
 * activity history, reports and a records table. Numbers come from posted data; nothing is invented.
 */
class ErpController extends Controller
{
    private const TZ = 'Asia/Dhaka';

    public static function defs(): array
    {
        static $d = null;
        return $d ??= json_decode((string) file_get_contents(resource_path('erp/modules.json')), true);
    }

    private function recs(string $m, ?int $form = null)
    {
        return ErpRecord::query()->where('module', $m)->when($form !== null, fn ($q) => $q->where('form', $form));
    }

    private function open(string $m, int $form): int
    {
        return $this->recs($m, $form)->whereNotIn('status', ['posted', 'closed', 'rejected'])->count();
    }

    /** Options for a select field, from the business's own lists. */
    public static function options(string $label): array
    {
        $l = strtolower($label);
        $sizes = ['12 KG', '35 KG', '45 KG'];
        return match (true) {
            str_contains($l, 'brand · size') || str_contains($l, 'brand/size') => Brand::query()->where('is_active', true)->orderBy('position')->orderBy('code')->get()->flatMap(fn ($b) => collect($b->sizes ?? [12, 35, 45])->map(fn ($s) => $b->name.' · '.$s.' KG'))->all(),
            str_contains($l, 'brand') => Brand::query()->where('is_active', true)->orderBy('position')->orderBy('code')->pluck('name')->all(),
            $l === 'size' || str_contains($l, 'cylinder size') => $sizes,
            str_contains($l, 'vehicle') => Vehicle::query()->orderBy('code')->get()->map(fn ($v) => $v->code.' · '.$v->reg_no)->all(),
            str_contains($l, 'customer') || str_contains($l, 'party') || str_contains($l, 'shop') => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->pluck('name')->all(),
            str_contains($l, 'route') || str_contains($l, 'zone') => RouteModel::query()->orderBy('name')->pluck('name')->all(),
            str_contains($l, 'supplier') => array_filter([\App\Models\Setting::get('supplier_name', 'AYGas').' ('.\App\Models\Setting::get('supplier_code', '').')']),
            str_contains($l, 'warehouse') || $l === 'from' || $l === 'to' || str_contains($l, 'location') => array_merge(['Main warehouse'], ErpRecord::query()->where('module', 'm10')->where('form', 0)->get()->map(fn ($r) => $r->val('Warehouse name') ?: $r->record_no)->all(), Vehicle::query()->orderBy('code')->get()->map(fn ($v) => 'Vehicle '.$v->code)->all()),
            str_contains($l, 'employee') || str_contains($l, 'person') || str_contains($l, 'inspector') || str_contains($l, 'assigned') || str_contains($l, 'officer') || str_contains($l, 'driver') || str_contains($l, 'salesman') || str_contains($l, 'by') || str_contains($l, 'trainer') || str_contains($l, 'manager') => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name')->all(),
            str_contains($l, 'account') => MoneyAccount::query()->orderBy('id')->pluck('name')->all(),
            str_contains($l, 'priority') => ['Low', 'Normal', 'High', 'Urgent'],
            str_contains($l, 'condition') => ['Good', 'Valve problem', 'Damaged', 'Expired test date'],
            str_contains($l, 'ownership') || str_contains($l, 'owner') => ['Company', 'Customer', 'Supplier'],
            default => [],
        };
    }

    private function kpis(string $m): array
    {
        $now = now()->timezone(self::TZ);
        $mStart = $now->copy()->startOfMonth()->utc(); $today = $now->copy()->startOfDay()->utc();
        $tk = fn ($n) => Ui::tk($n);
        $godown = fn ($col) => (int) StockMovement::query()->whereNull('trip_id')->sum($col);
        switch ($m) {
            case 'm01':
                $with = (int) CustomerCylinder::query()->sum('qty_delta');
                $bySize = CustomerCylinder::query()->selectRaw('size_kg, sum(qty_delta) q')->groupBy('size_kg')->pluck('q', 'size_kg');
                $tracked = $godown('full_delta') + $godown('empty_delta') + $godown('damaged_delta') + (int) StockMovement::query()->whereNotNull('trip_id')->sum(\Illuminate\Support\Facades\DB::raw('full_delta + empty_delta')) + $with;
                return [[$tracked, 'batch-based · 12/35/45 kg'], [$with, $bySize->map(fn ($q, $s) => $s.' kg '.$q)->implode(' · ') ?: '—'], [$godown('damaged_delta'), 'not for sale'], [TripAdjustment::query()->where('detail', 'like', '%lost%')->count(), 'written off on trips']];
            case 'm02':
                $j = $this->recs('m02', 1)->where('created_at', '>=', $mStart)->get();
                $dr = $j->sum(fn ($r) => $r->num('Debit amount') ?: $r->num('Amount')); $cr = $j->sum(fn ($r) => $r->num('Credit amount') ?: $r->num('Amount'));
                $bank = collect(Money::balances())->first(fn ($b) => $b['account']->type === 'bank');
                return [[$j->count(), 'manual journals'], [abs($dr - $cr) < 0.01 ? 'Balanced' : $tk($dr - $cr).' off', 'debit = credit'], [$bank ? $tk($bank['balance']) : 'No bank account', $bank['account']->name ?? 'add in Accounts'], [$this->open('m02', 2), 'bank lines to match']];
            case 'm03':
                $held = $this->recs('m03', 0)->where('status', 'posted')->get()->sum(fn ($r) => $r->num('Amount')) - $this->recs('m03', 1)->where('status', 'posted')->get()->sum(fn ($r) => $r->num('Amount'));
                return [[$tk($held), 'refundable, not income'], [$tk(CompanyEntry::query()->where('kind', 'deposit')->where('deposit_kind', 'security')->sum('amount')), 'cylinder security with company'],
                    [$tk($this->recs('m03', 1)->where('created_at', '>=', $mStart)->get()->sum(fn ($r) => $r->num('Amount'))), $now->format('F')], [$this->open('m03', 1), 'waiting']];
            case 'm04':
                $t = Trip::query()->whereDate('trip_date', $now->toDateString())->where('status', '!=', 'draft')->get()->map(fn ($x) => TripCalc::of($x));
                return [[Trip::query()->where('status', 'closed')->where('closed_at', '>=', $today)->count(), 'of '.$t->count().' trips today'], [$tk($t->sum(fn ($c) => $c['cashDiff'] ?? 0)), 'counted − expected'],
                    [$t->sum(fn ($c) => collect($c['stockDiff'])->sum(fn ($z) => $z['vehFull'] + $z['vehEmpty'])), 'cylinders not yet matched'], [$this->open('m04', 1), 'shortage is not misconduct until investigated']];
            case 'm05':
                return [[$this->open('m05', 0), 'purchase orders'], [$this->recs('m05', 1)->where('created_at', '>=', $mStart)->count(), 'goods receiving notes'], [$this->recs('m05', 0)->whereIn('status', ['approved'])->count(), 'approved, not received'], [$this->recs('m05', 2)->count(), 'purchase returns']];
            case 'm06':
                return [[$this->recs('m06', 0)->where('created_at', '>=', now()->subDays(7))->count(), 'inspections'], [$godown('damaged_delta'), 'set aside in godown'], [$this->open('m06', 2), 'incidents'], [$this->open('m06', 1), 'quarantine actions']];
            case 'm07':
                $exp = $this->recs('m07', 1)->get()->filter(fn ($r) => collect($r->data)->contains(fn ($v, $k) => str_contains(strtolower($k), 'expiry') && $v && strtotime($v) && strtotime($v) <= strtotime('+30 days')))->count();
                $fuel = $this->recs('m07', 0)->where('created_at', '>=', $mStart)->get();
                $km = $fuel->sum(fn ($r) => $r->num('Distance (km)') ?: $r->num('Km')); $lit = $fuel->sum(fn ($r) => $r->num('Litres') ?: $r->num('Quantity (litre)'));
                return [[Vehicle::query()->count(), Vehicle::query()->where('status', 'Maintenance')->count().' in maintenance'], [$tk(Expense::query()->where('head', 'Fuel')->where('expense_date', '>=', $now->copy()->startOfMonth()->toDateString())->sum('amount')), 'from vehicle expenses'],
                    [$exp, 'in the next 30 days'], [$lit > 0 && $km > 0 ? number_format($km / $lit, 1) : '—', $lit > 0 ? 'from fuel entries' : 'needs km and litres in fuel entries']];
            case 'm08':
                $cs = Customer::query()->where('is_active', true)->get();
                $dues = $cs->map(fn ($c) => ['c' => $c, 'd' => $c->due()])->filter(fn ($x) => $x['d'] > 0);
                $old = $dues->filter(fn ($x) => optional(CustomerLedger::query()->where('customer_id', $x['c']->id)->where('kind', 'sale')->oldest('id')->first())->created_at?->lt(now()->subDays(30)));
                return [[$cs->where('credit_limit', '>', 0)->count(), 'credit turned on'], [$tk($dues->sum('d')), $dues->count().' customers'], [$tk($old->sum('d')), $old->count().' customers'], [$this->open('m08', 2), 'waiting for approval']];
            case 'm09':
                return [[(int) StockMovement::query()->where('kind', 'damaged')->where('created_at', '>=', $mStart)->sum('damaged_delta'), 'set aside'], [$this->open('m09', 1), 'loss reports'], [$this->open('m09', 2), 'with supplier'], [TripAdjustment::query()->where('detail', 'like', '%write off%')->count(), 'approved write-offs']];
            case 'm10':
                return [[1 + $this->recs('m10', 0)->count(), 'Main warehouse + '.$this->recs('m10', 0)->count()], [$this->open('m10', 2), 'transfers on the way'], [$this->recs('m10', 1)->where('created_at', '>=', $mStart)->count(), $now->format('F')], ['—', 'shown when receiving counts differ']];
            case 'm11':
                $act = Employee::query()->where('is_active', true)->get();
                $comm = $act->sum(fn ($e) => HrController::cylinders($e, $now->format('Y-m')) * (float) ($e->commission_rate ?? 0));
                return [[$act->count(), 'active'], [DutySession::query()->where('started_at', '>=', $today)->distinct('employee_id')->count('employee_id'), 'started duty in the app'], [$tk($comm), 'cylinders × rate'], [$tk($act->sum(fn ($e) => $e->balances()['advance'])), 'salary advances']];
            case 'm12':
                $t = $this->recs('m12', 0)->get();
                $open = $t->whereNotIn('status', ['closed', 'rejected', 'posted']);
                return [[$open->count(), 'tickets'], [$open->filter(fn ($r) => $r->created_at->lt(now()->subDays(3)))->count(), 'older than 3 days'], [$t->whereIn('status', ['closed', 'posted'])->filter(fn ($r) => $r->updated_at->gte(now()->subDays(7)))->count(), 'this week'],
                    [$t->groupBy(fn ($r) => $r->val('Customer'))->filter(fn ($g, $k) => $k && $g->count() > 1)->count(), 'customers with 2+ tickets']];
            case 'm13':
                $sug = $this->suggest();
                return [[collect($sug)->where('risk', 'High')->count(), 'sizes'], [collect($sug)->sum('sug'), 'cylinders'], ['—', 'shown after a full month of sales'], [$this->open('m05', 0), 'purchase orders']];
            case 'm14':
                return [[AuditLog::query()->where('created_at', '>=', $today)->count(), 'recorded'], [\App\Models\Setting::get('backup_last') ?: 'Not set up', 'database backup'], [\App\Models\Setting::get('backup_failed', '—'), 'last 30 days'], [\App\Models\Setting::get('restore_test_last') ?: 'Never', 'restore test']];
            case 'm15':
                $lic = $this->recs('m15', 0)->get();
                return [[$lic->count(), 'licenses'], [$lic->filter(fn ($r) => collect($r->data)->contains(fn ($v, $k) => str_contains(strtolower($k), 'expiry') && $v && strtotime($v) && strtotime($v) <= strtotime('+60 days')))->count(), 'renew in time'], [$this->open('m15', 3), 'from incidents'], [$this->recs('m15', 2)->where('created_at', '>=', $now->copy()->startOfYear()->utc())->count(), 'training records']];
        }
        return [];
    }

    /** Reorder suggestion per size: 7-day average sales against what is in the godown. */
    private function suggest(): array
    {
        $own = Brand::query()->where('for_sale', true)->orderBy('id')->value('id');
        $out = [];
        foreach (Brand::SIZES as $s) {
            $avail = $own ? StockMovement::balance($own, $s)['full'] : 0;
            $sold = (int) SaleItem::query()->whereIn('sale_id', Sale::query()->where('stage', '!=', 'voided')->where('created_at', '>=', now()->subDays(7))->pluck('id'))->where('size_kg', $s)->whereIn('category', ['refill', 'new_package'])->sum('qty');
            $po = $this->recs('m05', 0)->whereIn('status', ['approved', 'submitted', 'checked'])->get()->filter(fn ($r) => str_contains($r->val('Brand · size'), $s.' KG'))->sum(fn ($r) => $r->num('Quantity'));
            $need = max(0, (int) ceil($sold * 2 - $avail - $po));
            $out[] = ['p' => 'Refill '.$s.' kg', 'avail' => $avail, 'd7' => $sold, 'po' => $po, 'sug' => $need, 'risk' => $sold === 0 ? 'Low' : ($avail < $sold ? 'High' : ($avail < $sold * 2 ? 'Medium' : 'Low'))];
        }
        return $out;
    }

    /** Records table: the design's columns, filled from real data where the module already has it. */
    private function table(string $m, array $def, string $q): array
    {
        $cols = $def['cols'];
        $rows = [];
        switch ($m) {
            case 'm01':
                foreach (StockMovement::query()->with('brand')->latest('id')->limit(40)->get() as $x) {
                    $qty = abs($x->full_delta ?: $x->empty_delta ?: $x->damaged_delta ?: $x->company_delta);
                    $rows[] = [[$x->ref_no, $x->created_at->timezone(self::TZ)->format('d-m'), ($x->brand?->name ?? '').' · '.$x->size_kg.' kg', $qty, $x->trip_id && ($x->full_delta + $x->empty_delta) < 0 ? 'Vehicle' : ($x->trip_id ? 'Warehouse' : '—'), $x->trip_id ? ($x->full_delta + $x->empty_delta > 0 ? 'Vehicle' : 'Warehouse') : '—', StockMovement::KINDS[$x->kind] ?? $x->kind], $x->reversal_of ? 'Reversal' : 'Posted'];
                }
                break;
            case 'm04':
                foreach (Trip::query()->with(['vehicle', 'salesman'])->whereIn('status', ['settled', 'closed'])->latest('id')->limit(30)->get() as $t) {
                    $c = TripCalc::of($t);
                    $rows[] = [[$t->trip_no, $t->vehicle->code, $t->salesman?->name ?? '—', Ui::tk($c['expected']), $c['counted'] !== null ? Ui::tk($c['counted']) : '—', collect($c['stockDiff'])->sum(fn ($z) => $z['vehFull'] + $z['vehEmpty'])], $t->status === 'closed' ? 'Closed' : TripCalc::stage($t, $c)];
                }
                break;
            case 'm07':
                foreach (Vehicle::query()->with('driver')->orderBy('code')->get() as $v) {
                    $fuel = Expense::query()->where('head', 'Fuel')->whereIn('trip_id', Trip::query()->where('vehicle_id', $v->id)->pluck('id'))->where('expense_date', '>=', now()->startOfMonth()->toDateString())->sum('amount');
                    $last = $this->recs('m07', 2)->get()->filter(fn ($r) => str_starts_with($r->val('Vehicle'), $v->code))->last();
                    $doc = $this->recs('m07', 1)->get()->filter(fn ($r) => str_starts_with($r->val('Vehicle') ?: $r->val('Vehicle ID'), $v->code))->last();
                    $exp = $doc ? collect($doc->data)->first(fn ($val, $k) => str_contains(strtolower($k), 'expiry') && $val) : null;
                    $rows[] = [[$v->code, $v->reg_no ?: '—', $v->driver?->name ?? '—', Ui::tk($fuel), $last?->created_at?->format('d-m-Y') ?? '—', $exp ?: '—'], $v->status];
                }
                break;
            case 'm08':
                foreach (Customer::query()->where('is_active', true)->orderBy('name')->get() as $c) {
                    $due = $c->due();
                    if ($due <= 0 && (float) $c->credit_limit <= 0) continue;
                    $b = [0, 0, 0];
                    $left = $due;
                    foreach (CustomerLedger::query()->where('customer_id', $c->id)->where('kind', 'sale')->latest('id')->get() as $s) {
                        if ($left <= 0) break;
                        $amt = min($left, (float) $s->debit); $left -= $amt;
                        $age = $s->created_at->diffInDays(now(), true);
                        $b[$age <= 15 ? 0 : ($age <= 30 ? 1 : 2)] += $amt;
                    }
                    $rows[] = [[$c->name, Ui::tk($c->credit_limit), Ui::tk($due), Ui::tk($b[0]), Ui::tk($b[1]), Ui::tk($b[2])], (float) $c->credit_limit <= 0 ? 'Credit off' : ($due > (float) $c->credit_limit ? 'Over limit' : 'Within limit')];
                }
                break;
            case 'm10':
                $own = Brand::query()->where('for_sale', true)->orderBy('id')->value('id');
                $b = fn ($s) => $own ? StockMovement::balance($own, $s)['full'] : 0;
                $rows[] = [['Main warehouse', '—', $b(12), $b(35), $b(45), (int) StockMovement::query()->whereNull('trip_id')->sum('empty_delta')], 'Active'];
                foreach ($this->recs('m10', 0)->get() as $r) $rows[] = [[$r->val('Warehouse name') ?: $r->record_no, $r->val('Manager') ?: '—', '—', '—', '—', '—'], ErpRecord::STATUS[$r->status]];
                break;
            case 'm11':
                foreach (PayrollLine::query()->with(['employee', 'payroll'])->latest('id')->limit(40)->get() as $l) {
                    $rows[] = [[$l->employee->name.' · '.$l->payroll->month, \App\Models\Employee::ROLES[$l->employee->role] ?? '', $l->days, Ui::tk((float) $l->basic + (float) $l->allowance), Ui::tk($l->commission), Ui::tk($l->net)], \App\Models\Payroll::STATUS[$l->payroll->status]];
                }
                break;
            case 'm13':
                foreach ($this->suggest() as $s) $rows[] = [[$s['p'], $s['avail'], $s['d7'], $s['po'], $s['sug'], $s['risk']], $s['sug'] > 0 ? 'Order' : 'Enough'];
                break;
            case 'm14':
                foreach (AuditLog::query()->with('user')->latest('id')->limit(40)->get() as $a) {
                    $rows[] = [[$a->created_at->timezone(self::TZ)->format('d-m H:i'), $a->user?->name ?? 'System', \Illuminate\Support\Str::before($a->action, '_'), $a->action, \Illuminate\Support\Str::limit((string) $a->details, 40), '—'], 'Logged'];
                }
                break;
        }
        $recs = $this->recs($m)->with('creator')->latest('id')->limit(60)->get();
        $recRows = $recs->map(function ($r) use ($cols, $def) {
            $cells = [];
            foreach ($cols as $c) {
                $lc = strtolower($c);
                $v = match (true) {
                    in_array($lc, ['id', 'journal', 'ticket', 'movement', 'closing', 'po / grn', 'license'], true) => $r->record_no,
                    $lc === 'date' || $lc === 'opened' => $r->created_at->timezone(self::TZ)->format('d-m'),
                    $lc === 'prepared by' || $lc === 'user' => $r->creator?->name ?? '—',
                    default => collect($r->data)->first(fn ($val, $k) => str_contains(strtolower($k), $lc) || str_contains($lc, strtolower($k))) ?? '',
                };
                $cells[] = $v === '' ? '—' : $v;
            }
            return [$cells, ErpRecord::STATUS[$r->status], $r];
        })->all();
        if ($q !== '') {
            $f = fn ($row) => str_contains(strtolower(implode(' ', $row[0]).' '.$row[1]), strtolower($q));
            $rows = array_values(array_filter($rows, $f)); $recRows = array_values(array_filter($recRows, $f));
        }
        return [$rows, $recRows];
    }

    public function index(Request $request, ?string $m = null)
    {
        $defs = self::defs();
        $m = isset($defs[$m]) ? $m : 'm01';
        $def = $defs[$m];
        $tab = max(0, min(count($def['forms']) - 1, (int) $request->query('tab', 0)));
        $edit = $request->query('r') ? ErpRecord::query()->where('module', $m)->find((int) $request->query('r')) : null;
        if ($edit) $tab = $edit->form;
        [$rows, $recRows] = $this->table($m, $def, trim((string) $request->query('q', '')));
        $hist = AuditLog::query()->with('user')->where('action', 'like', 'erp_'.$m.'%')->latest('id')->limit(8)->get();
        $nextNo = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', explode('·', $def['forms'][$tab]['tab'])[1] ?? 'REC'), 0, 3)).'-'.now()->timezone(self::TZ)->format('dm').'-'.str_pad((string) ($this->recs($m)->count() + 1), 3, '0', STR_PAD_LEFT);
        return view('erp.module', ['m' => $m, 'def' => $def, 'defs' => $defs, 'tab' => $tab, 'kpis' => $this->kpis($m), 'rows' => $rows, 'recRows' => $recRows, 'hist' => $hist, 'edit' => $edit, 'nextNo' => $nextNo]);
    }

    public function store(Request $request, string $m)
    {
        $defs = self::defs();
        abort_unless(isset($defs[$m]), 404);
        $data = $request->validate(['form' => ['required', 'integer', 'min:0', 'max:5'], 'f' => ['array'], 'f.*' => ['nullable', 'string', 'max:500'], 'action' => ['required', 'in:draft,submit'],
            'id' => ['nullable', 'exists:erp_records,id'], 'file' => ['nullable', 'file', 'max:6144', 'mimes:jpg,jpeg,png,webp,pdf']]);
        $form = $defs[$m]['forms'][$data['form']] ?? abort(422);
        $vals = [];
        foreach ($form['fields'] as $i => $fl) if ($fl['t'] !== 'auto' && $fl['t'] !== 'file') $vals[$fl['l']] = $data['f'][$i] ?? null;
        foreach ($form['fields'] as $i => $fl) if (! $fl['adv'] && $fl['t'] !== 'auto' && $fl['t'] !== 'file' && $data['action'] === 'submit' && ($vals[$fl['l']] ?? '') === '') return back()->withInput()->withErrors(['f' => '"'.$fl['l'].'" is required to submit.']);
        $rec = ! empty($data['id']) ? ErpRecord::findOrFail($data['id']) : new ErpRecord(['module' => $m, 'form' => $data['form'], 'record_no' => $request->input('no') ?: strtoupper($m).'-'.now()->format('dmHis'), 'created_by' => $request->user()->id]);
        if ($rec->exists && $rec->status !== 'draft') return back()->withErrors(['f' => 'Only a draft can be edited. Post an adjustment instead.']);
        if (ErpRecord::query()->where('record_no', $rec->record_no)->where('id', '!=', $rec->id ?? 0)->exists()) $rec->record_no .= '-'.random_int(10, 99);
        $trail = $rec->trail ?? [];
        $trail[] = ['s' => $data['action'] === 'submit' ? 'submitted' : 'draft', 'by' => $request->user()->name, 'at' => now()->timezone(self::TZ)->format('d-m H:i')];
        $rec->fill(['data' => $vals, 'status' => $data['action'] === 'submit' ? 'submitted' : 'draft', 'trail' => $trail]);
        if ($request->hasFile('file')) $rec->file = $request->file('file')->store('erp', 'local');
        $rec->save();
        AuditLog::record('erp_'.$m.'_'.($data['action'] === 'submit' ? 'submitted' : 'draft'), $rec->record_no.' · '.$form['tab']);
        return redirect()->route('erp.module', ['m' => $m, 'tab' => $data['form']])->with('ok', $rec->record_no.($data['action'] === 'submit' ? ' submitted.' : ' saved as draft.'));
    }

    public function step(Request $request, ErpRecord $rec)
    {
        $data = $request->validate(['to' => ['required', 'in:checked,approved,posted,closed,rejected'], 'reason' => ['nullable', 'string', 'max:255']]);
        $role = $request->user()->role;
        $order = ['draft', 'submitted', 'checked', 'approved', 'posted', 'closed'];
        $need = ['checked' => ['super_admin', 'admin', 'manager', 'accounts'], 'approved' => ['super_admin', 'admin', 'manager'], 'posted' => ['super_admin', 'admin'], 'closed' => ['super_admin', 'admin', 'manager'], 'rejected' => ['super_admin', 'admin', 'manager']][$data['to']];
        abort_unless(in_array($role, $need, true), 403);
        if ($data['to'] !== 'rejected' && array_search($data['to'], $order, true) !== array_search($rec->status, $order, true) + 1) return back()->withErrors(['f' => 'That step is not next for '.$rec->record_no.'.']);
        if (in_array($data['to'], ['approved', 'posted'], true) && $rec->created_by === $request->user()->id && $role !== 'super_admin') return back()->withErrors(['f' => 'Someone other than the maker must approve.']);
        $trail = $rec->trail ?? [];
        $trail[] = ['s' => $data['to'], 'by' => $request->user()->name, 'at' => now()->timezone(self::TZ)->format('d-m H:i'), 'r' => $data['reason'] ?? null];
        $rec->update(['status' => $data['to'], 'trail' => $trail]);
        AuditLog::record('erp_'.$rec->module.'_'.$data['to'], $rec->record_no);
        return back()->with('ok', $rec->record_no.' → '.ErpRecord::STATUS[$data['to']].'.');
    }

    public function file(ErpRecord $rec)
    {
        abort_unless($rec->file && \Illuminate\Support\Facades\Storage::disk('local')->exists($rec->file), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->response($rec->file);
    }

    public function overview()
    {
        return view('erp.overview', ['defs' => self::defs(), 'counts' => ErpRecord::query()->selectRaw('module, count(*) n')->groupBy('module')->pluck('n', 'module')]);
    }
}
