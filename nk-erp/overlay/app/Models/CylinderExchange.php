<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderExchange extends Model
{
    protected $fillable = ['ref_no', 'channel', 'trip_id', 'customer_id', 'walkin_name', 'walkin_phone', 'size_kg', 'qty', 'given_brand_id', 'taken_brand_id',
        'unit_price', 'charge_unit', 'total', 'paid', 'method', 'status', 'sale_id', 'note', 'decision_note', 'requested_by', 'decided_by', 'decided_at',
        'type', 'exchange_date', 'deposit_adj', 'attachment', 'photo', 'ack_name', 'hold_reason', 'client_tx', 'reversed_by', 'reversed_at', 'reverse_reason'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'reversed_at' => 'datetime', 'exchange_date' => 'date', 'unit_price' => 'float', 'charge_unit' => 'float', 'total' => 'float', 'paid' => 'float', 'deposit_adj' => 'float'];
    }

    public const STATUS = ['pending' => 'Waiting for approval', 'held' => 'Held for review', 'approved' => 'Posted', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];
    public const STATUS_BN = ['pending' => 'অনুমোদনের অপেক্ষায়', 'held' => 'রিভিউতে আটকে', 'approved' => 'অনুমোদিত', 'rejected' => 'বাতিল', 'reversed' => 'ফেরত নেওয়া'];
    public const TYPES = [
        'A' => 'Own brand empty → own brand full',
        'B' => 'Other brand empty → own brand full',
        'C' => 'Own brand empty → other brand full',
        'D' => 'Other brand empty → other brand full',
        'E' => 'Empty cylinder swap only',
    ];
    public const CONDITIONS = ['good' => 'Good', 'valve' => 'Valve problem', 'damaged' => 'Damaged', 'unknown' => 'Not checked'];
    public const OWNERSHIP = ['customer' => 'Customer-owned', 'company' => 'Our cylinder coming back', 'brand' => 'Brand-owned (settle with brand)', 'unknown' => 'Unknown'];

    public function lines() { return $this->hasMany(CylinderExchangeLine::class, 'exchange_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function trip() { return $this->belongsTo(Trip::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function givenBrand() { return $this->belongsTo(Brand::class, 'given_brand_id'); }
    public function takenBrand() { return $this->belongsTo(Brand::class, 'taken_brand_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }

    public function buyer(): string { return $this->customer?->name ?? ($this->walkin_name ?: 'Walk-in'); }

    /** Types switched on by an administrator (Exchange rate settings). All on until changed. */
    public static function enabledTypes(): array
    {
        $v = Setting::get('exchange_types');
        return $v === null ? array_keys(self::TYPES) : array_values(array_filter(explode(',', $v), fn ($t) => isset(self::TYPES[$t])));
    }

    public function typeLabel(): string { return $this->type === 'MX' ? 'Mixed' : ($this->type ? $this->type.' · '.(self::TYPES[$this->type] ?? '') : '—'); }

    public static function nextRef(): string
    {
        $last = static::query()->orderByDesc('id')->value('ref_no');
        $n = $last ? ((int) substr($last, 3)) + 1 : 1;
        return 'EX-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
