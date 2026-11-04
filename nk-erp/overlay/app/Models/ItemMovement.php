<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMovement extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['product_id', 'qty_delta', 'kind', 'reference', 'created_by'];
}
