<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    public const STATUSES = ['Available', 'Loading', 'Dispatched', 'In market', 'Returned', 'Closing pending', 'Closed', 'Maintenance'];
    public const TYPES = ['Pickup', 'Covered van', 'Truck', 'Van', 'Other'];
    protected $fillable = ['code', 'reg_no', 'type', 'capacity', 'route_id', 'driver_id', 'salesman_id', 'status', 'remarks'];

    public function route() { return $this->belongsTo(Route::class); }
    public function driver() { return $this->belongsTo(Employee::class, 'driver_id'); }
    public function salesman() { return $this->belongsTo(Employee::class, 'salesman_id'); }
    public function trips() { return $this->hasMany(Trip::class); }
}
