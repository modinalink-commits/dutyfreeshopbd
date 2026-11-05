<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    public const STATUS_LABELS = ['draft' => 'Waiting approval', 'dispatched' => 'In market', 'returned' => 'Returned', 'settled' => 'Closing pending', 'closed' => 'Closed'];

    protected $fillable = ['trip_no', 'trip_date', 'vehicle_id', 'route_id', 'driver_id', 'salesman_id', 'warehouse_staff_id', 'loading_time', 'status', 'remarks',
        'counted_by', 'counted_at', 'approved_by', 'dispatched_at', 'return_time', 'return_checked_by', 'return_remarks', 'returned_at',
        'cash_notes', 'cash_counted', 'digital_verified', 'received_by', 'settled_at', 'closed_by', 'closed_at', 'return_requested_at'];

    protected function casts(): array
    {
        return ['trip_date' => 'date', 'counted_at' => 'datetime', 'dispatched_at' => 'datetime', 'returned_at' => 'datetime', 'settled_at' => 'datetime', 'closed_at' => 'datetime', 'return_requested_at' => 'datetime',
            'cash_notes' => 'array', 'cash_counted' => 'decimal:2', 'digital_verified' => 'decimal:2'];
    }

    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function route() { return $this->belongsTo(Route::class); }
    public function driver() { return $this->belongsTo(Employee::class, 'driver_id'); }
    public function salesman() { return $this->belongsTo(Employee::class, 'salesman_id'); }
    public function warehouseStaff() { return $this->belongsTo(Employee::class, 'warehouse_staff_id'); }
    public function returnChecker() { return $this->belongsTo(Employee::class, 'return_checked_by'); }
    public function receiver() { return $this->belongsTo(User::class, 'received_by'); }
    public function loads() { return $this->hasMany(TripLoad::class); }
    public function returns() { return $this->hasMany(TripReturn::class); }
    public function adjustments() { return $this->hasMany(TripAdjustment::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function expenses() { return $this->hasMany(Expense::class); }

    public function locked(): bool { return $this->status === 'closed'; }

    public static function nextNo(Vehicle $v, string $date): string
    {
        $base = 'TRP-'.date('dm', strtotime($date)).'-'.substr($v->code, 2);
        $no = $base; $i = 2;
        while (static::query()->where('trip_no', $no)->exists()) { $no = $base.'-'.$i++; }
        return $no;
    }
}
