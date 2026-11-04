<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockMovement extends Model
{
    const UPDATED_AT = null;

    public const KINDS = [
        'opening' => 'Opening stock',
        'received' => 'Received from company',
        'to_company' => 'Empties sent to company',
        'empty_in' => 'Empty collected',
        'sale' => 'Sale (manual)',
        'damaged' => 'Set aside as damaged',
        'damaged_out' => 'Damaged sent out',
        'exchange' => 'Brand exchange',
        'adjustment' => 'Count adjustment',
        'reversal' => 'Reversal',
    ];

    protected $fillable = ['ref_no', 'kind', 'brand_id', 'size_kg', 'full_delta', 'empty_delta', 'company_delta', 'damaged_delta', 'reference', 'note', 'reversal_of', 'created_by'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Posted stock movements cannot be changed. Post a reversal instead.'));
        static::deleting(fn () => throw new RuntimeException('Posted stock movements cannot be deleted. Post a reversal instead.'));
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy()
    {
        return $this->hasOne(StockMovement::class, 'reversal_of');
    }

    /** Current balance per bucket for one brand and size. */
    public static function balance(int $brandId, int $size): array
    {
        $row = static::query()->where('brand_id', $brandId)->where('size_kg', $size)
            ->selectRaw('coalesce(sum(full_delta),0) f, coalesce(sum(empty_delta),0) e, coalesce(sum(company_delta),0) c, coalesce(sum(damaged_delta),0) d')
            ->first();
        return ['full' => (int) $row->f, 'empty' => (int) $row->e, 'company' => (int) $row->c, 'damaged' => (int) $row->d];
    }

    public static function nextRef(string $prefix = 'STK'): string
    {
        $base = $prefix.'-'.now()->timezone('Asia/Dhaka')->format('dm').'-';
        $n = static::query()->where('ref_no', 'like', $base.'%')->count() + 1;
        do {
            $ref = $base.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $n++;
        } while (static::query()->where('ref_no', $ref)->exists());
        return $ref;
    }

    /**
     * Post one movement inside a transaction, refusing anything that would make a bucket negative.
     */
    public static function post(array $data): self
    {
        return DB::transaction(function () use ($data) {
            // Serialise postings for the same brand+size so two users cannot both pass the balance check.
            DB::table('brands')->where('id', $data['brand_id'])->lockForUpdate()->first();
            $bal = static::balance($data['brand_id'], $data['size_kg']);
            $after = [
                'full' => $bal['full'] + ($data['full_delta'] ?? 0),
                'empty' => $bal['empty'] + ($data['empty_delta'] ?? 0),
                'company' => $bal['company'] + ($data['company_delta'] ?? 0),
                'damaged' => $bal['damaged'] + ($data['damaged_delta'] ?? 0),
            ];
            foreach ($after as $bucket => $v) {
                if ($v < 0) {
                    throw new RuntimeException("Not enough {$bucket} stock: only {$bal[$bucket]} available.");
                }
            }
            $data['ref_no'] = $data['ref_no'] ?? static::nextRef();
            $data['created_by'] = $data['created_by'] ?? auth()->id();
            return static::create($data);
        });
    }
}
