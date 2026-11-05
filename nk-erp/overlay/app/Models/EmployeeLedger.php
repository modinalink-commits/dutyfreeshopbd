<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EmployeeLedger extends Model
{
    protected $table = 'employee_ledger';
    const UPDATED_AT = null;
    public const KINDS = ['opening' => 'Opening balance', 'salary' => 'Basic + allowance', 'commission' => 'Commission', 'overtime' => 'Overtime', 'bonus' => 'Bonus',
        'reimbursement' => 'Expense reimbursement', 'advance' => 'Salary advance paid', 'loan' => 'Loan paid', 'advance_recovery' => 'Advance recovery', 'loan_recovery' => 'Loan recovery',
        'deduction' => 'Authorized deduction', 'payment' => 'Salary paid', 'adjustment' => 'Adjustment'];
    protected $fillable = ['employee_id', 'entry_date', 'kind', 'reference', 'earned', 'deducted', 'advance_delta', 'loan_delta', 'account_id', 'cash_out', 'payroll_id', 'note', 'created_by'];
    protected function casts(): array
    {
        return ['entry_date' => 'date', 'earned' => 'decimal:2', 'deducted' => 'decimal:2', 'advance_delta' => 'decimal:2', 'loan_delta' => 'decimal:2', 'cash_out' => 'decimal:2'];
    }
    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Posted employee ledger lines are locked. Post an adjustment.'));
        static::deleting(fn () => throw new RuntimeException('Posted employee ledger lines are locked. Post an adjustment.'));
    }
    public function account() { return $this->belongsTo(MoneyAccount::class, 'account_id'); }
}
