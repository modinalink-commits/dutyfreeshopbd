<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    public const STATUS = ['queued' => 'Queued', 'sent' => 'Sent', 'failed' => 'Failed', 'no_mobile' => 'No mobile', 'off' => 'Gateway not set'];
    protected $fillable = ['kind', 'to', 'body', 'sale_id', 'customer_id', 'status', 'response', 'attempts', 'sent_at'];
    protected function casts(): array { return ['sent_at' => 'datetime']; }
    public function sale() { return $this->belongsTo(Sale::class); }
}
