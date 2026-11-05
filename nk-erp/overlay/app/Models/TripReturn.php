<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripReturn extends Model
{
    public $timestamps = false;
    protected $fillable = ['trip_id', 'size_kg', 'full_returned', 'damaged', 'empty_returned'];
}
