<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandExchange;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $filter = $request->query('f');
        $totals = StockMovement::query()->selectRaw('brand_id, sum(full_delta) f, sum(empty_delta) e')->groupBy('brand_id')->get()->keyBy('brand_id');

        $brands = Brand::query()
            ->when($q !== '', fn ($w) => $w->where('name', 'ilike', "%{$q}%"))
            ->when($filter === 'active', fn ($w) => $w->where('is_active', true))
            ->when($filter === 'inactive', fn ($w) => $w->where('is_active', false))
            ->orderBy('code')->get();

        $all = Brand::query()->get();
        $own = $all->where('for_sale', true)->pluck('id');
        $otherEmpty = $totals->reject(fn ($t, $id) => $own->contains($id))->sum('e');

        $edit = $request->query('edit') ? Brand::find($request->query('edit')) : null;

        return view('brands', [
            'brands' => $brands,
            'totals' => $totals,
            'q' => $q,
            'filter' => $filter,
            'stats' => [
                'count' => $all->count(),
                'forSale' => $all->where('for_sale', true)->count(),
                'trackOnly' => $all->where('for_sale', false)->where('is_active', true)->count(),
                'otherEmpty' => (int) $otherEmpty,
                'waiting' => BrandExchange::query()->where('status', 'waiting')->count(),
            ],
            'exchanges' => BrandExchange::query()->with(['fromBrand', 'toBrand', 'requester'])->where('status', 'waiting')->latest()->get(),
            'edit' => $edit,
            'nextCode' => Brand::nextCode(),
            'allBrands' => $all->sortBy('code'),
        ]);
    }

    public function save(Request $request)
    {
        $id = $request->input('id');
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:80', Rule::unique('brands', 'name')->ignore($id)],
            'company_name' => ['nullable', 'string', 'max:150'],
            'sizes' => ['required', 'array', 'min:1'],
            'sizes.*' => ['integer', Rule::in(Brand::SIZES)],
            'is_active' => ['required', 'boolean'],
            'for_sale' => ['required', 'boolean'],
            'empty_collection' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);
        $data['sizes'] = array_values(array_map('intval', $data['sizes']));
        sort($data['sizes']);
        unset($data['id']);

        if ($id) {
            $brand = Brand::findOrFail($id);
            $brand->fill($data)->save();
            AuditLog::record('brand_updated', $brand->code.' '.$brand->name);
        } else {
            $brand = Brand::create($data + ['code' => Brand::nextCode()]);
            AuditLog::record('brand_created', $brand->code.' '.$brand->name);
        }

        return redirect()->route('brands.index')->with('ok', "{$brand->name} saved.");
    }
}
