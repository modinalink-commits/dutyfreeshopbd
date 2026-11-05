<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpsPoint extends Model
{
    public $timestamps = false;
    protected $fillable = ['duty_id', 'employee_id', 'lat', 'lng', 'accuracy', 'speed', 'recorded_at'];
    protected function casts(): array { return ['recorded_at' => 'datetime', 'lat' => 'float', 'lng' => 'float']; }
}
