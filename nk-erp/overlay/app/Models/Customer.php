<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public const TYPES = ['retail' => 'Retail', 'commercial' => 'Commercial', 'dealer' => 'Dealer'];

    protected $fillable = ['code', 'name', 'shop_name', 'phone', 'type', 'area', 'address', 'credit_limit', 'is_active', 'note', 'lat', 'lng', 'portal_enabled', 'portal_last_seen', 'owner_name', 'geo_source', 'geo_label'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean', 'portal_enabled' => 'boolean', 'portal_last_seen' => 'datetime'];
    }

    public function ledger() { return $this->hasMany(CustomerLedger::class); }

    public function due(): float
    {
        return (float) CustomerLedger::query()->where('customer_id', $this->id)->selectRaw('coalesce(sum(debit - credit),0) d')->value('d');
    }

    public function cylindersOut(): array
    {
        return CustomerCylinder::query()->where('customer_id', $this->id)
            ->selectRaw('size_kg, sum(qty_delta) q')->groupBy('size_kg')->pluck('q', 'size_kg')
            ->map(fn ($q) => (int) $q)->filter()->all();
    }

    public static function nextCode(): string
    {
        $n = (int) static::query()->max('id') + 1;
        return 'C-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function normalisePhone(string $p): string
    {
        $d = preg_replace('/\D/', '', $p);
        if (str_starts_with($d, '880')) $d = substr($d, 2);
        if (str_starts_with($d, '88')) $d = substr($d, 2);
        return $d;
    }
}
