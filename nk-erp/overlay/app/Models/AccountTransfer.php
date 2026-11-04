<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountTransfer extends Model
{
    const UPDATED_AT = null;
    protected $fillable = ['transfer_date', 'from_account_id', 'to_account_id', 'amount', 'note', 'created_by'];
    protected function casts(): array { return ['transfer_date' => 'date', 'amount' => 'decimal:2']; }
}
