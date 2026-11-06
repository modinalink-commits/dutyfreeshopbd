<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderExchange extends Model
{
    protected $fillable = ['ref_no', 'channel', 'trip_id', 'customer_id', 'walkin_name', 'walkin_phone', 'size_kg', 'qty', 'given_brand_id', 'taken_brand_id',
        'unit_price', 'charge_unit', 'total', 'paid', 'method', 'status', 'sale_id', 'note', 'decision_note', 'requested_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'unit_price' => 'float', 'charge_unit' => 'float', 'total' => 'float', 'paid' => 'float'];
    }

    public const STATUS = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    public const STATUS_BN = ['pending' => 'অনুমোদনের অপেক্ষায়', 'approved' => 'অনুমোদিত', 'rejected' => 'বাতিল'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function trip() { return $this->belongsTo(Trip::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function givenBrand() { return $this->belongsTo(Brand::class, 'given_brand_id'); }
    public function takenBrand() { return $this->belongsTo(Brand::class, 'taken_brand_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }

    public function buyer(): string { return $this->customer?->name ?? ($this->walkin_name ?: 'Walk-in'); }

    public static function nextRef(): string
    {
        $last = static::query()->orderByDesc('id')->value('ref_no');
        $n = $last ? ((int) substr($last, 3)) + 1 : 1;
        return 'EX-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
