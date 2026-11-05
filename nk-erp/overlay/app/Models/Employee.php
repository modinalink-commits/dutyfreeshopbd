<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    public const ROLES = ['driver' => 'Driver', 'salesman' => 'Salesman', 'warehouse' => 'Warehouse staff', 'manager' => 'Manager', 'accountant' => 'Accountant', 'other' => 'Other'];
    protected $fillable = ['code', 'name', 'phone', 'role', 'is_active', 'user_id'];
    protected function casts(): array { return ['is_active' => 'boolean']; }

    public static function nextCode(): string
    {
        return 'E-'.str_pad((string) ((int) static::query()->max('id') + 1), 3, '0', STR_PAD_LEFT);
    }
}
