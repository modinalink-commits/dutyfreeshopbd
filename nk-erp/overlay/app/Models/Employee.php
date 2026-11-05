<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    public const ROLES = ['driver' => 'Driver', 'salesman' => 'Salesman', 'warehouse' => 'Warehouse staff', 'manager' => 'Manager', 'accountant' => 'Accountant', 'other' => 'Other'];
    protected $fillable = ['code', 'name', 'phone', 'role', 'is_active', 'user_id', 'department', 'designation', 'joined_on', 'basic', 'allowance', 'commission_rate', 'bank_account', 'left_on'];
    public const DEPARTMENTS = ['Distribution', 'Warehouse', 'Accounts', 'Counter / POS', 'Management', 'Other'];
    protected function casts(): array { return ['is_active' => 'boolean', 'joined_on' => 'date', 'left_on' => 'date', 'basic' => 'decimal:2', 'allowance' => 'decimal:2', 'commission_rate' => 'decimal:2']; }
    public function ledger() { return $this->hasMany(EmployeeLedger::class); }
    public function balances(): array
    {
        $r = EmployeeLedger::query()->where('employee_id', $this->id)->selectRaw('coalesce(sum(earned - deducted),0) p, coalesce(sum(advance_delta),0) a, coalesce(sum(loan_delta),0) l')->first();
        return ['payable' => (float) $r->p, 'advance' => (float) $r->a, 'loan' => (float) $r->l];
    }
    public function monthly(): float { return (float) $this->basic + (float) $this->allowance; }

    public static function nextCode(): string
    {
        return 'E-'.str_pad((string) ((int) static::query()->max('id') + 1), 3, '0', STR_PAD_LEFT);
    }
}
