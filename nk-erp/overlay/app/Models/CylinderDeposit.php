<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money held for a customer's cylinder. A liability: it is never counted as sales. */
class CylinderDeposit extends Model
{
    protected $fillable = ['customer_id', 'walkin_name', 'exchange_id', 'amount', 'method', 'reference', 'note', 'created_by'];

    protected function casts(): array { return ['amount' => 'float']; }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function exchange() { return $this->belongsTo(CylinderExchange::class, 'exchange_id'); }
}
