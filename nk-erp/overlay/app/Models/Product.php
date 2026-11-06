<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const CATEGORIES = ['refill' => 'Refill', 'new_package' => 'New package', 'accessory' => 'Accessories', 'service' => 'Service'];

    protected $fillable = ['code', 'name', 'category', 'brand_id', 'size_kg', 'retail_price', 'dealer_price', 'note', 'is_active', 'image'];

    protected function casts(): array
    {
        return ['retail_price' => 'decimal:2', 'dealer_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function brand() { return $this->belongsTo(Brand::class); }

    public function isCylinder(): bool { return in_array($this->category, ['refill', 'new_package'], true); }

    /** Units available to sell now. Null = not stock-tracked (service). */
    public function available(): ?int
    {
        if ($this->isCylinder()) {
            return $this->brand_id && $this->size_kg ? StockMovement::balance($this->brand_id, $this->size_kg)['full'] : 0;
        }
        if ($this->category === 'accessory') {
            return (int) ItemMovement::query()->where('product_id', $this->id)->sum('qty_delta');
        }
        return null;
    }

    /**
     * Make sure an active brand has a Refill and a New package product for each of its sizes, with the brand's cylinder photo.
     * Only adds what is missing; never changes prices or existing products. New ones have no price, so they cannot be sold until priced.
     */
    public static function syncBrand(Brand $brand): int
    {
        if (! $brand->is_active) return 0;
        $made = 0;
        foreach ([['refill', 'Refill', 'Customer returns an empty cylinder'], ['new_package', 'New package', 'Cylinder + gas, no empty needed']] as [$cat, $label, $note]) {
            foreach (array_map('intval', $brand->sizes ?: [12]) as $size) {
                $q = static::query()->where('brand_id', $brand->id)->where('category', $cat)->where('size_kg', $size);
                if ($q->exists()) continue;
                $n = (int) static::query()->max('id') + 1;
                $code = 'P-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
                while (static::query()->where('code', $code)->exists()) { $n++; $code = 'P-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT); }
                $name = ($brand->for_sale ? '' : $brand->name.' ').$label.' '.$size.' kg';
                $p = static::create(['code' => $code, 'name' => $name, 'category' => $cat, 'brand_id' => $brand->id, 'size_kg' => $size, 'note' => $note, 'is_active' => true]);
                if ($img = $brand->img($size)) $p->forceFill(['image' => $img])->save();
                $made++;
            }
        }
        return $made;
    }
}
