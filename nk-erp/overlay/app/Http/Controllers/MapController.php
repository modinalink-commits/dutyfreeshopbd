<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\DutySession;
use App\Models\GpsPoint;
use App\Models\Route as RouteModel;
use App\Models\Sale;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\TripCalc;
use Illuminate\Http\Request;

/** Live tracking (salesman phones on duty, with consent) and the distribution map. */
class MapController extends Controller
{
    public static function km(array $pts): float
    {
        $d = 0; $p = null;
        foreach ($pts as $q) {
            if ($p) {
                $r = 6371; $la1 = deg2rad($p[0]); $la2 = deg2rad($q[0]); $dl = deg2rad($q[1] - $p[1]); $dp = $la2 - $la1;
                $d += 2 * $r * asin(sqrt(sin($dp / 2) ** 2 + cos($la1) * cos($la2) * sin($dl / 2) ** 2));
            }
            $p = $q;
        }
        return $d;
    }

    private function people(): array
    {
        $day = now()->timezone('Asia/Dhaka')->startOfDay()->utc();
        $out = [];
        foreach (DutySession::query()->with(['employee', 'trip.vehicle', 'trip.route', 'trip.driver'])->where('started_at', '>=', $day)->orderBy('started_at')->get()->groupBy('employee_id') as $sessions) {
            $d = $sessions->last();
            $last = GpsPoint::query()->where('duty_id', $d->id)->latest('recorded_at')->first();
            $age = $last ? now()->diffInSeconds($last->recorded_at, true) : null;
            $st = $d->ended_at ? 'ended' : (! $d->gps_consent || ! $last ? 'waiting' : ($age <= 120 ? 'active' : ($age <= 900 ? 'stale' : 'offline')));
            $out[] = ['id' => $d->employee_id, 'duty' => $d->id, 'name' => $d->employee->name, 'emp' => $d->employee->code.' · '.(\App\Models\Employee::ROLES[$d->employee->role] ?? ''),
                'phone' => auth()->user()->isSuperAdmin() ? $d->employee->phone : '[authorized only]', 'veh' => $d->trip ? $d->trip->vehicle->code.' · '.($d->trip->driver?->name ?? '—') : '—',
                'route' => $d->trip?->route?->name ?? '—', 'start' => $sessions->first()->started_at->timezone('Asia/Dhaka')->format('H:i'), 'st' => $st,
                'last' => $last ? $last->recorded_at->timezone('Asia/Dhaka')->format('H:i:s').' · '.($age < 60 ? $age.' s ago' : intdiv($age, 60).' min ago') : 'no fix yet',
                'lat' => $last?->lat, 'lng' => $last?->lng, 'acc' => $last?->accuracy !== null ? '±'.$last->accuracy.' m' : '—', 'trip' => $d->trip_id];
        }
        return $out;
    }

    public function live(Request $request)
    {
        AuditLog::record('location_view', 'Live tracking opened');
        return view('map.live', ['people' => $this->people(), 'routes' => RouteModel::query()->orderBy('name')->get()]);
    }

    public function liveJson(Request $request)
    {
        $people = $this->people();
        $sel = (int) $request->query('e');
        $trail = []; $hist = null;
        if ($sel) {
            $day = now()->timezone('Asia/Dhaka')->startOfDay()->utc();
            $pts = GpsPoint::query()->where('employee_id', $sel)->where('recorded_at', '>=', $day)->orderBy('recorded_at')->get(['lat', 'lng', 'accuracy', 'recorded_at']);
            $good = $pts->filter(fn ($p) => $p->accuracy === null || $p->accuracy <= 100)->values();
            $trail = $good->map(fn ($p) => [$p->lat, $p->lng])->all();
            $gaps = []; $prev = null;
            foreach ($good as $p) { if ($prev && $p->recorded_at->diffInMinutes($prev->recorded_at, true) > 5) $gaps[] = $prev->recorded_at->timezone('Asia/Dhaka')->format('H:i').'–'.$p->recorded_at->timezone('Asia/Dhaka')->format('H:i'); $prev = $p; }
            $person = collect($people)->firstWhere('id', $sel);
            $stops = $person && $person['trip'] ? Sale::query()->with('customer')->where('trip_id', $person['trip'])->where('stage', '!=', 'voided')->orderBy('id')->get()
                ->map(fn ($s) => ['t' => $s->created_at->timezone('Asia/Dhaka')->format('H:i'), 'n' => $s->buyerName(), 'lat' => $s->customer?->lat, 'lng' => $s->customer?->lng])->all() : [];
            $hist = ['km' => round(self::km($trail), 1), 'kept' => $good->count(), 'dropped' => $pts->count() - $good->count(), 'gaps' => $gaps, 'stops' => $stops];
            AuditLog::record('location_view', 'Route history of employee #'.$sel);
        }
        return response()->json(['people' => $people, 'trail' => $trail, 'hist' => $hist, 'at' => now()->timezone('Asia/Dhaka')->format('d-m-Y H:i')]);
    }

    public function dist(Request $request)
    {
        $day = now()->timezone('Asia/Dhaka')->toDateString();
        $routes = RouteModel::query()->orderBy('name')->get();
        $trips = Trip::query()->with(['vehicle', 'salesman', 'driver', 'route'])->whereDate('trip_date', $day)->where('status', '!=', 'draft')->get();
        $sa = $request->user()->isSuperAdmin();
        $todaySales = Sale::query()->whereIn('trip_id', $trips->pluck('id'))->where('stage', '!=', 'voided')->get();
        $visited = $todaySales->pluck('customer_id')->filter()->unique()->all();
        $customers = Customer::query()->where('is_active', true)->whereNotNull('lat')->get()->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'lat' => (float) $c->lat, 'lng' => (float) $c->lng,
            'type' => $c->type, 'mob' => $c->phone, 'cat' => \App\Models\Customer::TYPES[$c->type] ?? $c->type, 'visited' => in_array($c->id, $visited, true),
            'last' => optional(Sale::query()->where('customer_id', $c->id)->latest('id')->first())->created_at?->timezone('Asia/Dhaka')->format('d-m-Y'),
            'total' => (float) Sale::query()->where('customer_id', $c->id)->where('stage', '!=', 'voided')->whereYear('created_at', now()->year)->sum('total'),
            'due' => $sa ? $c->due() : null]);
        $zones = $routes->map(function ($r) use ($trips, $customers) {
            $t = $trips->where('route_id', $r->id);
            $c = $t->map(fn ($x) => TripCalc::of($x));
            $inZone = $r->zone ? $customers->filter(fn ($cu) => self::inside([$cu['lat'], $cu['lng']], $r->zone)) : collect();
            return ['id' => $r->id, 'name' => $r->name, 'zone' => $r->zone, 'markets' => $r->markets ?: '—', 'sales' => (float) $c->sum('totalSales'), 'cyl' => $c->sum('soldTotal'),
                'coll' => (float) $c->sum('collection'), 'shops' => $inZone->count().' / '.($r->shops ?? '—'), 'unv' => $inZone->where('visited', false)->count(),
                'sm' => $t->first()?->salesman?->name ?? '—', 'veh' => $t->first()?->vehicle?->code ?? '—'];
        });
        $veh = $trips->map(function ($t) {
            $last = GpsPoint::query()->whereIn('duty_id', DutySession::query()->where('trip_id', $t->id)->pluck('id'))->latest('recorded_at')->first();
            $trail = GpsPoint::query()->whereIn('duty_id', DutySession::query()->where('trip_id', $t->id)->pluck('id'))->where(fn ($q) => $q->whereNull('accuracy')->orWhere('accuracy', '<=', 100))->orderBy('recorded_at')->get(['lat', 'lng'])->map(fn ($p) => [$p->lat, $p->lng])->all();
            $c = TripCalc::of($t);
            return ['code' => $t->vehicle->code, 'reg' => $t->vehicle->reg_no, 'route' => $t->route?->name ?? '—', 'crew' => ($t->driver?->name ?? '—').' · '.($t->salesman?->name ?? '—'),
                'lat' => $last?->lat, 'lng' => $last?->lng, 'at' => $last?->recorded_at?->timezone('Asia/Dhaka')->format('H:i'), 'stops' => $c['invoices'], 'trail' => $trail, 'status' => $t->status];
        })->values();
        return view('map.dist', ['zones' => $zones, 'customers' => $customers->values(), 'veh' => $veh, 'routes' => $routes, 'sa' => $sa]);
    }

    public static function inside(array $p, array $poly): bool
    {
        $in = false; $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$yi, $xi] = $poly[$i]; [$yj, $xj] = $poly[$j];
            if ((($yi > $p[0]) !== ($yj > $p[0])) && ($p[1] < ($xj - $xi) * ($p[0] - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) $in = ! $in;
        }
        return $in;
    }

    public function saveZone(Request $request, RouteModel $zoneRoute)
    {
        $route = $zoneRoute;
        $data = $request->validate(['zone' => ['nullable', 'array', 'max:200'], 'zone.*' => ['array', 'size:2'], 'zone.*.*' => ['numeric']]);
        $route->update(['zone' => $data['zone'] ?: null]);
        AuditLog::record('zone_saved', $route->name.' · '.count($data['zone'] ?? []).' points');
        return response()->json(['ok' => true]);
    }

    public function placeCustomer(Request $request, Customer $customer)
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);
        $customer->update($data);
        AuditLog::record('customer_location', $customer->code.' '.$customer->name);
        return response()->json(['ok' => true]);
    }
}
