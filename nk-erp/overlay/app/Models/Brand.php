<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    public const SIZES = [12, 35, 45];

    protected $fillable = ['code', 'name', 'company_name', 'sizes', 'is_active', 'for_sale', 'empty_collection', 'remarks'];

    protected function casts(): array
    {
        return ['sizes' => 'array', 'is_active' => 'boolean', 'for_sale' => 'boolean', 'empty_collection' => 'boolean'];
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public static function nextCode(): string
    {
        $max = static::query()->pluck('code')->map(fn ($c) => (int) substr($c, 3))->max() ?? 0;
        return 'BR-'.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }
}
