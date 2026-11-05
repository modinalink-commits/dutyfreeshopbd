<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseClaim extends Model
{
    public const FLOW = ['draft' => 'Draft', 'submitted' => 'Pending approval', 'approved' => 'Approved', 'posted' => 'Posted', 'paid' => 'Paid', 'rejected' => 'Rejected'];
    public const PAYMENTS = ['cash' => 'Cash (office)', 'trip' => 'Cash (trip collection)', 'bank' => 'Bank', 'mobile' => 'bKash / Nagad', 'payable' => 'Unpaid — payable', 'salary' => 'Payable (with salary)'];
    public const CREDIT = ['cash' => '1110 Cash in hand', 'trip' => '1110 Cash in hand (trip)', 'bank' => '1120 Bank', 'mobile' => '1130 Mobile banking', 'payable' => '2130 Bills payable', 'salary' => '2220 Reimbursement payable'];
    protected $fillable = ['claim_no', 'category_id', 'expense_date', 'amount', 'cost_center', 'payment', 'account_id', 'trip_id', 'description', 'extra', 'splits', 'receipt_path',
        'status', 'reject_reason', 'created_by', 'submitted_at', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'expense_id', 'recurring_id'];
    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'extra' => 'array', 'splits' => 'array', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
    public function account() { return $this->belongsTo(MoneyAccount::class, 'account_id'); }
    public function trip() { return $this->belongsTo(Trip::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function immediate(): bool { return ! in_array($this->payment, ['payable', 'salary'], true); }
    public static function nextNo(): string
    {
        $base = 'EXP-'.now()->timezone('Asia/Dhaka')->format('ym').'-';
        $n = static::query()->where('claim_no', 'like', $base.'%')->count() + 1;
        do { $no = $base.str_pad((string) $n++, 3, '0', STR_PAD_LEFT); } while (static::query()->where('claim_no', $no)->exists());
        return $no;
    }
}
