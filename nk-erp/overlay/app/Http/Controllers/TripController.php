<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Route as RouteModel;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Trip;
use App\Models\TripAdjustment;
use App\Models\TripLoad;
use App\Models\TripReturn;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\SalesService;
use App\Support\TripCalc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** One vehicle trip: loading → market sales → return → cash settlement → closing. */
class TripController extends Controller
{
    public const NOTES = [1000, 500, 200, 100, 50, 20, 10, 5, 2, 1];

    private function ownBrand(): Brand
    {
        $b = Brand::query()->where('for_sale', true)->orderBy('id')->first();
        if (! $b) throw new RuntimeException('Mark your own brand as "for sale" in Cylinder brands first.');
        return $b;
    }

    private function pick(Request $request, array $status): ?Trip
    {
        if ($id = (int) $request->query('trip')) return Trip::findOrFail($id);
        $day = FleetController::day($request);
        return Trip::query()->whereDate('trip_date', $day)->whereIn('status', $status)->orderBy('id')->first()
            ?? Trip::query()->whereIn('status', $status)->orderByDesc('trip_date')->orderBy('id')->first();
    }

    private function dayTrips(Request $request, ?Trip $trip)
    {
        $day = $trip ? $trip->trip_date->toDateString() : FleetController::day($request);
        return Trip::query()->with('vehicle')->whereDate('trip_date', $day)->orderBy('id')->get();
    }

    // ---------------- Morning loading ----------------

    public function loading(Request $request)
    {
        $day = FleetController::day($request);
        $trip = $request->query('trip') ? Trip::with('loads')->findOrFail((int) $request->query('trip')) : null;
        try { $brand = $this->ownBrand(); } catch (RuntimeException $e) { $brand = null; }
        $sizes = $brand ? array_values(array_intersect(Brand::SIZES, array_map('intval', $brand->sizes ?? Brand::SIZES))) : Brand::SIZES;
        $godown = []; foreach ($sizes as $s) $godown[$s] = $brand ? StockMovement::balance($brand->id, $s)['full'] : 0;
        $vehicles = Vehicle::query()->with(['route', 'driver', 'salesman'])->orderBy('code')->get();
        $today = Trip::query()->with('loads')->whereDate('trip_date', $day)->get();
        $list = $vehicles->map(function ($v) use ($today) {
            $t = $today->where('vehicle_id', $v->id)->last();
            $items = $t ? $t->loads->sortBy('size_kg')->map(fn ($l) => $l->size_kg.' kg '.$l->qty)->implode(' · ') : ($v->status === 'Maintenance' ? 'In maintenance' : 'No trip yet');
            $st = ! $t ? 'Not loading' : ($t->status === 'draft' ? 'Waiting approval' : 'Dispatched');
            return ['v' => $v, 't' => $t, 'items' => $items, 'status' => $st];
        });
        return view('route.loading', [
            'day' => $day, 'trip' => $trip, 'brand' => $brand, 'sizes' => $sizes, 'godown' => $godown, 'vehicles' => $vehicles, 'list' => $list,
            'routes' => RouteModel::query()->orderBy('name')->get(),
            'drivers' => Employee::query()->where('is_active', true)->where('role', 'driver')->orderBy('name')->get(),
            'salesmen' => Employee::query()->where('is_active', true)->where('role', 'salesman')->orderBy('name')->get(),
            'warehouse' => Employee::query()->where('is_active', true)->whereIn('role', ['warehouse', 'manager', 'other'])->orderBy('name')->get(),
            'nextNo' => $vehicles->first() ? Trip::nextNo($vehicles->first(), $day) : 'TRP-'.date('dm', strtotime($day)).'-01',
        ]);
    }

    public function storeLoading(Request $request)
    {
        $data = $request->validate([
            'trip_id' => ['nullable', 'exists:trips,id'], 'trip_date' => ['required', 'date'], 'vehicle_id' => ['required', 'exists:vehicles,id'],
            'route_id' => ['nullable', 'exists:routes,id'], 'driver_id' => ['nullable', 'exists:employees,id'], 'salesman_id' => ['nullable', 'exists:employees,id'],
            'warehouse_staff_id' => ['nullable', 'exists:employees,id'], 'loading_time' => ['nullable', 'date_format:H:i'], 'remarks' => ['nullable', 'string', 'max:255'],
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'integer', 'min:0', 'max:5000'], 'action' => ['required', Rule::in(['draft', 'dispatch'])],
        ]);
        if ($data['action'] === 'dispatch' && ! in_array($request->user()->role, ['super_admin', 'admin', 'manager'], true)) {
            return back()->withInput()->withErrors(['qty' => 'Only a manager can approve and dispatch.']);
        }
        try {
            $trip = DB::transaction(function () use ($data, $request) {
                $brand = $this->ownBrand();
                $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($data['vehicle_id']);
                if ($vehicle->status === 'Maintenance') throw new RuntimeException("{$vehicle->code} is in maintenance.");
                $trip = ! empty($data['trip_id']) ? Trip::query()->lockForUpdate()->findOrFail($data['trip_id']) : null;
                if ($trip && $trip->status !== 'draft') throw new RuntimeException("{$trip->trip_no} is already dispatched. Loading cannot change now.");
                $busy = Trip::query()->where('vehicle_id', $vehicle->id)->whereIn('status', ['draft', 'dispatched', 'returned'])->when($trip, fn ($q) => $q->where('id', '!=', $trip->id))->first();
                if ($busy) throw new RuntimeException("{$vehicle->code} already has trip {$busy->trip_no} open. Settle or close it first.");
                $loads = array_filter(array_map('intval', $data['qty']), fn ($q) => $q > 0);
                if (! $loads) throw new RuntimeException('Enter how many cylinders to load.');
                $total = array_sum($loads);
                if ($vehicle->capacity && $total > $vehicle->capacity) throw new RuntimeException("{$total} cylinders is more than {$vehicle->code}'s capacity of {$vehicle->capacity}.");
                foreach ($loads as $size => $q) {
                    $have = StockMovement::balance($brand->id, (int) $size)['full'];
                    if ($q > $have) throw new RuntimeException("Only {$have} × {$size} kg left in the warehouse.");
                }
                $fields = ['trip_date' => $data['trip_date'], 'vehicle_id' => $vehicle->id, 'route_id' => $data['route_id'] ?? null, 'driver_id' => $data['driver_id'] ?? null,
                    'salesman_id' => $data['salesman_id'] ?? null, 'warehouse_staff_id' => $data['warehouse_staff_id'] ?? null, 'loading_time' => $data['loading_time'] ?? null,
                    'remarks' => $data['remarks'] ?? null, 'counted_by' => $request->user()->id, 'counted_at' => now()];
                if ($trip) $trip->update($fields);
                else $trip = Trip::create($fields + ['trip_no' => Trip::nextNo($vehicle, $data['trip_date']), 'status' => 'draft']);
                TripLoad::query()->where('trip_id', $trip->id)->delete();
                foreach ($loads as $size => $q) TripLoad::create(['trip_id' => $trip->id, 'size_kg' => (int) $size, 'qty' => $q]);
                $vehicle->update(['status' => 'Loading']);
                if ($data['action'] === 'dispatch') {
                    foreach ($loads as $size => $q) {
                        $ref = StockMovement::nextRef('LOD');
                        StockMovement::post(['ref_no' => $ref, 'kind' => 'loading', 'brand_id' => $brand->id, 'size_kg' => (int) $size, 'full_delta' => -$q, 'reference' => $trip->trip_no, 'note' => 'Loaded on '.$vehicle->code]);
                        StockMovement::post(['ref_no' => $ref.'V', 'kind' => 'loading', 'brand_id' => $brand->id, 'size_kg' => (int) $size, 'full_delta' => $q, 'reference' => $trip->trip_no, 'note' => 'On '.$vehicle->code, 'trip_id' => $trip->id]);
                    }
                    $trip->update(['status' => 'dispatched', 'approved_by' => $request->user()->id, 'dispatched_at' => now()]);
                    $vehicle->update(['status' => 'In market']);
                }
                return $trip;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }
        $n = $trip->loads()->sum('qty');
        AuditLog::record($trip->status === 'dispatched' ? 'trip_dispatched' : 'trip_loading', "{$trip->trip_no} · {$n} cylinders".($trip->status === 'dispatched' ? ' · loading approved and dispatched' : ' · counted, waiting for approval'));
        return redirect()->route('trips.loading', ['trip' => $trip->id, 'd' => $trip->trip_date->toDateString()])
            ->with('ok', $trip->status === 'dispatched' ? "{$trip->trip_no} dispatched. {$n} cylinders moved to {$trip->vehicle->code}." : "{$trip->trip_no} saved as draft. A manager must approve before dispatch.");
    }

    public function slip(Trip $trip)
    {
        $trip->load(['vehicle', 'route', 'driver', 'salesman', 'warehouseStaff', 'loads']);
        return view('route.slip', ['trip' => $trip, 'counter' => User::find($trip->counted_by), 'approver' => User::find($trip->approved_by)]);
    }

    // ---------------- Sales on the trip (back office entry until the salesman app is live) ----------------

    public function sales(Trip $trip)
    {
        $trip->load(['vehicle', 'route', 'salesman']);
        $c = TripCalc::of($trip);
        $products = Product::query()->where('is_active', true)->whereIn('category', ['refill', 'new_package'])->orderBy('category')->orderBy('size_kg')->get();
        $onVehicle = [];
        foreach (Brand::SIZES as $s) $onVehicle[$s] = (int) StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $s)->sum('full_delta');
        return view('route.sales', ['trip' => $trip, 'c' => $c, 'products' => $products, 'onVehicle' => $onVehicle,
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone', 'code']),
            'brands' => Brand::query()->where('empty_collection', true)->orderBy('code')->get(), 'ownBrand' => Brand::query()->where('for_sale', true)->value('id')]);
    }

    public function storeSale(Request $request, Trip $trip)
    {
        $data = $request->validate([
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'customer_id' => ['nullable', 'exists:customers,id'], 'walkin_name' => ['nullable', 'string', 'max:120'], 'walkin_phone' => ['nullable', 'string', 'max:20'],
            'empties' => ['nullable', 'integer', 'min:0', 'max:5000'], 'empty_brand_id' => ['required', 'exists:brands,id'],
            'method' => ['required', Rule::in(array_keys(Sale::METHODS))], 'paid' => ['nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        if ($trip->status !== 'dispatched') return back()->withInput()->withErrors(['qty' => "{$trip->trip_no} is not in the market. Sales can only be entered while the vehicle is out."]);
        try {
            $sale = DB::transaction(function () use ($data, $request, $trip) {
                $lines = SalesService::lines(array_filter($data['qty']), 'dealer_price');
                $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
                $total = round(array_sum(array_column($lines, 'total')), 2);
                $paid = $data['method'] === 'due' ? (float) ($data['paid'] ?? 0) : $total;
                if ($paid > $total + 0.004) throw new RuntimeException('Paid amount is more than the bill.');
                SalesService::checkCredit($customer, $total - $paid);
                $refills = SalesService::refillBySize($lines);
                DB::table('sales')->lockForUpdate()->count();
                $sale = Sale::create([
                    'invoice_no' => Sale::nextNo('retail'), 'type' => 'retail', 'customer_id' => $customer?->id, 'trip_id' => $trip->id,
                    'walkin_name' => $customer ? null : ($data['walkin_name'] ?? null), 'walkin_phone' => $customer ? null : ($data['walkin_phone'] ?? null),
                    'stage' => 'posted', 'subtotal' => $total, 'discount' => 0, 'delivery_charge' => 0, 'total' => $total, 'paid' => $paid,
                    'payment_method' => $paid > 0 && $data['method'] === 'due' ? 'cash' : $data['method'], 'empty_brand_id' => $data['empty_brand_id'],
                    'empties_expected' => array_sum($refills), 'empties_received' => (int) ($data['empties'] ?? array_sum($refills)),
                    'vehicle' => $trip->vehicle->code, 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id, 'delivered_at' => now(),
                ]);
                foreach ($lines as $l) {
                    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $l['product']->id, 'description' => $l['product']->name, 'category' => $l['product']->category,
                        'brand_id' => $l['product']->brand_id, 'size_kg' => $l['product']->size_kg, 'qty' => $l['qty'], 'unit_price' => $l['price'], 'line_total' => $l['total']]);
                }
                SalesService::postOut($sale, $lines);
                SalesService::postEmpties($sale, $refills, $sale->empties_received, (int) $data['empty_brand_id']);
                SalesService::postMoney($sale);
                return $sale;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }
        AuditLog::record('trip_sale', "{$trip->trip_no} · {$sale->invoice_no} · ".$sale->buyerName()." ৳{$sale->total}");
        return back()->with('ok', "{$sale->invoice_no} saved on {$trip->trip_no}.");
    }

    public function collect(Request $request, Trip $trip)
    {
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id'], 'amount' => ['required', 'numeric', 'min:1'], 'method' => ['required', Rule::in(['cash', 'bkash', 'nagad', 'bank'])], 'note' => ['nullable', 'string', 'max:255']]);
        if ($trip->status !== 'dispatched') return back()->withErrors(['amount' => 'Previous due can be collected on a trip only while the vehicle is out.']);
        $c = Customer::findOrFail($data['customer_id']);
        CustomerLedger::create(['customer_id' => $c->id, 'kind' => 'payment', 'reference' => $trip->trip_no, 'credit' => $data['amount'], 'method' => $data['method'],
            'note' => trim('Previous due collected on '.$trip->trip_no.' '.($data['note'] ?? '')), 'created_by' => $request->user()->id, 'trip_id' => $trip->id]);
        rescue(fn () => \App\Support\Sms::forPayment($c, (float) $data['amount'], null), null, false);
        AuditLog::record('trip_due_collected', "{$trip->trip_no} · {$c->name} ৳{$data['amount']} {$data['method']}");
        return back()->with('ok', 'Previous due ৳'.number_format((float) $data['amount'])." from {$c->name} saved.");
    }

    // ---------------- Vehicle return ----------------

    public function returnForm(Request $request)
    {
        $trip = $this->pick($request, ['dispatched', 'returned', 'settled', 'closed']);
        if (! $trip) return view('route.empty', ['title' => 'Vehicle return', 'msg' => 'No trip has been dispatched yet. Start with Morning loading.']);
        $trip->load(['vehicle', 'route', 'driver', 'salesman', 'returnChecker']);
        $c = TripCalc::of($trip);
        $shops = CustomerCylinder::query()->with('customer')->whereIn('sale_id', $c['sales']->pluck('id'))->get()->groupBy(fn ($r) => $r->customer_id.'-'.$r->size_kg)
            ->map(function ($g) use ($c) {
                $f = $g->first();
                $sold = (int) $c['sales']->where('customer_id', $f->customer_id)->flatMap->items->where('size_kg', $f->size_kg)->where('category', 'refill')->sum('qty');
                $pending = (int) $g->sum('qty_delta');
                return ['c' => $f->customer, 'size' => $f->size_kg, 'sold' => $sold, 'got' => $sold - $pending, 'pending' => $pending,
                    'note' => $c['sales']->where('customer_id', $f->customer_id)->pluck('note')->filter()->implode('; ')];
            })->filter(fn ($r) => $r['pending'] > 0)->values();
        return view('route.return', ['trip' => $trip, 'c' => $c, 'shops' => $shops, 'trips' => $this->dayTrips($request, $trip),
            'checkers' => Employee::query()->where('is_active', true)->whereIn('role', ['warehouse', 'manager', 'other'])->orderBy('name')->get()]);
    }

    public function storeReturn(Request $request, Trip $trip)
    {
        $data = $request->validate([
            'return_date' => ['required', 'date'], 'return_time' => ['nullable', 'date_format:H:i'], 'checked_by' => ['nullable', 'exists:employees,id'], 'remarks' => ['nullable', 'string', 'max:255'],
            'full' => ['array'], 'full.*' => ['nullable', 'integer', 'min:0'], 'damaged' => ['array'], 'damaged.*' => ['nullable', 'integer', 'min:0'],
            'empty' => ['array'], 'empty.*' => ['nullable', 'integer', 'min:0'],
        ]);
        try {
            DB::transaction(function () use ($data, $trip) {
                $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
                if ($trip->status !== 'dispatched') throw new RuntimeException("{$trip->trip_no} return is already saved. Fix differences with a manager adjustment in Daily closing.");
                foreach (Brand::SIZES as $s) {
                    $full = (int) ($data['full'][$s] ?? 0); $dmg = (int) ($data['damaged'][$s] ?? 0); $emp = (int) ($data['empty'][$s] ?? 0);
                    if (! $full && ! $dmg && ! $emp && ! $trip->loads()->where('size_kg', $s)->exists()) continue;
                    TripReturn::create(['trip_id' => $trip->id, 'size_kg' => $s, 'full_returned' => $full, 'damaged' => $dmg, 'empty_returned' => $emp]);
                    $this->moveFromVehicle($trip, $s, 'full', $full, 'full');
                    $this->moveFromVehicle($trip, $s, 'full', $dmg, 'damaged');
                    $this->moveFromVehicle($trip, $s, 'empty', $emp, 'empty');
                }
                $trip->update(['status' => 'returned', 'return_time' => $data['return_time'] ?? null, 'return_checked_by' => $data['checked_by'] ?? null,
                    'return_remarks' => $data['remarks'] ?? null, 'returned_at' => now()]);
                $trip->vehicle->update(['status' => 'Returned']);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['full' => $e->getMessage()]);
        }
        $c = TripCalc::of($trip->fresh());
        $short = collect($c['sizes'])->map(fn ($z, $s) => ($z['emptyDiff'] < 0 ? abs($z['emptyDiff']).' empty '.$s.' kg short ' : '').($z['missing'] > 0 ? $z['missing'].' full '.$s.' kg missing' : ''))->filter()->implode(', ');
        AuditLog::record('trip_returned', "{$trip->trip_no} · vehicle return saved".($short ? ' — '.$short : ' — matches'));
        return redirect()->route('trips.settle', ['trip' => $trip->id])->with('ok', "Return saved for {$trip->trip_no}.");
    }

    /** Move n cylinders of one size off the vehicle into a godown bucket, brand by brand. */
    private function moveFromVehicle(Trip $trip, int $size, string $from, int $n, string $to, string $kind = 'unloading', string $note = 'Back from vehicle'): void
    {
        if ($n <= 0) return;
        $col = $from === 'full' ? 'full_delta' : 'empty_delta';
        $byBrand = StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $size)->groupBy('brand_id')->selectRaw("brand_id, sum($col) q")->pluck('q', 'brand_id');
        $left = $n;
        foreach ($byBrand as $brandId => $have) {
            $take = min((int) $have, $left);
            if ($take <= 0) continue;
            $ref = StockMovement::nextRef('RET');
            StockMovement::post(['ref_no' => $ref.'V', 'kind' => $kind, 'brand_id' => $brandId, 'size_kg' => $size, $col => -$take, 'reference' => $trip->trip_no, 'note' => $note, 'trip_id' => $trip->id]);
            if ($to !== 'none') {
                StockMovement::post(['ref_no' => $ref, 'kind' => $kind, 'brand_id' => $brandId, 'size_kg' => $size, $to.'_delta' => $take, 'reference' => $trip->trip_no, 'note' => $note.' '.$trip->vehicle->code]);
            }
            $left -= $take;
            if ($left <= 0) break;
        }
        if ($left > 0) throw new RuntimeException("{$trip->vehicle->code} has only ".($n - $left)." {$from} {$size} kg on board, not {$n}.");
    }

    // ---------------- Cash settlement ----------------

    public function settle(Request $request)
    {
        $trip = $this->pick($request, ['returned', 'settled', 'closed']);
        if (! $trip) return view('route.empty', ['title' => 'Cash collection & settlement', 'msg' => 'No vehicle has come back yet. Save the Vehicle return first.']);
        $trip->load(['vehicle', 'salesman']);
        return view('route.settle', ['trip' => $trip, 'c' => TripCalc::of($trip), 'trips' => $this->dayTrips($request, $trip), 'notes' => self::NOTES,
            'receivers' => User::query()->where('is_active', true)->whereIn('role', ['super_admin', 'admin', 'manager', 'accounts'])->orderBy('name')->get()]);
    }

    public function storeSettle(Request $request, Trip $trip)
    {
        $data = $request->validate(['notes' => ['array'], 'notes.*' => ['nullable', 'integer', 'min:0', 'max:100000'], 'digital_ok' => ['nullable', 'boolean'],
            'received_by' => ['required', 'exists:users,id'], 'time' => ['nullable', 'date_format:H:i']]);
        if ($trip->status !== 'returned') return back()->withErrors(['notes' => $trip->status === 'dispatched' ? 'Save the vehicle return first.' : "{$trip->trip_no} cash is already settled."]);
        $notes = []; $sum = 0;
        foreach (self::NOTES as $n) { $q = (int) ($data['notes'][$n] ?? 0); if ($q) { $notes[$n] = $q; $sum += $n * $q; } }
        $c = TripCalc::of($trip);
        $at = $data['time'] ?? null ? now()->timezone('Asia/Dhaka')->setTimeFromTimeString($data['time'])->utc() : now();
        $trip->update(['cash_notes' => $notes, 'cash_counted' => $sum, 'digital_verified' => $request->boolean('digital_ok') ? $c['digitalTotal'] : null,
            'received_by' => $data['received_by'], 'settled_at' => $at, 'status' => 'settled']);
        $trip->vehicle->update(['status' => 'Closing pending']);
        $diff = round($sum - $c['expected'], 2);
        AuditLog::record('trip_settled', "{$trip->trip_no} · cash settlement saved — counted ৳".number_format($sum).($diff ? ' · ৳'.number_format(abs($diff)).($diff < 0 ? ' short' : ' extra') : ' · matches'));
        if (abs($diff) >= 0.5 || TripCalc::of($trip->fresh())['stockDiff']->isNotEmpty()) AuditLog::record('trip_mismatch', "{$trip->trip_no} · status set to Mismatch found");
        return redirect()->route('trips.closing', ['trip' => $trip->id])->with('ok', "Cash for {$trip->trip_no} saved. Counted ৳".number_format($sum).'.');
    }

    // ---------------- Daily closing ----------------

    private function options(Trip $trip, array $c): array
    {
        $o = [];
        $shops = $c['sales']->whereNotNull('customer_id')->pluck('customer')->unique('id');
        foreach ($c['stockDiff'] as $s => $z) {
            if ($z['vehEmpty'] > 0) {
                foreach ($shops as $cu) $o["empty_shop:$s:{$z['vehEmpty']}:{$cu->id}"] = "Empty {$s} kg × {$z['vehEmpty']} → move to {$cu->name} pending empties";
                $o["empty_found:$s:{$z['vehEmpty']}"] = "Empty {$s} kg × {$z['vehEmpty']} → found, return to warehouse";
                $o["empty_lost:$s:{$z['vehEmpty']}"] = "Empty {$s} kg × {$z['vehEmpty']} → lost, write off";
            }
            if ($z['vehFull'] > 0) {
                $o["full_found:$s:{$z['vehFull']}"] = "Full {$s} kg × {$z['vehFull']} → found, return to warehouse";
                $o["full_lost:$s:{$z['vehFull']}"] = "Full {$s} kg × {$z['vehFull']} → lost, write off";
            }
        }
        if ($c['cashDiff'] !== null && abs($c['cashDiff']) >= 0.5 && ! $c['cashAdj']) {
            $a = '৳'.number_format(abs($c['cashDiff']));
            if ($c['cashDiff'] < 0) { $o['cash_recover'] = "Cash {$a} → recover from salesman"; $o['cash_accept'] = "Cash {$a} short → accept, under investigation"; }
            else $o['cash_extra'] = "Cash {$a} extra → keep, record as excess";
        }
        return $o;
    }

    public function closing(Request $request)
    {
        $trip = $this->pick($request, ['settled', 'returned', 'dispatched', 'closed']);
        if (! $trip) return view('route.empty', ['title' => 'Daily closing', 'msg' => 'No trip to close yet.']);
        $trip->load(['vehicle', 'route', 'driver', 'salesman', 'adjustments.approver']);
        $c = TripCalc::of($trip);
        $log = \App\Models\AuditLog::query()->with('user')->where('details', 'like', $trip->trip_no.'%')->orderBy('id')->get();
        return view('route.closing', ['trip' => $trip, 'c' => $c, 'stage' => TripCalc::stage($trip, $c), 'options' => $this->options($trip, $c), 'log' => $log, 'trips' => $this->dayTrips($request, $trip)]);
    }

    public function adjust(Request $request, Trip $trip)
    {
        $data = $request->validate(['option' => ['required', 'string', 'max:60'], 'reason' => ['required', 'string', 'min:5', 'max:255']]);
        if ($trip->status !== 'settled') return back()->withErrors(['reason' => 'Adjustments are made after the cash settlement and before closing.']);
        $c = TripCalc::of($trip);
        $opts = $this->options($trip, $c);
        if (! isset($opts[$data['option']])) return back()->withErrors(['option' => 'That difference has changed. Pick again.']);
        $p = explode(':', $data['option']);
        try {
            DB::transaction(function () use ($p, $trip, $data, $opts, $request) {
                $s = (int) ($p[1] ?? 0); $n = (int) ($p[2] ?? 0);
                $kind = str_starts_with($p[0], 'cash') ? 'cash' : 'stock';
                match ($p[0]) {
                    'empty_found' => $this->moveFromVehicle($trip, $s, 'empty', $n, 'empty', 'adjustment', 'Found: '.$data['reason']),
                    'full_found' => $this->moveFromVehicle($trip, $s, 'full', $n, 'full', 'adjustment', 'Found: '.$data['reason']),
                    'empty_lost' => $this->moveFromVehicle($trip, $s, 'empty', $n, 'none', 'adjustment', 'Written off: '.$data['reason']),
                    'full_lost' => $this->moveFromVehicle($trip, $s, 'full', $n, 'none', 'adjustment', 'Written off: '.$data['reason']),
                    'empty_shop' => $this->toShop($trip, $s, $n, (int) $p[3], $data['reason']),
                    default => null,
                };
                TripAdjustment::create(['trip_id' => $trip->id, 'kind' => $kind, 'detail' => $opts[$data['option']], 'reason' => $data['reason'], 'approved_by' => $request->user()->id]);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['option' => $e->getMessage()]);
        }
        AuditLog::record('trip_adjustment', "{$trip->trip_no} · adjustment approved — {$opts[$data['option']]} · {$data['reason']}");
        return back()->with('ok', 'Adjustment approved.');
    }

    private function toShop(Trip $trip, int $size, int $n, int $customerId, string $reason): void
    {
        $brandId = (int) StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $size)->groupBy('brand_id')->havingRaw('sum(empty_delta) > 0')->value('brand_id');
        $this->moveFromVehicle($trip, $size, 'empty', $n, 'none', 'adjustment', 'Left at shop: '.$reason);
        CustomerCylinder::create(['customer_id' => $customerId, 'brand_id' => $brandId ?: Brand::query()->where('for_sale', true)->value('id'), 'size_kg' => $size, 'qty_delta' => $n, 'reference' => $trip->trip_no.' adj']);
    }

    public function close(Request $request, Trip $trip)
    {
        $c = TripCalc::of($trip);
        if ($trip->status !== 'settled') return back()->withErrors(['reason' => 'Only a settled trip can be closed.']);
        if (! $c['stockOk'] || ! $c['cashOk']) return back()->withErrors(['reason' => 'Stock and cash must match or have an approved adjustment.']);
        $trip->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()]);
        $trip->vehicle->update(['status' => 'Closed']);
        AuditLog::record('trip_closed', "{$trip->trip_no} · trip closed and locked");
        return back()->with('ok', "{$trip->trip_no} closed and locked.");
    }

    public function wizard(Request $request)
    {
        $trip = $this->pick($request, ['settled', 'returned', 'dispatched', 'closed']);
        if (! $trip) return view('route.empty', ['title' => 'Daily closing', 'msg' => 'No trip to close yet.']);
        $trip->load(['vehicle', 'driver', 'salesman']);
        $c = TripCalc::of($trip);
        $auto = match ($trip->status) { 'dispatched' => 2, 'returned' => 4, 'settled' => ($c['stockOk'] && $c['cashOk'] ? 7 : 6), default => 7 };
        $step = max(1, min(7, (int) $request->query('step', $auto)));
        return view('route.wizard', ['trip' => $trip, 'c' => $c, 'step' => $step, 'options' => $this->options($trip, $c)]);
    }
}
