<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseRule extends Model
{
    public const APPROVERS = ['manager' => 'Manager', 'super_admin' => 'Super Admin'];
    protected $fillable = ['who', 'up_to', 'above', 'verified_by', 'approver'];
    protected function casts(): array { return ['up_to' => 'decimal:2', 'above' => 'decimal:2']; }

    /** Who must approve this amount: Super Admin when any rule sends an amount this size to the Super Admin. Checked on the server. */
    public static function approverFor(float $amount, bool $duty = false): string
    {
        $rules = static::query()->where('approver', 'super_admin')->get()
            ->filter(fn ($r) => $duty ? true : ! str_contains(strtolower($r->who), 'driver'));
        foreach ($rules as $r) {
            if ($r->above !== null && $amount > (float) $r->above) return 'super_admin';
        }
        return 'manager';
    }
}
