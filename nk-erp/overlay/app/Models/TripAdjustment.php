<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripAdjustment extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['trip_id', 'kind', 'detail', 'reason', 'approved_by'];
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
