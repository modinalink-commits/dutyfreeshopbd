<?php

namespace App\Http\Controllers;

use App\Models\CompanyEntry;
use App\Models\CompanyMonth;
use App\Models\CompanyRate;
use App\Models\CustomerLedger;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\WebsiteOrder;
use App\Support\CompanyCalc;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        if (auth()->user()->role === 'salesman') return redirect()->route('app.home');
        $tz = 'Asia/Dhaka';
        $now = now()->timezone($tz);
        $dayStart = $now->copy()->startOfDay()->utc();
        $monthStart = $now->copy()->startOfMonth()->utc();
        $ym = $now->format('Y-m');

        $live = fn () => Sale::query()->where('stage', '!=', 'voided');
        $itemsSince = fn ($from) => SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.stage', '!=', 'voided')->where('sales.created_at', '>=', $from)->whereNotNull('sale_items.size_kg');

        $todaySales = (float) $live()->where('created_at', '>=', $dayStart)->sum('total');
        $todayCyl = (int) $itemsSince($dayStart)->sum('sale_items.qty');
        $todayKg = (int) $itemsSince($dayStart)->selectRaw('coalesce(sum(sale_items.qty * sale_items.size_kg),0) kg')->value('kg');
        $monthCyl = (int) $itemsSince($monthStart)->sum('sale_items.qty');
        $monthKg = (int) $itemsSince($monthStart)->selectRaw('coalesce(sum(sale_items.qty * sale_items.size_kg),0) kg')->value('kg');
        $monthSales = (float) $live()->where('created_at', '>=', $monthStart)->sum('total');

        // Gross profit = sales − company bill (DO − transport) for the cylinders sold.
        $cost = 0;
        foreach ($itemsSince($monthStart)->select('sale_items.size_kg', 'sale_items.qty', 'sale_items.category', 'sales.created_at')->get() as $i) {
            $r = CompanyRate::forDate((int) $i->size_kg, Carbon::parse($i->created_at)->timezone($tz)->toDateString());
            if ($r) $cost += $i->qty * ((float) $r->do_rate - (float) $r->transport);
        }
        $gross = $monthSales - $cost;

        $dues = CustomerLedger::query()->selectRaw('customer_id, sum(debit - credit) d')->groupBy('customer_id')->havingRaw('sum(debit - credit) > 0.004')->get();
        $collected = (float) CustomerLedger::query()->where('kind', 'payment')->where('created_at', '>=', $dayStart)->whereIn('method', ['cash', 'bkash', 'nagad'])->sum('credit')
            + (float) Sale::query()->whereNull('customer_id')->where('stage', '!=', 'voided')->where('created_at', '>=', $dayStart)->sum('paid');

        // Bars: cylinders per day this month, retail vs wholesale.
        $bars = [];
        $rows = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.stage', '!=', 'voided')
            ->where('sales.created_at', '>=', $monthStart)->whereNotNull('sale_items.size_kg')
            ->get(['sales.type', 'sales.created_at', 'sale_items.qty']);
        for ($d = 1; $d <= $now->day; $d++) $bars[$d] = ['r' => 0, 'w' => 0];
        foreach ($rows as $r) { $d = Carbon::parse($r->created_at)->timezone($tz)->day; $bars[$d][$r->type === 'wholesale' ? 'w' : 'r'] += $r->qty; }

        // Company target this month (tons lifted from company challans).
        $month = CompanyMonth::find($ym);
        $lifted = (float) CompanyEntry::query()->where('kind', 'challan')->whereBetween('entry_date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])
            ->whereDoesntHave('reversedBy')->selectRaw('coalesce(sum(qty * size_kg),0) kg')->value('kg') / 1000;

        // Needs attention.
        $alerts = [];
        $full12 = (int) StockMovement::query()->whereNull('trip_id')->where('size_kg', 12)->sum('full_delta');
        $avg12 = $now->day > 0 ? (int) $itemsSince($monthStart)->where('sale_items.size_kg', 12)->sum('sale_items.qty') / max(1, $now->day) : 0;
        if ($avg12 > 0 && $full12 / $avg12 < 3) $alerts[] = ['t' => '12 kg full stock low', 'd' => "{$full12} left, about ".round($full12 / $avg12, 1).' days of sales. Place a company DO today.', 'link' => route('stock.index'), 'l' => 'Stock →', 'tone' => 'neg'];
        $old = Sale::query()->where('stage', 'delivered')->whereColumn('paid', '<', 'total')->where('delivered_at', '<', now()->subDays(30))->get();
        if ($old->count()) $alerts[] = ['t' => $old->pluck('customer_id')->unique()->count().' dealers over 30 days due', 'd' => '৳'.number_format($old->sum(fn ($s) => $s->due())).' in total.', 'link' => route('customers.index', ['f' => 'due']), 'l' => 'Customers →', 'tone' => 'warn'];
        $newOrders = WebsiteOrder::query()->where('status', 'new')->count();
        if ($newOrders) $alerts[] = ['t' => "{$newOrders} new website orders", 'd' => 'Call the customers to confirm.', 'link' => route('orders.index', ['status' => 'new']), 'l' => 'Orders →', 'tone' => 'blue'];
        $calc = null;
        if (auth()->user()->isSuperAdmin()) {
            $calc = CompanyCalc::month($now->copy()->subMonthNoOverflow()->format('Y-m'));
            $mn = Carbon::parse($calc['month']->month.'-01')->format('F');
            if (! $calc['incentiveReceived'] && $calc['net'] > 0) $alerts[] = ['t' => "{$mn} incentive arrives ".Carbon::parse($calc['incentiveDate'])->format('d-m-Y'), 'd' => 'added to the company balance as Deposit + Incentive.', 'tone' => 'vio'];
            if (! $calc['commissionReceived'] && $calc['commission'] > 0) $alerts[] = ['t' => "{$mn} commission arrives ".Carbon::parse($calc['commissionDate'])->format('d-m-Y'), 'd' => 'Tk '.rtrim(rtrim(number_format($calc['month']->kg_commission, 2), '0'), '.').' per kg on total '.$mn.' sales.', 'tone' => 'vio'];
        }

        // Distribution network today: routes (zones), planned shops, shops sold to on today's trips.
        $net = rescue(function () use ($now) {
            $routes = \App\Models\Route::query()->where('is_active', true)->get();
            $trips = \App\Models\Trip::query()->whereDate('trip_date', $now->toDateString())->where('status', '!=', 'draft')->get(['id', 'route_id']);
            $ts = Sale::query()->whereIn('trip_id', $trips->pluck('id'))->where('stage', '!=', 'voided')->with('items')->get();
            $shops = (int) $routes->sum('shops');
            $visited = $ts->pluck('customer_id')->filter()->unique()->count();
            $top = $trips->groupBy('route_id')->map(function ($g, $rid) use ($ts, $routes) {
                $s = $ts->whereIn('trip_id', $g->pluck('id'));
                return ['name' => $routes->firstWhere('id', $rid)?->name ?? '—', 'amt' => (float) $s->sum('total'), 'cyl' => (int) $s->flatMap->items->whereNotNull('size_kg')->sum('qty')];
            })->sortByDesc('amt')->first();
            return ['zones' => $routes->count(), 'shops' => $shops, 'visited' => $visited, 'unvisited' => max(0, $shops - $visited), 'trips' => $trips->count(), 'top' => $top && $top['amt'] > 0 ? $top : null];
        }, null, false);

        return view('dashboard', [
            'net' => $net,
            'now' => $now, 'todaySales' => $todaySales, 'todayCyl' => $todayCyl, 'todayKg' => $todayKg,
            'monthCyl' => $monthCyl, 'monthKg' => $monthKg, 'gross' => $gross,
            'balance' => CompanyEntry::balanceUntil($now->toDateString()),
            'dues' => (float) $dues->sum('d'), 'dueCount' => $dues->count(), 'collected' => $collected,
            'bars' => $bars, 'month' => $month, 'lifted' => $lifted, 'alerts' => $alerts,
            'latest' => Sale::query()->with(['customer', 'items'])->latest('id')->limit(6)->get(),
        ]);
    }
}
