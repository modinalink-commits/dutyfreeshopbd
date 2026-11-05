<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CustomerLedger extends Model
{
    const UPDATED_AT = null;
    protected $table = 'customer_ledger';
    protected $fillable = ['customer_id', 'kind', 'reference', 'sale_id', 'debit', 'credit', 'method', 'note', 'created_by', 'trip_id', 'client_tx'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Ledger lines cannot be changed.'));
        static::deleting(fn () => throw new RuntimeException('Ledger lines cannot be deleted.'));
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class, 'created_by'); }
}
