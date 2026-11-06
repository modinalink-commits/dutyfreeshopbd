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
}
