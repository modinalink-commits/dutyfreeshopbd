<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = ['name', 'markets', 'shops', 'is_active', 'zone'];
    protected function casts(): array { return ['is_active' => 'boolean', 'zone' => 'array']; }
}
