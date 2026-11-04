<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public const FIELDS = [
        'company_name' => 'Company name',
        'brand' => 'Brand',
        'slogan' => 'Slogan',
        'phone' => 'Phone',
        'whatsapp' => 'WhatsApp number',
        'email' => 'Email',
        'address_en' => 'Address (English)',
        'address_bn' => 'Address (Bangla)',
        'trade_license' => 'Trade license no.',
        'tin' => 'TIN',
        'opening_hours' => 'Opening hours',
        'home_delivery_charge' => 'Home delivery charge (৳)',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
