<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyAccount extends Model
{
    public const TYPES = ['cash' => 'Cash', 'bank' => 'Bank', 'mobile' => 'bKash / Nagad'];
    protected $fillable = ['name', 'type', 'opening_balance', 'opening_date', 'is_default'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'opening_date' => 'date', 'is_default' => 'boolean'];
    }
}
