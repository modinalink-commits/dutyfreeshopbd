<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class Expense extends Model
{
    const UPDATED_AT = null;
    public const HEADS = ['Fuel', 'Salary', 'Rent', 'Electricity', 'Toll & parking', 'Loading / unloading', 'Repair', 'Mobile & internet', 'Office', 'Other'];
    protected $fillable = ['expense_date', 'head', 'amount', 'account_id', 'note', 'reversal_of', 'created_by'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Posted expenses cannot be changed. Post a reversal.'));
        static::deleting(fn () => throw new RuntimeException('Posted expenses cannot be deleted. Post a reversal.'));
    }

    public function account() { return $this->belongsTo(MoneyAccount::class, 'account_id'); }
    public function reversedBy() { return $this->hasOne(Expense::class, 'reversal_of'); }
}
