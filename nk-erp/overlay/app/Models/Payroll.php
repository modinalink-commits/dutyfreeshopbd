<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    public const STATUS = ['draft' => 'Prepared', 'checked' => 'Checked', 'approved' => 'Approved', 'paid' => 'Paid'];
    protected $fillable = ['month', 'status', 'prepared_by', 'prepared_at', 'checked_by', 'checked_at', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'account_id'];
    protected function casts(): array { return ['prepared_at' => 'datetime', 'checked_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime']; }
    public function lines() { return $this->hasMany(PayrollLine::class); }
    public function preparer() { return $this->belongsTo(User::class, 'prepared_by'); }
    public function checker() { return $this->belongsTo(User::class, 'checked_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function account() { return $this->belongsTo(MoneyAccount::class, 'account_id'); }
    public function gross(): float { return (float) $this->lines->sum(fn ($l) => $l->gross()); }
}
