<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\Employee;
use App\Models\Route as RouteModel;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\Request;

/** Office side of customer-app orders: approve, then attach to a route trip. */
class CustomerOrderController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->query('f', 'all');
        $orders = CustomerOrder::query()->with(['customer', 'route', 'salesman', 'trip.vehicle'])->when($f !== 'all', fn ($q) => $q->where('status', $f))->latest('id')->limit(100)->get();
        $sel = $request->query('o') ? CustomerOrder::with('customer')->find((int) $request->query('o')) : ($orders->firstWhere('status', 'new') ?? $orders->first());
        $c = $sel?->customer;
        $routeOf = $c ? \App\Models\Sale::query()->whereNotNull('trip_id')->where('customer_id', $c->id)->latest('id')->first()?->trip : null;
        return view('corders', ['orders' => $orders, 'f' => $f, 'sel' => $sel, 'c' => $c, 'routeOf' => $routeOf,
            'k' => ['new' => CustomerOrder::query()->where('status', 'new')->count(), 'approved' => CustomerOrder::query()->where('status', 'approved')->count(), 'on_trip' => CustomerOrder::query()->where('status', 'on_trip')->count(),
                'delivered' => CustomerOrder::query()->where('status', 'delivered')->where('updated_at', '>=', now()->subDays(7))->count(), 'app' => Customer::query()->whereNotNull('portal_last_seen')->count()],
            'routes' => RouteModel::query()->orderBy('name')->get(), 'vehicles' => Vehicle::query()->orderBy('code')->get(), 'salesmen' => Employee::query()->where('role', 'salesman')->where('is_active', true)->orderBy('name')->get()]);
    }

    public function approve(Request $request, CustomerOrder $order)
    {
        $data = $request->validate(['route_id' => ['nullable', 'exists:routes,id'], 'delivery_date' => ['required', 'date'], 'vehicle_id' => ['nullable', 'exists:vehicles,id'], 'salesman_id' => ['nullable', 'exists:employees,id']]);
        if (! in_array($order->status, ['new', 'approved'], true)) return back()->withErrors(['x' => 'This order is already on a trip or closed.']);
        $trip = ! empty($data['vehicle_id']) ? Trip::query()->where('vehicle_id', $data['vehicle_id'])->whereDate('trip_date', $data['delivery_date'])->whereIn('status', ['draft', 'dispatched'])->first() : null;
        $order->update($data + ['status' => $trip ? 'on_trip' : 'approved', 'trip_id' => $trip?->id, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        AuditLog::record('customer_order_approved', "{$order->order_no}".($trip ? ' · on '.$trip->trip_no : ''));
        rescue(fn () => \App\Support\Sms::queue('delivery', $order->customer->phone, 'প্রিয় গ্রাহক, আপনার অর্ডার '.$order->order_no.' অনুমোদিত। ডেলিভারি '.\Carbon\Carbon::parse($data['delivery_date'])->format('d-m-Y').'। '.\App\Models\Setting::get('company_name', 'NK Corporation'), null, $order->customer_id), null, false);
        return back()->with('ok', "{$order->order_no} approved".($trip ? " and added to {$trip->trip_no}." : '. It goes on the trip when that vehicle is loaded for the day.'));
    }

    public function attach(CustomerOrder $order)
    {
        if ($order->status !== 'approved' || ! $order->vehicle_id) return back()->withErrors(['x' => 'Approve with a vehicle first.']);
        $trip = Trip::query()->where('vehicle_id', $order->vehicle_id)->whereIn('status', ['draft', 'dispatched'])->latest('id')->first();
        if (! $trip) return back()->withErrors(['x' => 'That vehicle has no open trip yet. Do the morning loading first.']);
        $order->update(['status' => 'on_trip', 'trip_id' => $trip->id]);
        AuditLog::record('customer_order_trip', "{$order->order_no} → {$trip->trip_no}");
        return back()->with('ok', "{$order->order_no} added to {$trip->trip_no}.");
    }

    public function reject(Request $request, CustomerOrder $order)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if (in_array($order->status, ['delivered', 'rejected'], true)) return back();
        $order->update(['status' => 'rejected', 'reject_reason' => $data['reason']]);
        AuditLog::record('customer_order_rejected', "{$order->order_no} · {$data['reason']}");
        return back()->with('ok', "{$order->order_no} rejected.");
    }

    public function togglePortal(Customer $customer)
    {
        $customer->update(['portal_enabled' => ! $customer->portal_enabled]);
        AuditLog::record('customer_portal', $customer->code.' '.($customer->portal_enabled ? 'on' : 'off'));
        return back();
    }
}
