<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\ItemMovement;
use App\Models\Product;
use App\Support\Img;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()->with('brand')->orderBy('category')->orderBy('size_kg')->orderBy('name')->get();
        return view('products', [
            'products' => $products,
            'brands' => Brand::query()->where('for_sale', true)->orderBy('code')->get(),
            'edit' => $request->query('edit') ? Product::find($request->query('edit')) : null,
        ]);
    }

    public function save(Request $request)
    {
        $id = $request->input('id');
        $data = $request->validate([
            'id' => ['nullable', 'exists:products,id'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(Product::CATEGORIES))],
            'brand_id' => ['nullable', 'required_if:category,refill,new_package', 'exists:brands,id'],
            'size_kg' => ['nullable', 'required_if:category,refill,new_package', Rule::in(Brand::SIZES)],
            'retail_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'dealer_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
        ], ['image.mimes' => 'Photo must be a JPG, PNG or WebP picture.', 'image.max' => 'Photo is larger than 5 MB. Use a smaller picture.']);
        unset($data['id'], $data['image'], $data['remove_image']);
        if (in_array($data['category'], ['refill', 'new_package'], true) && $data['is_active']) {
            $twin = Product::query()->where('is_active', true)->where('category', $data['category'])->where('brand_id', $data['brand_id'])->where('size_kg', $data['size_kg'])
                ->when($id, fn ($q) => $q->where('id', '!=', $id))->first();
            if ($twin) return back()->withInput()->withErrors(['name' => "{$twin->name} ({$twin->code}) already sells this brand, size and type. Change its price instead, or turn it off first — two prices for one cylinder would confuse sales."]);
        }
        if (! in_array($data['category'], ['refill', 'new_package'], true)) {
            $data['brand_id'] = null; $data['size_kg'] = null;
        }
        if ($id) {
            $p = Product::findOrFail($id);
            $old = $p->only(['retail_price', 'dealer_price']);
            $p->fill($data)->save();
            AuditLog::record('product_updated', "{$p->code} {$p->name} · retail {$old['retail_price']}→{$p->retail_price} · dealer {$old['dealer_price']}→{$p->dealer_price}");
        } else {
            $n = (int) Product::query()->max('id') + 1;
            $p = Product::create($data + ['code' => 'P-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT)]);
            AuditLog::record('product_created', "{$p->code} {$p->name}");
        }
        if ($request->hasFile('image')) {
            $old = $p->image;
            try { $path = Img::save($request->file('image'), 'products', strtolower($p->code)); } catch (\RuntimeException $e) { return redirect()->route('products.index', ['edit' => $p->id])->withErrors(['image' => 'Product saved, but '.$e->getMessage()]); }
            $p->forceFill(['image' => $path])->save();
            Img::delete($old);
            AuditLog::record('product_photo', "{$p->code} {$p->name} · photo ".($old ? 'replaced' : 'added'));
        } elseif ($request->boolean('remove_image') && $p->image) {
            Img::delete($p->image);
            $p->forceFill(['image' => null])->save();
            AuditLog::record('product_photo', "{$p->code} {$p->name} · photo removed");
        }
        if (! $p->image && $p->brand_id && in_array($p->category, ['refill', 'new_package'], true) && ($bi = $p->brand?->img($p->size_kg))) {
            $p->forceFill(['image' => $bi])->save();
        }
        return redirect()->route('products.index')->with('ok', "{$p->name} saved.");
    }

    /** Quick photo upload from the product list row. */
    public function photo(Request $request, Product $product)
    {
        $request->validate(['image' => ['required', 'file', 'mimes:png,webp,jpg,jpeg', 'max:5120']], ['image.mimes' => 'Photo must be a JPG, PNG or WebP picture.', 'image.max' => 'Photo is larger than 5 MB. Use a smaller picture.']);
        $old = $product->image;
        try { $path = Img::save($request->file('image'), 'products', strtolower($product->code)); } catch (\RuntimeException $e) { return back()->withErrors(['image' => $e->getMessage()]); }
        $product->forceFill(['image' => $path])->save();
        Img::delete($old);
        AuditLog::record('product_photo', "{$product->code} {$product->name} · photo ".($old ? 'replaced' : 'added'));
        return back()->with('ok', "{$product->name}: photo saved.");
    }

    public function receive(Request $request, Product $product)
    {
        abort_unless($product->category === 'accessory', 404);
        $data = $request->validate(['qty' => ['required', 'integer', 'not_in:0', 'min:-10000', 'max:10000'], 'reference' => ['required', 'string', 'max:60']]);
        $have = (int) ItemMovement::query()->where('product_id', $product->id)->sum('qty_delta');
        if ($have + $data['qty'] < 0) return back()->withErrors(['qty' => "Only {$have} in stock."]);
        ItemMovement::create(['product_id' => $product->id, 'qty_delta' => $data['qty'], 'kind' => $data['qty'] > 0 ? 'received' : 'adjustment', 'reference' => $data['reference'], 'created_by' => $request->user()->id]);
        AuditLog::record('item_stock', "{$product->name} {$data['qty']} ({$data['reference']})");
        return back()->with('ok', "{$product->name} stock updated.");
    }
}
