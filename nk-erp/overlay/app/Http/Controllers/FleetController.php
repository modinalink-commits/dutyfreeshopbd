<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\MoneyAccount;
use App\Models\Route as RouteModel;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\TripCalc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Route dashboard, vehicles / staff / routes, and vehicle profit. */
class FleetController extends Controller
{
    public static function day(Request $r): string
    {
        $d = (string) $r->query('d', '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : now()->timezone('Asia/Dhaka')->toDateString();
    }

    public function dashboard(Request $request)
    {
        $day = self::day($request);
        $vehicles = Vehicle::query()->with(['route', 'driver', 'salesman'])->orderBy('code')->get();
        $trips = Trip::query()->with(['vehicle', 'route', 'driver', 'salesman'])->whereDate('trip_date', $day)->orderBy('id')->get();
        $rows = []; $T = ['loaded' => 0, 'sold' => 0, 'empty' => 0, 'cash' => 0.0, 'sales' => 0.0, 'credit' => 0.0, 'short' => 0.0, 'exp' => 0.0, 'pending' => 0, 'missingFull' => 0, 'missingEmpty' => 0, 'cost' => 0.0, 'unrated' => 0];
        $stages = [];
        foreach ($trips as $t) {
            $c = TripCalc::of($t);
            $stage = TripCalc::stage($t, $c);
            $stages[$t->id] = ['trip' => $t, 'c' => $c, 'stage' => $stage];
            if ($t->status === 'draft') continue;
            $T['loaded'] += $c['loadedTotal']; $T['sold'] += $c['soldTotal']; $T['empty'] += $c['collectedTotal'];
            $T['cash'] += $c['collection']; $T['sales'] += $c['totalSales']; $T['credit'] += $c['credit']; $T['exp'] += $c['expTotal']; $T['pending'] += $c['pendingTotal'];
            if ($c['cashDiff'] !== null && $c['cashDiff'] < 0) $T['short'] += -$c['cashDiff'];
            foreach ($c['stockDiff'] as $z) { if ($t->status !== 'dispatched') { $T['missingFull'] += max(0, $z['vehFull']); $T['missingEmpty'] += max(0, $z['vehEmpty']); } }
            $T['cost'] += $c['cost']['amount']; $T['unrated'] += $c['cost']['unrated'];
        }
        foreach ($vehicles as $v) {
            $s = collect($stages)->filter(fn ($x) => $x['trip']->vehicle_id === $v->id)->last();
            $rows[] = ['v' => $v, 's' => $s];
        }
        $active = collect($stages)->filter(fn ($x) => $x['trip']->status !== 'draft');
        $live = collect($stages)->first(fn ($x) => $x['trip']->status === 'dispatched');
        $liveSales = $live ? Sale::query()->with(['items', 'customer'])->where('trip_id', $live['trip']->id)->where('stage', '!=', 'voided')->latest('id')->limit(6)->get() : collect();

        return view('route.dash', compact('day', 'vehicles', 'rows', 'T', 'active', 'stages', 'live', 'liveSales'));
    }

    public function vehicles(Request $request)
    {
        $vehicles = Vehicle::query()->with(['route', 'driver', 'salesman'])->orderBy('code')->get();
        $sel = $request->query('new') ? null : ($vehicles->firstWhere('id', (int) $request->query('v')) ?? $vehicles->first());
        $staff = Employee::query()->orderBy('role')->orderBy('name')->get();
        $routes = RouteModel::query()->orderBy('name')->get();
        $monthStart = now()->timezone('Asia/Dhaka')->startOfMonth()->toDateString();
        $stats = null;
        if ($sel) {
            $mt = Trip::query()->where('vehicle_id', $sel->id)->where('trip_date', '>=', $monthStart)->where('status', '!=', 'draft')->get();
            $sold = 0; $net = 0.0;
            foreach ($mt as $t) { $c = TripCalc::of($t); $sold += $c['soldTotal']; $net += $c['totalSales'] - $c['cost']['amount'] - $c['expTotal']; }
            $stats = ['trips' => $mt->count(), 'sold' => $sold, 'net' => $net];
        }
        $routeRows = $routes->map(function ($r) use ($vehicles) {
            $tr = Trip::query()->where('route_id', $r->id)->where('status', '!=', 'draft')->get();
            $sold = $tr->sum(fn ($t) => TripCalc::of($t)['soldTotal']);
            $custIds = Sale::query()->whereIn('trip_id', $tr->pluck('id'))->whereNotNull('customer_id')->distinct()->pluck('customer_id');
            $due = Customer::query()->whereIn('id', $custIds)->get()->sum(fn ($c) => max(0, $c->due()));
            return ['r' => $r, 'v' => $vehicles->where('route_id', $r->id)->pluck('code')->implode(', ') ?: '—', 'avg' => $tr->count() ? round($sold / $tr->count()) : null, 'due' => $due];
        });

        return view('route.vehicles', compact('vehicles', 'sel', 'staff', 'routes', 'stats', 'routeRows'));
    }

    public function saveVehicle(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:vehicles,id'],
            'code' => ['required', 'string', 'max:10', Rule::unique('vehicles', 'code')->ignore($request->input('id'))],
            'reg_no' => ['nullable', 'string', 'max:40'], 'type' => ['required', Rule::in(Vehicle::TYPES)],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'], 'route_id' => ['nullable', 'exists:routes,id'],
            'driver_id' => ['nullable', 'exists:employees,id'], 'salesman_id' => ['nullable', 'exists:employees,id'],
            'status' => ['required', Rule::in(Vehicle::STATUSES)], 'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $v = ! empty($data['id']) ? Vehicle::findOrFail($data['id']) : new Vehicle();
        unset($data['id']);
        $v->fill($data)->save();
        AuditLog::record('vehicle_saved', "{$v->code} {$v->reg_no} · {$v->status}");
        return redirect()->route('fleet.vehicles', ['v' => $v->id])->with('ok', "{$v->code} saved.");
    }

    public function saveRoute(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:routes,id'],
            'name' => ['required', 'string', 'max:120', Rule::unique('routes', 'name')->ignore($request->input('id'))],
            'markets' => ['nullable', 'string', 'max:255'], 'shops' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $r = ! empty($data['id']) ? RouteModel::findOrFail($data['id']) : new RouteModel();
        unset($data['id']);
        $r->fill($data)->save();
        AuditLog::record('route_saved', $r->name);
        return back()->with('ok', "Route {$r->name} saved.");
    }

    public function saveStaff(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:employees,id'],
            'name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(Employee::ROLES))], 'is_active' => ['nullable', 'boolean'],
        ]);
        $e = ! empty($data['id']) ? Employee::findOrFail($data['id']) : new Employee(['code' => Employee::nextCode()]);
        unset($data['id']);
        $e->fill($data + ['is_active' => $request->boolean('is_active', true)])->save();
        AuditLog::record('staff_saved', "{$e->code} {$e->name} ({$e->role})");
        return back()->with('ok', "{$e->name} saved.");
    }

    public function profit(Request $request)
    {
        $tz = 'Asia/Dhaka';
        $p = in_array($request->query('p'), ['day', 'week', 'month'], true) ? $request->query('p') : 'day';
        $day = self::day($request);
        $d = Carbon::parse($day, $tz);
        [$from, $to] = match ($p) {
            'week' => [$d->copy()->subDays(6)->toDateString(), $day],
            'month' => [$d->copy()->startOfMonth()->toDateString(), $day],
            default => [$day, $day],
        };
        $trips = Trip::query()->with(['vehicle', 'salesman'])->whereBetween('trip_date', [$from, $to])->where('status', '!=', 'draft')->get();
        $rows = []; $open = [];
        foreach ($trips->groupBy('vehicle_id') as $vt) {
            $q = 0; $sales = 0.0; $cost = 0.0; $exp = 0.0; $unrated = 0;
            foreach ($vt as $t) {
                $c = TripCalc::of($t);
                $q += $c['soldTotal']; $sales += $c['totalSales']; $cost += $c['cost']['amount']; $unrated += $c['cost']['unrated']; $exp += $c['expTotal'];
                if ($t->status === 'dispatched') $open[] = $t->vehicle->code;
            }
            $last = $vt->sortBy('trip_date')->last();
            $rows[] = ['v' => $last->vehicle->code, 's' => $last->salesman?->name ?? '—', 'q' => $q, 'sales' => $sales, 'cogs' => $cost, 'gross' => $sales - $cost, 'exp' => $exp, 'net' => $sales - $cost - $exp, 'unrated' => $unrated];
        }
        usort($rows, fn ($a, $b) => strcmp($a['v'], $b['v']));
        $tripIds = $trips->pluck('id');
        $cats = collect(Expense::TRIP_HEADS)->mapWithKeys(fn ($h) => [$h => (float) Expense::query()->whereIn('trip_id', $tripIds)->where('head', $h)->sum('amount')]);
        $openTrips = Trip::query()->with('vehicle')->whereIn('status', ['dispatched', 'returned', 'settled'])->orderByDesc('trip_date')->limit(30)->get();
        $perf = collect($rows)->sortByDesc('net')->values();
        $hasCash = MoneyAccount::query()->where('type', 'cash')->exists();

        return view('route.profit', compact('p', 'day', 'from', 'to', 'rows', 'cats', 'openTrips', 'perf', 'open', 'hasCash'));
    }

    public function expense(Request $request)
    {
        $data = $request->validate([
            'trip_id' => ['required', 'exists:trips,id'], 'head' => ['required', Rule::in(Expense::TRIP_HEADS)],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'], 'from' => ['required', Rule::in(['collection', 'office'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $trip = Trip::findOrFail($data['trip_id']);
        if ($trip->locked()) return back()->withErrors(['amount' => "{$trip->trip_no} is closed. Use a correction entry in Accounts."]);
        if ($data['from'] === 'collection' && $trip->status === 'settled') return back()->withErrors(['amount' => 'Cash for this trip is already settled. Choose "Office cash".']);
        $acc = MoneyAccount::query()->where('type', 'cash')->orderByDesc('is_default')->value('id');
        if (! $acc) return back()->withErrors(['amount' => 'Add a cash account in Accounts & profit first.']);
        $e = Expense::create(['expense_date' => $trip->trip_date->toDateString(), 'head' => $data['head'], 'amount' => $data['amount'], 'account_id' => $acc,
            'note' => trim($trip->trip_no.' '.($data['note'] ?? '')), 'created_by' => $request->user()->id, 'trip_id' => $trip->id, 'from_collection' => $data['from'] === 'collection']);
        AuditLog::record('trip_expense', "{$trip->trip_no} {$e->head} ৳{$e->amount}");
        return back()->with('ok', "{$e->head} ৳".number_format((float) $e->amount).' saved for '.$trip->trip_no.'.');
    }
}
