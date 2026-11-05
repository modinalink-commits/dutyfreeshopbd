<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    public const TYPES = ['Fixed', 'Utility', 'Variable', 'Claim'];
    public const FORMS = ['general' => 'Basic', 'fuel' => 'Vehicle fuel', 'rent' => 'Rent', 'reimb' => 'Employee reimbursement', 'bill' => 'Bill / utility'];
    public const SOURCES = ['payroll' => 'Payroll', 'fleet' => 'Vehicle expense (Fleet)', 'purchase' => 'Purchase'];
    protected $fillable = ['code', 'name', 'parent_id', 'gl_account', 'type', 'form', 'approval_rule', 'monthly_budget', 'source', 'is_active'];
    protected function casts(): array { return ['monthly_budget' => 'decimal:2', 'is_active' => 'boolean']; }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function isGroup(): bool { return $this->parent_id === null; }
}
