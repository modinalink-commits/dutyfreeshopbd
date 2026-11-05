<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrder extends Model
{
    public const STATUS = ['new' => 'New', 'approved' => 'Approved', 'on_trip' => 'On trip', 'delivered' => 'Delivered', 'rejected' => 'Rejected'];
    public const BN = ['new' => 'অফিসে পাঠানো হয়েছে', 'approved' => 'অনুমোদিত', 'on_trip' => 'গাড়িতে উঠেছে', 'delivered' => 'ডেলিভারি সম্পন্ন', 'rejected' => 'বাতিল'];
    protected $fillable = ['order_no', 'customer_id', 'items', 'address', 'wanted_on', 'note', 'status', 'route_id', 'vehicle_id', 'salesman_id', 'trip_id', 'delivery_date', 'approved_by', 'approved_at', 'reject_reason', 'sale_id'];
    protected function casts(): array { return ['items' => 'array', 'wanted_on' => 'date', 'delivery_date' => 'date', 'approved_at' => 'datetime']; }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function route() { return $this->belongsTo(Route::class); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function salesman() { return $this->belongsTo(Employee::class, 'salesman_id'); }
    public function trip() { return $this->belongsTo(Trip::class); }
    public function itemsText(bool $bn = false): string
    {
        return collect($this->items)->map(fn ($i) => ($bn ? \App\Support\Ui::bn($i['size']).' কেজি' : $i['size'].' kg').' × '.$i['qty'].(($i['type'] ?? 'ex') === 'nw' ? ($bn ? ' নতুন' : ' new') : ''))->implode(', ');
    }
    public static function nextNo(): string
    {
        $base = 'ORD-'.now()->timezone('Asia/Dhaka')->format('dm').'-';
        $n = static::query()->where('order_no', 'like', $base.'%')->count() + 1;
        do { $no = $base.str_pad((string) $n++, 3, '0', STR_PAD_LEFT); } while (static::query()->where('order_no', $no)->exists());
        return $no;
    }
}
