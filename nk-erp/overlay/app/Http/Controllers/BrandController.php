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
        $totals = StockMovement::query()->whereNull('trip_id')->selectRaw('brand_id, sum(full_delta) f, sum(empty_delta) e')->groupBy('brand_id')->get()->keyBy('brand_id');

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

    public function report(Request $request)
    {
        $type = in_array($request->query('type'), ['purchase', 'sales', 'empty', 'stock'], true) ? $request->query('type') : 'stock';
        $from = $request->query('from', now()->timezone('Asia/Dhaka')->startOfMonth()->toDateString());
        $to = $request->query('to', now()->timezone('Asia/Dhaka')->toDateString());
        $kinds = ['purchase' => ['received'], 'sales' => ['sale'], 'empty' => ['empty_in', 'exchange']];
        $q = StockMovement::query()->join('brands', 'brands.id', '=', 'stock_movements.brand_id');
        if ($type === 'stock') { $q->whereNull('stock_movements.trip_id'); }
        if ($type !== 'stock') {
            $q->whereIn('stock_movements.kind', $kinds[$type])
              ->whereBetween('stock_movements.created_at', [\Carbon\Carbon::parse($from, 'Asia/Dhaka')->startOfDay()->utc(), \Carbon\Carbon::parse($to, 'Asia/Dhaka')->endOfDay()->utc()]);
        }
        $rows = $q->selectRaw('brands.code, brands.name, stock_movements.size_kg, sum(full_delta) f, sum(empty_delta) e, count(*) n')
            ->groupBy('brands.code', 'brands.name', 'stock_movements.size_kg')->orderBy('brands.code')->orderBy('stock_movements.size_kg')->get();
        return view('brand_report', compact('type', 'from', 'to', 'rows'));
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

    /** Upload or replace the photo of one brand's cylinder at one size. Products of that brand and size that used the old picture follow. */
    public function photo(\Illuminate\Http\Request $request, Brand $brand)
    {
        $d = $request->validate([
            'size' => ['required', \Illuminate\Validation\Rule::in(Brand::SIZES)],
            'image' => ['required', 'file', 'mimes:png,webp,jpg,jpeg', 'max:5120'],
        ], ['image.mimes' => 'Photo must be a JPG, PNG or WebP picture.', 'image.max' => 'Photo is larger than 5 MB. Use a smaller picture.']);
        $size = (string) $d['size'];
        $before = $brand->img($size);
        $path = \App\Support\Img::save($request->file('image'), 'brands', strtolower($brand->code).'-'.$size);
        $imgs = $brand->images ?? [];
        $old = $imgs[$size] ?? null;
        $imgs[$size] = $path;
        $brand->forceFill(['images' => $imgs])->save();
        \App\Models\Product::query()->where('brand_id', $brand->id)->where('size_kg', $size)->whereIn('category', ['refill', 'new_package'])
            ->where(fn ($q) => $q->whereNull('image')->orWhere('image', $before))->update(['image' => $path]);
        \App\Support\Img::delete($old);
        \App\Models\AuditLog::record('brand_photo', "{$brand->code} {$brand->name} {$size} kg · photo ".($old ? 'replaced' : 'added'));
        return back()->with('ok', "{$brand->name} {$size} kg: photo saved.");
    }
}
