<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    public const STAGES = ['waiting' => 'Waiting to load', 'on_road' => 'On the road', 'delivered' => 'Delivered', 'posted' => 'Posted', 'voided' => 'Voided'];
    public const METHODS = ['cash' => 'Cash', 'bkash' => 'bKash', 'nagad' => 'Nagad', 'bank' => 'Bank', 'due' => 'Due'];

    protected $fillable = ['invoice_no', 'type', 'customer_id', 'walkin_name', 'walkin_phone', 'stage', 'subtotal', 'discount', 'delivery_charge', 'total', 'paid', 'payment_method', 'empty_brand_id', 'empties_expected', 'empties_received', 'vehicle', 'note', 'created_by', 'delivered_at', 'voided_by', 'voided_at', 'void_reason', 'trip_id', 'company'];

    protected static function booted(): void
    {
        static::creating(function (Sale $s) { if (! $s->company) $s->company = Setting::snapshot(); });
        static::created(fn (Sale $s) => rescue(fn () => \App\Support\Sms::forSale($s), null, false));
    }

    public function trip() { return $this->belongsTo(Trip::class); }

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'delivery_charge' => 'decimal:2', 'total' => 'decimal:2', 'paid' => 'decimal:2', 'delivered_at' => 'datetime', 'voided_at' => 'datetime', 'company' => 'array'];
    }

    public function items() { return $this->hasMany(SaleItem::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class, 'created_by'); }
    public function emptyBrand() { return $this->belongsTo(Brand::class, 'empty_brand_id'); }

    public function buyerName(): string { return $this->customer?->name ?? $this->walkin_name ?? 'Walk-in customer'; }
    public function buyerPhone(): ?string { return $this->customer?->phone ?? $this->walkin_phone; }
    public function due(): float { return max(0, (float) $this->total - (float) $this->paid); }

    public function wholesaleStatus(): string
    {
        if ($this->stage === 'voided') return 'Voided';
        if ($this->stage !== 'delivered') return self::STAGES[$this->stage] ?? $this->stage;
        if ($this->due() <= 0) return 'Closed';
        return (float) $this->paid > 0 ? 'Part paid' : 'Delivered, unpaid';
    }

    public static function nextNo(string $type): string
    {
        $p = $type === 'wholesale' ? 'W-' : 'R-';
        $last = static::query()->where('invoice_no', 'like', $p.'%')->orderByDesc('id')->value('invoice_no');
        $n = $last ? ((int) substr($last, 2)) + 1 : 1;
        return $p.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
