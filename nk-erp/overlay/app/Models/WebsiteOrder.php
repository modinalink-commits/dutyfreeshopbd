<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteOrder extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'called' => 'Called',
        'confirmed' => 'Confirmed',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = ['name', 'phone', 'cylinder', 'type', 'quantity', 'address', 'status', 'note', 'ip', 'handled_by'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
