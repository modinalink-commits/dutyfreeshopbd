<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRate extends Model
{
    public const PARTS = ['target' => 'Target', 'slab' => 'Slab', 'exclusive' => 'Exclusive', 'deadline' => 'Deadline (lifted by deadline day)', 'late' => 'Late (lifted after deadline day)', 'dd' => 'DD'];

    protected $fillable = ['size_kg', 'from_date', 'do_rate', 'transport', 'target', 'slab', 'exclusive', 'deadline', 'late', 'dd'];

    protected function casts(): array
    {
        return ['from_date' => 'date'];
    }

    public static function forDate(int $size, string $date): ?self
    {
        return static::query()->where('size_kg', $size)->where('from_date', '<=', $date)->orderByDesc('from_date')->first();
    }
}
