<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeCharge extends Model
{
    protected $fillable = ['brand_id', 'size_kg', 'charge', 'updated_by'];

    protected function casts(): array { return ['charge' => 'float']; }

    public function brand() { return $this->belongsTo(Brand::class); }

    /** Charge per cylinder for swapping between these two brands at this size: set on the brand that is not ours. */
    public static function for(int $givenBrandId, int $takenBrandId, int $size): float
    {
        if ($givenBrandId === $takenBrandId) return 0.0;
        $own = (int) Brand::query()->where('for_sale', true)->value('id');
        $other = $givenBrandId !== $own ? $givenBrandId : $takenBrandId;
        return (float) (static::query()->where('brand_id', $other)->where('size_kg', $size)->value('charge') ?? 0);
    }
}
