<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCylinder extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['customer_id', 'brand_id', 'size_kg', 'qty_delta', 'reference', 'sale_id'];
    public function customer() { return $this->belongsTo(Customer::class); }
}
