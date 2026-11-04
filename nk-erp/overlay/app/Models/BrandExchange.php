<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandExchange extends Model
{
    protected $fillable = ['ref_no', 'from_brand_id', 'to_brand_id', 'size_kg', 'qty', 'party', 'reason', 'status', 'requested_by', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function fromBrand() { return $this->belongsTo(Brand::class, 'from_brand_id'); }
    public function toBrand() { return $this->belongsTo(Brand::class, 'to_brand_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
