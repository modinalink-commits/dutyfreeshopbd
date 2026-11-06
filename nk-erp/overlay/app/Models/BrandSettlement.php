<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandSettlement extends Model
{
    protected $fillable = ['brand_id', 'size_kg', 'kind', 'qty_received', 'qty_delivered', 'amount', 'status', 'reference', 'exchange_id', 'agreement_ref', 'document', 'note', 'created_by'];

    protected function casts(): array { return ['amount' => 'float']; }

    public function brand() { return $this->belongsTo(Brand::class); }
    public function exchange() { return $this->belongsTo(CylinderExchange::class, 'exchange_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
