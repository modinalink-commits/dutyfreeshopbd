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
            ->orderBy('position')->orderBy('code')->get();

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
            'allBrands' => $all->sortBy(fn ($b) => [$b->position, $b->code]),
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
            'position' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);
        $pos = $data['position'] ?? null; unset($data['position']);
        $data['sizes'] = array_values(array_map('intval', $data['sizes']));
        sort($data['sizes']);
        unset($data['id']);

        if ($id) {
            $brand = Brand::findOrFail($id);
            $brand->fill($data)->save();
            AuditLog::record('brand_updated', $brand->code.' '.$brand->name);
        } else {
            $brand = Brand::create($data + ['code' => Brand::nextCode(), 'position' => (int) Brand::query()->max('position') + 1]);
            AuditLog::record('brand_created', $brand->code.' '.$brand->name);
        }

        if ($pos !== null && (int) $pos !== (int) $brand->position) self::placeAt($brand, (int) $pos);
        $made = \App\Models\Product::syncBrand($brand);
        return redirect()->route('brands.index')->with('ok', "{$brand->name} saved.".($made ? " {$made} products added to Products & prices — set their prices there." : ''));
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
        try { $path = \App\Support\Img::save($request->file('image'), 'brands', strtolower($brand->code).'-'.$size); } catch (\RuntimeException $e) { return back()->withErrors(['image' => $e->getMessage()]); }
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

    /**
     * Delete a brand that was never used. A brand with stock, sales, empties owed or exchanges keeps its history:
     * the owner is told to set it Inactive instead. Its unused products and uploaded photos go with it.
     */
    public function delete(Brand $brand)
    {
        if ($brand->id === \App\Support\ExchangeService::ownId()) return back()->withErrors(['name' => "{$brand->name} is our own brand and cannot be deleted."]);
        $uses = [
            'stock movements' => \App\Models\StockMovement::query()->where('brand_id', $brand->id)->count(),
            'sale lines' => \App\Models\SaleItem::query()->where('brand_id', $brand->id)->count(),
            'sales (empties received)' => \App\Models\Sale::query()->where('empty_brand_id', $brand->id)->count(),
            'customer cylinders' => \App\Models\CustomerCylinder::query()->where('brand_id', $brand->id)->count(),
            'brand exchanges' => BrandExchange::query()->where('from_brand_id', $brand->id)->orWhere('to_brand_id', $brand->id)->count(),
        ];
        $uses['cylinder exchanges'] = \App\Models\CylinderExchange::query()->where('given_brand_id', $brand->id)->orWhere('taken_brand_id', $brand->id)->count()
            + \App\Models\CylinderExchangeLine::query()->where('in_brand_id', $brand->id)->orWhere('out_brand_id', $brand->id)->count();
        $uses['brand settlement entries'] = \App\Models\BrandSettlement::query()->where('brand_id', $brand->id)->count();
        $used = array_filter($uses);
        if ($used) {
            $list = implode(', ', array_map(fn ($k, $v) => "{$v} {$k}", array_keys($used), $used));
            return back()->withErrors(['name' => "{$brand->name} cannot be deleted — it already has history ({$list}). Set it Inactive instead; the history stays correct."]);
        }
        $name = $brand->code.' '.$brand->name;
        \Illuminate\Support\Facades\DB::transaction(function () use ($brand) {
            foreach (\App\Models\Product::query()->where('brand_id', $brand->id)->get() as $p) {
                \App\Support\Img::delete($p->image);
                $p->delete();
            }
            foreach (($brand->images ?? []) as $img) \App\Support\Img::delete($img);
            if (\Illuminate\Support\Facades\Schema::hasTable('exchange_charges')) \Illuminate\Support\Facades\DB::table('exchange_charges')->where('brand_id', $brand->id)->delete();
            $brand->delete();
        });
        AuditLog::record('brand_deleted', $name);
        return redirect()->route('brands.index')->with('ok', "{$name} deleted.");
    }

    /** Put a brand at a list position (1 = top) and renumber the rest 1..n, so every list follows the same order. */
    public static function placeAt(Brand $brand, int $pos): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($brand, $pos) {
            $ids = Brand::query()->where('id', '!=', $brand->id)->orderBy('position')->orderBy('code')->lockForUpdate()->pluck('id')->all();
            $pos = max(1, min($pos, count($ids) + 1));
            array_splice($ids, $pos - 1, 0, [$brand->id]);
            foreach ($ids as $i => $id) Brand::query()->whereKey($id)->update(['position' => $i + 1]);
        });
    }

    /** ▲ / ▼ buttons and the position box in the brand list. */
    public function move(Request $request, Brand $brand)
    {
        $data = $request->validate(['dir' => ['nullable', Rule::in(['up', 'down', 'top', 'bottom'])], 'to' => ['nullable', 'integer', 'min:1', 'max:9999']]);
        $cur = (int) Brand::query()->where(fn ($q) => $q->where('position', '<', $brand->position)->orWhere(fn ($w) => $w->where('position', $brand->position)->where('code', '<', $brand->code)))->count() + 1;
        $to = $data['to'] ?? match ($data['dir'] ?? null) { 'up' => $cur - 1, 'down' => $cur + 1, 'top' => 1, 'bottom' => 9999, default => $cur };
        self::placeAt($brand, (int) $to);
        $now = $brand->fresh()->position;
        AuditLog::record('brand_position', "{$brand->code} {$brand->name} → position {$now}");
        return redirect()->to(route('brands.index', array_filter(['q' => $request->input('q'), 'f' => $request->input('f')])).'#b'.$brand->id)->with('ok', "{$brand->name} is now at position {$now}.");
    }
}
