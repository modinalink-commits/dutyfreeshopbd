<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CylinderExchangeLine extends Model
{
    protected $fillable = ['exchange_id', 'type', 'in_brand_id', 'in_size', 'in_qty', 'in_condition', 'in_ownership', 'out_brand_id', 'out_size', 'out_qty', 'out_kind', 'out_condition', 'refill_price', 'fee', 'line_total'];

    protected function casts(): array { return ['refill_price' => 'float', 'fee' => 'float', 'line_total' => 'float']; }

    public function exchange() { return $this->belongsTo(CylinderExchange::class, 'exchange_id'); }
    public function inBrand() { return $this->belongsTo(Brand::class, 'in_brand_id'); }
    public function outBrand() { return $this->belongsTo(Brand::class, 'out_brand_id'); }

    public function inText(): string { return "{$this->in_qty} × {$this->inBrand?->name} {$this->in_size} kg empty"; }
    public function outText(): string { return "{$this->out_qty} × {$this->outBrand?->name} {$this->out_size} kg ".($this->out_kind === 'empty' ? 'empty' : 'full'); }
}
