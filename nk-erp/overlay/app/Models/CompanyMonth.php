<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyMonth extends Model
{
    protected $primaryKey = 'month';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['month', 'kg_commission', 'deadline_day', 'flags', 'ait_pct', 'note', 'tons_target', 'excl_target'];

    protected function casts(): array
    {
        return ['flags' => 'array'];
    }

    public function on(string $part, int $size): bool
    {
        if ($part === 'dd') return true; // DD has no condition in the ledger rules
        return (bool) (($this->flags ?? [])[$part.'_'.$size] ?? false);
    }
}
