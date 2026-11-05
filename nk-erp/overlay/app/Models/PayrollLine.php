<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollLine extends Model
{
    public $timestamps = false;
    protected $fillable = ['payroll_id', 'employee_id', 'days', 'leave_days', 'basic', 'allowance', 'cylinders', 'commission', 'overtime_hours', 'overtime', 'bonus', 'reimbursement', 'advance_recovery', 'loan_recovery', 'deductions', 'net'];
    public function employee() { return $this->belongsTo(Employee::class); }
    /** Salary cost: basic, allowance, commission, overtime and bonus. Reimbursements are not salary. */
    public function gross(): float { return (float) $this->basic + (float) $this->allowance + (float) $this->commission + (float) $this->overtime + (float) $this->bonus; }
    public function computeNet(): float
    {
        return round($this->gross() + (float) $this->reimbursement - (float) $this->advance_recovery - (float) $this->loan_recovery - (float) $this->deductions, 2);
    }
}
