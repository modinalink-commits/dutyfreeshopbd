<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = ['code', 'user_id', 'report', 'format', 'filters', 'rows', 'path', 'size', 'status', 'expires_at', 'downloads', 'archived'];
    protected function casts(): array { return ['filters' => 'array', 'expires_at' => 'datetime', 'archived' => 'boolean']; }
    public function user() { return $this->belongsTo(User::class); }
    public static function nextCode(): string
    {
        $base = 'EXP-'.now()->timezone('Asia/Dhaka')->format('dm').'-';
        $n = static::query()->where('code', 'like', $base.'%')->count() + 1;
        do { $c = $base.str_pad((string) $n++, 3, '0', STR_PAD_LEFT); } while (static::query()->where('code', $c)->exists());
        return $c;
    }
}
