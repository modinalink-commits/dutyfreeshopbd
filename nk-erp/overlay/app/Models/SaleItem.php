<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['sale_id', 'product_id', 'description', 'category', 'brand_id', 'size_kg', 'qty', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function product() { return $this->belongsTo(Product::class); }
}
