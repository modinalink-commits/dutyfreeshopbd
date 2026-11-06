<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Trip;
use Illuminate\Http\Request;

/** Global search: one box for customers, invoices and trips/routes. */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['groups' => []]);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $digits = preg_replace('/\D+/', '', $q);

        $cust = Customer::query()->where(function ($w) use ($like, $digits) {
            $w->where('name', 'ilike', $like)->orWhere('shop_name', 'ilike', $like)->orWhere('owner_name', 'ilike', $like)->orWhere('code', 'ilike', $like)->orWhere('area', 'ilike', $like);
            if (strlen($digits) >= 4) $w->orWhere('phone', 'like', '%'.$digits.'%');
        })->orderBy('name')->limit(6)->get()->map(function ($c) {
            $due = $c->due();
            return ['t' => $c->name, 'm' => $c->code.($c->area ? ' · '.$c->area : '').($due > 0 ? ' · বকেয়া '.\App\Support\Ui::bd($due) : ''), 'u' => route('customers.statement', $c)];
        });

        $inv = Sale::query()->with(['customer', 'trip.route', 'trip.vehicle', 'trip.salesman'])->where(function ($w) use ($like) {
            $w->where('invoice_no', 'ilike', $like)->orWhere('walkin_name', 'ilike', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', $like));
        })->latest('id')->limit(5)->get()->map(fn ($s) => ['t' => $s->invoice_no.' · '.\App\Support\Ui::bd($s->total).' · '.$s->created_at->timezone('Asia/Dhaka')->format('d-m'),
            'm' => $s->buyerName().($s->trip ? ' · '.($s->trip->route?->name ?? '').' · '.($s->trip->vehicle?->code ?? '') : '').($s->stage === 'voided' ? ' · বাতিল' : ''), 'u' => route('sales.show', $s)]);

        $trips = Trip::query()->with(['route', 'vehicle', 'salesman'])->where(function ($w) use ($like) {
            $w->where('trip_no', 'ilike', $like)->orWhereHas('route', fn ($r) => $r->where('name', 'ilike', $like))
                ->orWhereHas('vehicle', fn ($v) => $v->where('code', 'ilike', $like)->orWhere('reg_no', 'ilike', $like))
                ->orWhereHas('salesman', fn ($e) => $e->where('name', 'ilike', $like));
        })->latest('trip_date')->limit(5)->get()->map(fn ($t) => ['t' => $t->trip_no.' · '.$t->trip_date->format('d-m'),
            'm' => 'রুট '.($t->route?->name ?? '—').' · '.($t->vehicle?->code ?? '—').' · '.($t->salesman?->name ?? '—'), 'u' => route('trips.sales', $t)]);

        $groups = [];
        if ($cust->isNotEmpty()) $groups[] = ['g' => 'CUSTOMER', 'items' => $cust->values()];
        if ($inv->isNotEmpty()) $groups[] = ['g' => 'INVOICE', 'items' => $inv->values()];
        if ($trips->isNotEmpty()) $groups[] = ['g' => 'TRIP · ROUTE', 'items' => $trips->values()];
        return response()->json(['groups' => $groups]);
    }
}
