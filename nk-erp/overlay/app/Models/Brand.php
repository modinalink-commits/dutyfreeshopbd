<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    public const SIZES = [12, 35, 45];

    protected $fillable = ['code', 'name', 'company_name', 'sizes', 'is_active', 'for_sale', 'empty_collection', 'remarks', 'images'];

    protected function casts(): array
    {
        return ['sizes' => 'array', 'is_active' => 'boolean', 'for_sale' => 'boolean', 'empty_collection' => 'boolean', 'images' => 'array'];
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

    /** Photo of this brand's cylinder at a size: an uploaded one, else the bundled picture with the brand name and size on it. */
    public function img(int|string|null $size): ?string
    {
        $size = (string) (int) $size;
        if (! empty($this->images[$size])) return $this->images[$size];
        $f = '/img/cylinders/'.self::slug($this->name).'-'.$size.'.webp';
        return is_file(public_path(ltrim($f, '/'))) ? $f : null;
    }

    public static function slug(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
    }
}
