<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DutySession extends Model
{
    protected $fillable = ['employee_id', 'trip_id', 'started_at', 'ended_at', 'gps_consent'];
    protected function casts(): array { return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'gps_consent' => 'boolean']; }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function trip() { return $this->belongsTo(Trip::class); }
    public function points() { return $this->hasMany(GpsPoint::class, 'duty_id'); }
}
