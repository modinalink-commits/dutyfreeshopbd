<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\CompanyEntry;
use App\Models\Customer;
use App\Models\ExpenseClaim;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Trip;
use Illuminate\Support\Collection;

/** Every exportable report. Rows are built from posted entries only; each report says which roles may export it. */
class Reports
{
    public const OFFICE = ['super_admin', 'admin', 'manager', 'accounts'];

    public static function all(): array
    {
        return [
            'Sales & distribution' => [
                'route_vehicle' => ['Route sales — vehicle-wise', self::OFFICE],
                'daily_sales' => ['Daily sales', self::OFFICE],
                'salesman' => ['Salesman performance', self::OFFICE],
                'loading' => ['Vehicle loading', self::OFFICE],
                'empties' => ['Empty cylinder collection', self::OFFICE],
                'sales_list' => ['Sales list & invoices', self::OFFICE],
            ],
            'Super Admin (restricted)' => [
                'company_ledger' => ['Company ledger', ['super_admin']],
            ],
            'Accounts' => [
                'expenses' => ['Expense list', ['super_admin', 'admin', 'accounts']],
            ],
            'Warehouse' => [
                'inventory' => ['Brand-wise inventory', self::OFFICE],
            ],
            'Customer' => [
                'customer_dues' => ['Customer dues & empties', self::OFFICE],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $mod => $list) if (isset($list[$key])) return ['module' => $mod, 'label' => $list[$key][0], 'roles' => $list[$key][1]];
        return null;
    }

    public static function allowed(string $key, string $role): bool
    {
        $r = self::find($key);
        return $r && in_array($role, $r['roles'], true);
    }

    /** @return array{0: array<string>, 1: array<array>, 2: ?array} headings, rows (numbers stay numbers), total row */
    public static function build(string $key, array $f): array
    {
        $from = $f['from']; $to = $f['to'];
        $utc = fn ($d, $end = false) => \Carbon\Carbon::parse($d, 'Asia/Dhaka')->{$end ? 'endOfDay' : 'startOfDay'}()->utc();
        $trips = fn () => Trip::query()->with(['vehicle', 'salesman', 'route', 'loads'])->whereBetween('trip_date', [$from, $to])->where('status', '!=', 'draft')
            ->when($f['vehicle'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))->when($f['route'] ?? null, fn ($q, $v) => $q->where('route_id', $v))->get();
        $sales = fn () => Sale::query()->with(['items', 'customer'])->where('stage', '!=', 'voided')->whereBetween('created_at', [$utc($from), $utc($to, true)])
            ->when($f['customer'] ?? null, fn ($q, $v) => $q->where('customer_id', $v))
            ->when($f['brand'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('brand_id', $v)))->get();
        switch ($key) {
            case 'route_vehicle':
                $rows = $trips()->groupBy('vehicle_id')->map(function (Collection $g) {
                    $c = $g->map(fn ($t) => TripCalc::of($t));
                    $t = $g->last();
                    return [$t->vehicle->code, $t->salesman?->name ?? '—', $t->route?->name ?? '—', $c->sum('soldTotal'), round($c->sum('totalSales'), 2), round($c->sum('collection'), 2)];
                })->sortBy(0)->values()->all();
                return [['Vehicle', 'Salesman', 'Route', 'Cylinders', 'Net sales', 'Collection'], $rows, ['Grand total', '', '', array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5))]];
            case 'daily_sales':
                $rows = $sales()->groupBy(fn ($s) => $s->created_at->timezone('Asia/Dhaka')->toDateString())->sortKeys()->map(fn ($g, $d) => [$d, $g->count(),
                    (int) $g->flatMap->items->whereIn('category', ['refill', 'new_package'])->sum('qty'), round($g->sum('total'), 2), round($g->sum('paid'), 2), round($g->sum(fn ($s) => $s->due()), 2)])->values()->all();
                return [['Date', 'Invoices', 'Cylinders', 'Sales', 'Paid at sale', 'Due'], $rows, ['Total', array_sum(array_column($rows, 1)), array_sum(array_column($rows, 2)), array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5))]];
            case 'salesman':
                $rows = $trips()->groupBy('salesman_id')->map(function ($g) {
                    $c = $g->map(fn ($t) => TripCalc::of($t));
                    return [$g->first()->salesman?->name ?? '—', $g->count(), $c->sum('soldTotal'), round($c->sum('totalSales'), 2), round($c->sum('collection'), 2), round($c->sum('credit'), 2)];
                })->sortByDesc(3)->values()->all();
                return [['Salesman', 'Trips', 'Cylinders', 'Sales', 'Collection', 'Credit'], $rows, null];
            case 'loading':
                $rows = $trips()->sortBy('trip_date')->map(fn ($t) => [$t->trip_date->toDateString(), $t->trip_no, $t->vehicle->code,
                    (int) $t->loads->where('size_kg', 12)->sum('qty'), (int) $t->loads->where('size_kg', 35)->sum('qty'), (int) $t->loads->where('size_kg', 45)->sum('qty'), (int) $t->loads->sum('qty'), \App\Models\Trip::STATUS_LABELS[$t->status]])->values()->all();
                return [['Date', 'Trip', 'Vehicle', '12 kg', '35 kg', '45 kg', 'Total', 'Status'], $rows, ['Total', '', '', array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), array_sum(array_column($rows, 6)), '']];
            case 'empties':
                $rows = $sales()->filter(fn ($s) => $s->empties_expected > 0)->map(fn ($s) => [$s->created_at->timezone('Asia/Dhaka')->format('Y-m-d H:i'), $s->invoice_no, $s->buyerName(),
                    (int) $s->empties_expected, (int) $s->empties_received, max(0, (int) $s->empties_expected - (int) $s->empties_received)])->values()->all();
                return [['Time', 'Invoice', 'Customer', 'Expected', 'Received', 'Pending'], $rows, ['Total', '', '', array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5))]];
            case 'sales_list':
                $rows = $sales()->sortBy('id')->map(fn ($s) => [$s->invoice_no, $s->created_at->timezone('Asia/Dhaka')->format('Y-m-d H:i'), $s->buyerName(), $s->type === 'wholesale' ? 'Wholesale' : ($s->trip_id ? 'Route' : 'Retail'),
                    round((float) $s->total, 2), round((float) $s->paid, 2), round($s->due(), 2), Sale::METHODS[$s->payment_method] ?? $s->payment_method])->values()->all();
                return [['Invoice', 'Date', 'Customer', 'Type', 'Total', 'Paid', 'Due', 'Payment'], $rows, ['Total', '', '', '', array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), array_sum(array_column($rows, 6)), '']];
            case 'company_ledger':
                $before = (float) CompanyEntry::query()->where('entry_date', '<', $from)->sum('amount');
                $run = $before;
                $rows = [['', 'Opening balance', '', '', '', '', round($before, 2)]];
                foreach (CompanyEntry::query()->whereBetween('entry_date', [$from, $to])->orderBy('entry_date')->orderBy('id')->get() as $e) {
                    $run += (float) $e->amount;
                    $rows[] = [$e->entry_date->toDateString(), ucfirst($e->kind), $e->challan_no ?? '', $e->size_kg ?? '', $e->qty ?? '', round((float) $e->amount, 2), round($run, 2)];
                }
                return [['Date', 'Entry', 'Challan', 'Size', 'Qty', 'Amount', 'Balance'], $rows, null];
            case 'expenses':
                $rows = self::expenseRows($from, $to);
                return [['Date', 'Category', 'Cost center', 'Amount', 'Status'], $rows, ['Total', '', '', array_sum(array_column($rows, 3)), '']];
            case 'inventory':
                $rows = [];
                foreach (Brand::query()->orderBy('code')->get() as $b) {
                    foreach (($b->sizes ?? []) as $s) {
                        $x = StockMovement::balance($b->id, (int) $s);
                        if (! array_sum($x)) continue;
                        $rows[] = [$b->code.' · '.$b->name, (int) $s.' kg', $x['full'], $x['empty'], $x['company'], $x['damaged']];
                    }
                }
                return [['Brand', 'Size', 'Full', 'Empty', 'Company-owned', 'Damaged'], $rows, ['Total', '', array_sum(array_column($rows, 2)), array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5))]];
            case 'customer_dues':
                $rows = Customer::query()->where('is_active', true)->orderBy('name')->get()->map(fn ($c) => [$c->code, $c->name, $c->phone, Customer::TYPES[$c->type] ?? $c->type, round($c->due(), 2), array_sum($c->cylindersOut()), round((float) $c->credit_limit, 2)])
                    ->filter(fn ($r) => $r[4] != 0 || $r[5] != 0)->values()->all();
                return [['Code', 'Customer', 'Mobile', 'Type', 'Due', 'Empties held', 'Credit limit'], $rows, ['Total', '', '', '', array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), '']];
        }
        return [[], [], null];
    }

    private static function expenseRows(string $from, string $to): array
    {
        $out = [];
        foreach (\App\Http\Controllers\ExpenseController::rows($from, $to)->sortBy('date') as $r) {
            $out[] = [$r['date'], $r['cat']?->name ?? 'Other', $r['cc'] ?? '—', round($r['amount'], 2), $r['paid'] ? 'Paid' : 'Approved, unpaid'];
        }
        return $out;
    }
}
