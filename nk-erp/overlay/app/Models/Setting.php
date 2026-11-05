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
        'brand' => 'Product brand',
        'slogan' => 'Company slogan',
        'category' => 'Business category',
        'address_bn' => 'Business address (Bangla)',
        'address_en' => 'Business address (English)',
        'country' => 'Country',
        'division' => 'Division',
        'upazila' => 'Upazila',
        'area' => 'Area',
        'phone' => 'Phone number',
        'whatsapp' => 'WhatsApp number',
        'email' => 'Email address',
        'website' => 'Website',
        'trade_license' => 'Trade license number',
        'bin' => 'BIN / VAT registration',
        'tin' => 'TIN',
        'invoice_footer' => 'Invoice footer',
        'opening_hours' => 'Opening hours',
        'home_delivery_charge' => 'Home delivery charge (৳)',
    ];

    /** What an invoice stores at the moment it is made. */
    public const SNAPSHOT = ['company_name', 'brand', 'slogan', 'address_en', 'address_bn', 'phone', 'email', 'website', 'trade_license', 'bin', 'tin', 'invoice_footer', 'logo', 'signature'];

    public static function snapshot(): array
    {
        $all = static::query()->whereIn('key', self::SNAPSHOT)->pluck('value', 'key')->all();
        $all['logo'] = $all['logo'] ?? '/img/logo.webp';
        return $all;
    }

    public static function logo(): string
    {
        return static::get('logo') ?: '/img/logo.webp';
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
