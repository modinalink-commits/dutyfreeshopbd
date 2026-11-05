<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Trip;

/** Every number of one trip, worked out from posted entries only. */
class TripCalc
{
    public static function of(Trip $trip): array
    {
        $trip->loadMissing(['loads', 'returns', 'adjustments']);
        $sales = Sale::query()->with(['items', 'customer'])->where('trip_id', $trip->id)->where('stage', '!=', 'voided')->get();
        $sizes = [];
        foreach (Brand::SIZES as $s) {
            $loaded = (int) $trip->loads->where('size_kg', $s)->sum('qty');
            $exch = (int) $sales->flatMap->items->where('size_kg', $s)->where('category', 'refill')->sum('qty');
            $new = (int) $sales->flatMap->items->where('size_kg', $s)->where('category', 'new_package')->sum('qty');
            $ret = $trip->returns->firstWhere('size_kg', $s);
            $collected = (int) StockMovement::query()->where('trip_id', $trip->id)->where('kind', 'empty_in')->where('size_kg', $s)->whereDoesntHave('reversedBy')->sum('empty_delta');
            $vehicle = StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $s)->selectRaw('coalesce(sum(full_delta),0) f, coalesce(sum(empty_delta),0) e')->first();
            $pending = (int) CustomerCylinder::query()->whereIn('sale_id', $sales->pluck('id'))->where('size_kg', $s)->sum('qty_delta');
            if (! $loaded && ! $exch && ! $new && ! $collected) continue;
            $sizes[$s] = [
                'loaded' => $loaded, 'exch' => $exch, 'new' => $new,
                'full_returned' => (int) ($ret->full_returned ?? 0), 'damaged' => (int) ($ret->damaged ?? 0), 'empty_returned' => (int) ($ret->empty_returned ?? 0),
                'missing' => $loaded - $exch - $new - (int) ($ret->full_returned ?? 0) - (int) ($ret->damaged ?? 0),
                'collected' => $collected, 'pending' => max(0, $pending), 'expected' => $exch,
                'emptyDiff' => (int) ($ret->empty_returned ?? 0) - $collected,
                'vehFull' => (int) $vehicle->f, 'vehEmpty' => (int) $vehicle->e,
            ];
        }
        // Money is read from what was posted on this trip, so later payments at the office are not counted here.
        $pays = CustomerLedger::query()->where('trip_id', $trip->id)->where('kind', 'payment')->get();
        $walkin = $sales->whereNull('customer_id');
        $sum = fn ($rows, $m, $field) => (float) $rows->whereIn($field === 'paid' ? 'payment_method' : 'method', (array) $m)->sum($field === 'paid' ? 'paid' : 'credit');
        $digital = ['bkash', 'nagad', 'bank'];
        $atSale = $pays->whereNotNull('sale_id');
        $cashSales = $sum($walkin, 'cash', 'paid') + $sum($atSale, 'cash', 'credit');
        $digitalSales = $sum($walkin, $digital, 'paid') + $sum($atSale, $digital, 'credit');
        $credit = max(0, (float) $sales->sum('total') - $cashSales - $digitalSales);
        $prevDue = CustomerLedger::query()->with('customer')->where('trip_id', $trip->id)->where('kind', 'payment')->whereNull('sale_id')->get();
        $prevCash = (float) $prevDue->where('method', 'cash')->sum('credit');
        $prevDigital = (float) $prevDue->whereIn('method', $digital)->sum('credit');
        $creditShops = $sales->map(fn ($x) => ['sale' => $x, 'due' => (float) $x->total - (float) $atSale->where('sale_id', $x->id)->sum('credit') - ($x->customer_id ? 0 : (float) $x->paid)])->filter(fn ($r) => $r['due'] > 0.004)->values();
        $digitalRows = collect($digital)->map(fn ($m) => ['method' => $m, 'count' => $walkin->where('payment_method', $m)->where('paid', '>', 0)->count() + $pays->where('method', $m)->count(),
            'amount' => $sum($walkin, $m, 'paid') + $sum($pays, $m, 'credit')])->filter(fn ($r) => $r['amount'] > 0)->values();
        $expenses = Expense::query()->where('trip_id', $trip->id)->get();
        $expFromCash = (float) $expenses->where('from_collection', true)->sum('amount');
        $cashCollected = $cashSales + $prevCash;
        $expected = $cashCollected - $expFromCash;
        $counted = $trip->cash_counted !== null ? (float) $trip->cash_counted : null;
        $cashAdj = $trip->adjustments->where('kind', 'cash')->isNotEmpty();
        $stockAdj = $trip->adjustments->where('kind', 'stock')->isNotEmpty();
        $stockDiff = collect($sizes)->filter(fn ($z) => $z['vehFull'] !== 0 || $z['vehEmpty'] !== 0);
        $cashDiff = $counted === null ? null : round($counted - $expected, 2);
        return [
            'sales' => $sales, 'sizes' => $sizes, 'invoices' => $sales->count(),
            'loadedTotal' => array_sum(array_column($sizes, 'loaded')), 'soldTotal' => array_sum(array_column($sizes, 'exch')) + array_sum(array_column($sizes, 'new')),
            'returnedTotal' => array_sum(array_column($sizes, 'full_returned')), 'collectedTotal' => array_sum(array_column($sizes, 'collected')),
            'emptyReturnedTotal' => array_sum(array_column($sizes, 'empty_returned')), 'pendingTotal' => array_sum(array_column($sizes, 'pending')),
            'totalSales' => (float) $sales->sum('total'), 'cashSales' => $cashSales, 'digitalSales' => $digitalSales, 'credit' => $credit,
            'creditShops' => $creditShops, 'digitalRows' => $digitalRows, 'prevDue' => $prevDue, 'prevCash' => $prevCash, 'prevDigital' => $prevDigital,
            'collection' => $cashSales + $prevCash + $digitalSales + $prevDigital, 'digitalTotal' => $digitalSales + $prevDigital,
            'expenses' => $expenses, 'expTotal' => (float) $expenses->sum('amount'), 'expFromCash' => $expFromCash,
            'cashCollected' => $cashCollected, 'expected' => $expected, 'counted' => $counted, 'cashDiff' => $cashDiff,
            'stockDiff' => $stockDiff, 'stockOk' => $stockDiff->isEmpty(), 'cost' => self::cost($sales), 'cashAdj' => $cashAdj, 'stockAdj' => $stockAdj, 'cashOk' => $cashDiff !== null && (abs($cashDiff) < 0.5 || $cashAdj),
        ];
    }

    /** Cost of the gas sold, from the company DO rate on the sale date. Null parts are counted in 'unrated'. */
    public static function cost($sales): array
    {
        $cost = 0.0; $unrated = 0;
        foreach ($sales as $sale) {
            $day = $sale->created_at->timezone('Asia/Dhaka')->toDateString();
            foreach ($sale->items as $i) {
                if (! in_array($i->category, ['refill', 'new_package'], true) || ! $i->size_kg) continue;
                $r = \App\Models\CompanyRate::forDate((int) $i->size_kg, $day);
                if (! $r) { $unrated += $i->qty; continue; }
                $cost += $i->qty * (float) $r->do_rate;
            }
        }
        return ['amount' => $cost, 'unrated' => $unrated];
    }

    public static function stage(Trip $trip, array $c): string
    {
        if ($trip->status === 'closed') return 'Closed';
        if ($trip->status === 'draft') return 'Waiting approval';
        if ($trip->status === 'dispatched') return 'In market';
        if ($trip->status === 'returned') return 'Returned';
        return ($c['stockOk'] && $c['cashOk']) ? 'Closing pending' : 'Mismatch found';
    }
}
