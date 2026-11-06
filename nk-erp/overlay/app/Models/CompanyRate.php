<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRate extends Model
{
    public const PARTS = ['target' => 'Target incentive', 'slab' => 'Slab incentive', 'exclusive' => 'Exclusive incentive', 'deadline' => '01–15 additional', 'late' => '16–31 additional', 'dd' => 'DD incentive'];

    protected $fillable = ['size_kg', 'from_date', 'do_rate', 'pkg_rate', 'transport', 'target', 'slab', 'exclusive', 'deadline', 'late', 'dd'];

    protected function casts(): array
    {
        return ['from_date' => 'date'];
    }

    /** Company price per cylinder for a refill or a new package. */
    public function doFor(string $ctype): float { return $ctype === 'package' ? (float) $this->pkg_rate : (float) $this->do_rate; }

    public static function forDate(int $size, string $date): ?self
    {
        return static::query()->where('size_kg', $size)->where('from_date', '<=', $date)->orderByDesc('from_date')->first();
    }
}
