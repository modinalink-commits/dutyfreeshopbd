<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CompanyEntry extends Model
{
    const UPDATED_AT = null;
    public const KINDS = ['opening' => 'Opening balance', 'deposit' => 'Deposit', 'challan' => 'Challan', 'incentive' => 'Incentive received', 'commission' => 'Commission received', 'adjustment' => 'Adjustment', 'reversal' => 'Reversal'];
    public const DEPOSIT_KINDS = ['bank' => 'Bank deposit', 'bill_payment' => 'Paid against bill', 'security' => 'Cylinder security deposit'];

    protected $fillable = ['entry_date', 'kind', 'deposit_kind', 'challan_no', 'size_kg', 'qty', 'do_rate', 'transport', 'amount', 'gross', 'ait', 'for_month', 'source', 'note', 'reversal_of', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'amount' => 'decimal:2', 'gross' => 'decimal:2', 'ait' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Company ledger entries cannot be changed. Post a reversal.'));
        static::deleting(fn () => throw new RuntimeException('Company ledger entries cannot be deleted. Post a reversal.'));
    }

    public function user() { return $this->belongsTo(User::class, 'created_by'); }
    public function reversedBy() { return $this->hasOne(CompanyEntry::class, 'reversal_of'); }

    public static function balanceUntil(string $date): float
    {
        return (float) static::query()->where('entry_date', '<=', $date)->sum('amount');
    }
}
