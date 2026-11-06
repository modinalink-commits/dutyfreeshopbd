<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class StockController extends Controller
{
    public function index()
    {
        $bySize = StockMovement::query()->whereNull('trip_id')
            ->selectRaw('size_kg, sum(full_delta) f, sum(empty_delta) e, sum(company_delta) c, sum(damaged_delta) d')
            ->groupBy('size_kg')->get()->keyBy('size_kg');

        $now = now()->timezone('Asia/Dhaka');
        $sold = StockMovement::query()->where('kind', 'sale')->whereNull('reversal_of')->where('created_at', '>=', $now->copy()->startOfMonth()->utc())
            ->selectRaw('size_kg, -sum(full_delta) q')->groupBy('size_kg')->pluck('q', 'size_kg');
        $withCustomers = \App\Models\CustomerCylinder::query()->selectRaw('size_kg, sum(qty_delta) q')->groupBy('size_kg')->pluck('q', 'size_kg');
        $onRoadW = \App\Models\SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.stage', 'on_road')
            ->whereNotNull('sale_items.size_kg')->selectRaw('sale_items.size_kg, sum(sale_items.qty) q')->groupBy('sale_items.size_kg')->pluck('q', 'size_kg');
        $onVeh = StockMovement::query()->whereNotNull('trip_id')->selectRaw('size_kg, sum(full_delta + empty_delta) q')->groupBy('size_kg')->pluck('q', 'size_kg');
        $onRoad = collect(Brand::SIZES)->mapWithKeys(fn ($s) => [$s => (int) ($onRoadW[$s] ?? 0) + (int) ($onVeh[$s] ?? 0)]);
        $sizes = collect(Brand::SIZES)->map(function ($s) use ($bySize, $sold, $now, $withCustomers, $onRoad) {
            $full = (int) ($bySize[$s]->f ?? 0);
            $avg = ((int) ($sold[$s] ?? 0)) / max(1, $now->day);
            $days = $avg > 0 ? $full / $avg : null;
            return [
                'size' => $s, 'full' => $full,
                'empty' => (int) ($bySize[$s]->e ?? 0),
                'company' => (int) ($bySize[$s]->c ?? 0),
                'damaged' => (int) ($bySize[$s]->d ?? 0),
                'out' => max(0, (int) ($withCustomers[$s] ?? 0)),
                'road' => (int) ($onRoad[$s] ?? 0),
                'days' => $days, 'low' => $days !== null && $days < 3,
            ];
        });
        $deposit = null;
        if (auth()->user()->isSuperAdmin()) {
            $sec = fn ($from, $to) => (float) \App\Models\CompanyEntry::query()->where('kind', 'deposit')->whereDoesntHave('reversedBy')->where('deposit_kind', 'security')->whereBetween('entry_date', [$from, $to])->sum('amount');
            $lm = $now->copy()->subMonthNoOverflow();
            $deposit = [
                'last' => ['name' => $lm->format('F'), 'amt' => $sec($lm->copy()->startOfMonth()->toDateString(), $lm->copy()->endOfMonth()->toDateString()), 'on' => $now->copy()->day(15)],
                'this' => ['name' => $now->format('F'), 'amt' => $sec($now->copy()->startOfMonth()->toDateString(), $now->toDateString()), 'on' => $now->copy()->addMonthNoOverflow()->day(15)],
            ];
        }

        $grid = StockMovement::query()->with('brand')->whereNull('trip_id')
            ->selectRaw('brand_id, size_kg, sum(full_delta) f, sum(empty_delta) e, sum(company_delta) c, sum(damaged_delta) d')
            ->groupBy('brand_id', 'size_kg')->get()
            ->filter(fn ($r) => $r->f || $r->e || $r->c || $r->d)
            ->sortBy(fn ($r) => ($r->brand->code ?? '').'-'.str_pad((string) $r->size_kg, 3, '0', STR_PAD_LEFT));

        $today = StockMovement::query()->with(['brand', 'user'])
            ->where('created_at', '>=', now()->timezone('Asia/Dhaka')->startOfDay()->utc())
            ->latest('id')->limit(50)->get();

        return view('stock.index', compact('sizes', 'grid', 'today', 'deposit'));
    }

    public function history(Request $request)
    {
        $moves = StockMovement::query()->with(['brand', 'user', 'reversedBy'])
            ->when($request->query('brand'), fn ($q, $b) => $q->where('brand_id', $b))
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->latest('id')->paginate(50)->withQueryString();

        return view('stock.history', ['moves' => $moves, 'brands' => Brand::orderBy('code')->get()]);
    }

    public function create(Request $request)
    {
        $kind = $request->query('kind', 'received');
        if (! isset(StockMovement::KINDS[$kind]) || in_array($kind, ['exchange', 'reversal'], true)) {
            $kind = 'received';
        }
        return view('stock.create', [
            'kind' => $kind,
            'brands' => Brand::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('empty_collection', true))->orderBy('position')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['opening', 'received', 'to_company', 'empty_in', 'sale', 'damaged', 'damaged_out', 'adjustment'])],
            'brand_id' => ['required', 'exists:brands,id'],
            'size_kg' => ['required', Rule::in(Brand::SIZES)],
            'full' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'empty' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'from_company' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'damaged_from' => ['nullable', Rule::in(['full', 'empty'])],
            'adj_full' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
            'adj_empty' => ['nullable', 'integer', 'min:-100000', 'max:100000'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $brand = Brand::findOrFail($data['brand_id']);
        $size = (int) $data['size_kg'];
        if (! in_array($size, $brand->sizes ?? [], true)) {
            return back()->withInput()->withErrors(['size_kg' => "{$brand->name} is not set up for {$size} kg. Add the size in Cylinder brands first."]);
        }

        $f = (int) ($data['full'] ?? 0);
        $e = (int) ($data['empty'] ?? 0);
        $role = $request->user()->role;
        $d = ['full_delta' => 0, 'empty_delta' => 0, 'company_delta' => 0, 'damaged_delta' => 0];

        switch ($data['kind']) {
            case 'opening':
                if (StockMovement::query()->where('brand_id', $brand->id)->where('size_kg', $size)->exists()) {
                    return back()->withInput()->withErrors(['kind' => 'Opening stock can be entered only once, before any other movement. Use a count adjustment instead.']);
                }
                $d['full_delta'] = $f; $d['empty_delta'] = $e;
                break;
            case 'received':
                if (! $brand->for_sale) {
                    return back()->withInput()->withErrors(['brand_id' => "{$brand->name} is not marked for sale, so full cylinders cannot be received for it."]);
                }
                if (empty($data['reference'])) {
                    return back()->withInput()->withErrors(['reference' => 'Enter the company challan number.']);
                }
                $c = (int) ($data['from_company'] ?? 0);
                $d['full_delta'] = $f; $d['empty_delta'] = -$e; $d['company_delta'] = -$c;
                break;
            case 'to_company':
                $d['empty_delta'] = -$e; $d['company_delta'] = $e;
                break;
            case 'empty_in':
                if (! $brand->empty_collection) {
                    return back()->withInput()->withErrors(['brand_id' => "Empty collection is switched off for {$brand->name}."]);
                }
                $d['empty_delta'] = $e;
                break;
            case 'sale':
                $d['full_delta'] = -$f; $d['empty_delta'] = $e;
                break;
            case 'damaged':
                $from = $data['damaged_from'] ?? 'full';
                $q = $f;
                $d[$from.'_delta'] = -$q; $d['damaged_delta'] = $q;
                break;
            case 'damaged_out':
                $d['damaged_delta'] = -$e;
                break;
            case 'adjustment':
                if (! in_array($role, ['super_admin', 'admin'], true)) {
                    abort(403, 'Only Super Admin or Admin can adjust counts.');
                }
                if (empty($data['note'])) {
                    return back()->withInput()->withErrors(['note' => 'Write the reason for the adjustment.']);
                }
                $d['full_delta'] = (int) ($data['adj_full'] ?? 0); $d['empty_delta'] = (int) ($data['adj_empty'] ?? 0);
                break;
        }

        if (! array_filter($d)) {
            return back()->withInput()->withErrors(['full' => 'Enter a quantity.']);
        }

        try {
            $m = StockMovement::post($d + [
                'kind' => $data['kind'], 'brand_id' => $brand->id, 'size_kg' => $size,
                'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null,
            ]);
        } catch (RuntimeException $ex) {
            return back()->withInput()->withErrors(['full' => \App\Support\Ui::msg($ex)]);
        }
        AuditLog::record('stock_'.$m->kind, "{$m->ref_no} {$brand->name} {$size}kg");

        return redirect()->route('stock.index')->with('ok', "{$m->ref_no} saved: ".StockMovement::KINDS[$m->kind].", {$brand->name} {$size} kg.");
    }

    public function reverse(Request $request, StockMovement $movement)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if ($movement->kind === 'reversal' || $movement->reversedBy()->exists()) {
            return back()->withErrors(['reason' => 'This entry is already reversed or is itself a reversal.']);
        }
        if ($movement->kind === 'exchange') {
            return back()->withErrors(['reason' => 'Exchange entries are reversed together from the exchange record.']);
        }
        try {
            $r = StockMovement::post([
                'kind' => 'reversal', 'brand_id' => $movement->brand_id, 'size_kg' => $movement->size_kg,
                'full_delta' => -$movement->full_delta, 'empty_delta' => -$movement->empty_delta,
                'company_delta' => -$movement->company_delta, 'damaged_delta' => -$movement->damaged_delta,
                'reference' => $movement->ref_no, 'note' => $request->input('reason'), 'reversal_of' => $movement->id,
            ]);
        } catch (RuntimeException $ex) {
            return back()->withErrors(['reason' => \App\Support\Ui::msg($ex)]);
        }
        AuditLog::record('stock_reversal', "{$r->ref_no} reverses {$movement->ref_no}");

        return back()->with('ok', "{$movement->ref_no} reversed by {$r->ref_no}.");
    }
}
