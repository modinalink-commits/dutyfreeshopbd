<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    public const SIZES = [12, 35, 45];

    protected $fillable = ['code', 'name', 'company_name', 'sizes', 'is_active', 'for_sale', 'empty_collection', 'remarks', 'images',
        'position', 'exchange_allowed', 'exchange_partner', 'ownership_policy', 'agreement_ref', 'deposit_rule', 'settlement_rule', 'fee_rule'];

    public const OWNERSHIP = ['swap' => 'Ownership passes with the swap', 'brand_owned' => 'Stays with the brand (settle with them)', 'deposit' => 'Customer deposit cylinder'];

    /** Cross-brand exchange with this brand is allowed only with an approved agreement on record. */
    public function exchangeAuthorized(): bool { return $this->for_sale || ($this->is_active && $this->exchange_allowed && $this->exchange_partner && trim((string) $this->agreement_ref) !== ''); }

    protected function casts(): array
    {
        return ['sizes' => 'array', 'is_active' => 'boolean', 'for_sale' => 'boolean', 'empty_collection' => 'boolean', 'images' => 'array', 'exchange_allowed' => 'boolean', 'exchange_partner' => 'boolean'];
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
