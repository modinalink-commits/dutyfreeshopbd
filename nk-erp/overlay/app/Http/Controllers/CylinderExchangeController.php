<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CylinderExchange;
use App\Models\ExchangeCharge;
use App\Models\Sale;
use App\Support\ExchangeService;
use App\Support\Ui;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Brand exchange at the counter or on the route: customer hands over one brand's empty and takes another brand's full.
 * Every request from the route app waits for office approval; office managers can post a counter exchange straight away.
 */
class CylinderExchangeController extends Controller
{
    private const DECIDERS = ['super_admin', 'admin', 'manager'];

    public function index(Request $request)
    {
        $status = in_array($request->query('s'), ['pending', 'approved', 'rejected'], true) ? $request->query('s') : null;
        $list = CylinderExchange::query()->with(['customer', 'givenBrand', 'takenBrand', 'requester', 'decider', 'sale', 'trip.vehicle'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")->orderByDesc('id')->paginate(30)->withQueryString();
        $brands = Brand::query()->where('is_active', true)->orderByDesc('for_sale')->orderBy('name')->get();
        $own = $brands->firstWhere('for_sale', true);
        $charges = ExchangeCharge::query()->get()->groupBy('brand_id')->map(fn ($r) => $r->pluck('charge', 'size_kg'));
        $today = CylinderExchange::query()->where('status', 'approved')->whereDate('decided_at', today());
        $stats = [
            'pending' => CylinderExchange::query()->where('status', 'pending')->count(),
            'today' => (int) (clone $today)->sum('qty'),
            'todayAmt' => (float) (clone $today)->sum('total'),
            'month' => (int) CylinderExchange::query()->where('status', 'approved')->where('decided_at', '>=', now()->startOfMonth())->sum('qty'),
        ];
        $canDecide = in_array($request->user()->role, self::DECIDERS, true);
        $canCharge = in_array($request->user()->role, ['super_admin', 'admin'], true);
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'phone']);

        return view('exchange.index', compact('list', 'brands', 'own', 'charges', 'stats', 'status', 'canDecide', 'canCharge', 'customers'));
    }

    /** Live price for the form. */
    public function quote(Request $request)
    {
        try {
            $q = ExchangeService::quote((int) $request->query('given'), (int) $request->query('taken'), (int) $request->query('size'), 'counter');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => Ui::msg($e)]);
        }
        return response()->json(['ok' => true, 'unit' => $q['unit'], 'charge' => $q['charge'], 'product' => $q['product']->name]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'given_brand_id' => ['required', 'exists:brands,id', 'different:taken_brand_id'],
            'taken_brand_id' => ['required', 'exists:brands,id'],
            'size_kg' => ['required', Rule::in(Brand::SIZES)],
            'qty' => ['required', 'integer', 'min:1', 'max:500'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'walkin_name' => ['nullable', 'string', 'max:120'],
            'walkin_phone' => ['nullable', 'string', 'max:20'],
            'method' => ['required', Rule::in(array_keys(Sale::METHODS))],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['given_brand_id.different' => 'Customer gives and takes the same brand — that is a normal refill, use Retail sale (POS).']);
        $user = $request->user();
        try {
            $x = DB::transaction(function () use ($data, $user) {
                $q = ExchangeService::quote((int) $data['given_brand_id'], (int) $data['taken_brand_id'], (int) $data['size_kg'], 'counter');
                $total = round($data['qty'] * ($q['unit'] + $q['charge']), 2);
                $paid = $data['method'] === 'due' ? (float) ($data['paid'] ?? 0) : $total;
                if ($paid > $total + 0.004) throw new RuntimeException('Paid amount is more than the bill.');
                if (empty($data['customer_id']) && $paid < $total - 0.004) throw new RuntimeException('A walk-in customer must pay in full. Pick a saved customer to leave a due.');
                Ui::lockNumbers();
                $x = CylinderExchange::create([
                    'ref_no' => CylinderExchange::nextRef(), 'channel' => 'counter', 'customer_id' => $data['customer_id'] ?? null,
                    'walkin_name' => $data['walkin_name'] ?? null, 'walkin_phone' => $data['walkin_phone'] ?? null,
                    'size_kg' => $data['size_kg'], 'qty' => $data['qty'], 'given_brand_id' => $data['given_brand_id'], 'taken_brand_id' => $data['taken_brand_id'],
                    'unit_price' => $q['unit'], 'charge_unit' => $q['charge'], 'total' => $total, 'paid' => $paid, 'method' => $data['method'],
                    'status' => 'pending', 'note' => $data['note'] ?? null, 'requested_by' => $user->id,
                ]);
                if (in_array($user->role, self::DECIDERS, true)) ExchangeService::post($x, $user);
                else AuditLog::record('cylinder_exchange', "{$x->ref_no} requested · {$x->qty} × {$x->size_kg} kg");
                return $x;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => Ui::msg($e)]);
        }
        if ($x->sale_id) return redirect()->route('sales.show', $x->sale_id)->with('ok', "{$x->ref_no} done — invoice {$x->sale->invoice_no}.");
        return redirect()->route('cx.index')->with('ok', "{$x->ref_no} sent for approval.");
    }

    public function approve(Request $request, CylinderExchange $exchange)
    {
        if ($exchange->status !== 'pending') return back()->withErrors(['qty' => "{$exchange->ref_no} is already ".strtolower(CylinderExchange::STATUS[$exchange->status]).'.']);
        try {
            DB::transaction(function () use ($exchange, $request) {
                $x = CylinderExchange::query()->lockForUpdate()->findOrFail($exchange->id);
                if ($x->status !== 'pending') throw new RuntimeException("{$x->ref_no} was decided by someone else just now.");
                if ($x->channel === 'route' && $x->trip && $x->trip->status === 'closed') throw new RuntimeException("The trip for {$x->ref_no} is already closed. Reject it and enter it at the counter instead.");
                ExchangeService::post($x, $request->user());
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['qty' => Ui::msg($e)]);
        }
        return back()->with('ok', "{$exchange->ref_no} approved — sale posted.");
    }

    public function reject(Request $request, CylinderExchange $exchange)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $n = CylinderExchange::query()->whereKey($exchange->id)->where('status', 'pending')
            ->update(['status' => 'rejected', 'decision_note' => $data['reason'], 'decided_by' => $request->user()->id, 'decided_at' => now(), 'updated_at' => now()]);
        if (! $n) return back()->withErrors(['qty' => "{$exchange->ref_no} is not waiting any more."]);
        AuditLog::record('cylinder_exchange', "{$exchange->ref_no} rejected: {$data['reason']}");
        return back()->with('ok', "{$exchange->ref_no} rejected.");
    }

    public function saveCharges(Request $request)
    {
        $data = $request->validate(['c' => ['array'], 'c.*' => ['array'], 'c.*.*' => ['nullable', 'numeric', 'min:0', 'max:100000']]);
        $n = 0;
        foreach ($data['c'] ?? [] as $brandId => $sizes) {
            if (! Brand::query()->whereKey($brandId)->exists()) continue;
            foreach ($sizes as $size => $v) {
                if (! in_array((int) $size, Brand::SIZES, true)) continue;
                $cur = ExchangeCharge::query()->where('brand_id', $brandId)->where('size_kg', $size)->first();
                if ($v === null || $v === '') { if ($cur) { $cur->delete(); $n++; } continue; }
                if ($cur && (float) $cur->charge === (float) $v) continue;
                ExchangeCharge::updateOrCreate(['brand_id' => $brandId, 'size_kg' => $size], ['charge' => $v, 'updated_by' => $request->user()->id]);
                $n++;
            }
        }
        if ($n) AuditLog::record('exchange_charges', "{$n} exchange charge(s) changed");
        return redirect()->route('cx.index')->with('ok', $n ? "{$n} exchange charge(s) saved." : 'No change.');
    }
}
