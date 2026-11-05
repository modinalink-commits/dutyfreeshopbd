<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripLoad extends Model
{
    public $timestamps = false;
    protected $fillable = ['trip_id', 'size_kg', 'qty'];
}
