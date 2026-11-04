<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['name', 'investment'];
    protected function casts(): array { return ['investment' => 'decimal:2']; }
}
