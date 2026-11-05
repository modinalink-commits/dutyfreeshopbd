<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseRecurring extends Model
{
    protected $table = 'expense_recurring';
    public const EVERY = ['month' => 'Month', 'year' => 'Year', 'low' => 'When low'];
    protected $fillable = ['name', 'category_id', 'amount', 'every', 'day', 'cost_center', 'next_date', 'is_active'];
    protected function casts(): array { return ['amount' => 'decimal:2', 'next_date' => 'date', 'is_active' => 'boolean']; }
    public function category() { return $this->belongsTo(ExpenseCategory::class, 'category_id'); }
}
