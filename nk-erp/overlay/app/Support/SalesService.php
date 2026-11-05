<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\ItemMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesService
{
    /**
     * Build validated lines from [product_id => qty]. Throws when a price is missing.
     */
    public static function lines(array $qtys, string $priceField): array
    {
        $lines = [];
        $products = Product::query()->whereIn('id', array_keys($qtys))->where('is_active', true)->get()->keyBy('id');
        foreach ($qtys as $id => $qty) {
            $qty = (int) $qty;
            if ($qty <= 0) continue;
            $p = $products[$id] ?? null;
            if (! $p) throw new RuntimeException('A product in the memo is not available.');
            $price = $p->{$priceField} ?? ($priceField === 'dealer_price' ? $p->retail_price : null);
            if ($price === null) throw new RuntimeException("Set a price for {$p->name} in Products & prices first.");
            $lines[] = ['product' => $p, 'qty' => $qty, 'price' => (float) $price, 'total' => round($qty * (float) $price, 2)];
        }
        if (! $lines) throw new RuntimeException('Add at least one item.');
        return $lines;
    }

    public static function refillBySize(array $lines): array
    {
        $out = [];
        foreach ($lines as $l) {
            if ($l['product']->category === 'refill') {
                $s = $l['product']->size_kg;
                $out[$s] = ($out[$s] ?? 0) + $l['qty'];
            }
        }
        return $out;
    }

    public static function checkCredit(?Customer $c, float $newDue): void
    {
        if ($newDue <= 0.004) return;
        if (! $c) throw new RuntimeException('A walk-in customer must pay in full. Pick a saved customer to sell on due.');
        $limit = (float) $c->credit_limit;
        if ($limit <= 0) throw new RuntimeException("Credit is off for {$c->name}. Turn it on with a credit limit in the customer page, or take full payment.");
        $after = $c->due() + $newDue;
        if ($after > $limit + 0.004) throw new RuntimeException("This would take {$c->name}'s due to ৳".number_format($after).', above the ৳'.number_format($limit).' credit limit.');
    }

    /** Take full cylinders and accessories out of stock. */
    public static function postOut(Sale $sale, array $lines): void
    {
        foreach ($lines as $l) {
            $p = $l['product'];
            if ($p->isCylinder()) {
                StockMovement::post(['kind' => 'sale', 'brand_id' => $p->brand_id, 'size_kg' => $p->size_kg,
                    'full_delta' => -$l['qty'], 'reference' => $sale->invoice_no, 'note' => $p->name, 'trip_id' => $sale->trip_id]);
            } elseif ($p->category === 'accessory') {
                $have = (int) ItemMovement::query()->where('product_id', $p->id)->sum('qty_delta');
                if ($have < $l['qty']) throw new RuntimeException("Not enough {$p->name}: only {$have} in stock.");
                ItemMovement::create(['product_id' => $p->id, 'qty_delta' => -$l['qty'], 'kind' => 'sale', 'reference' => $sale->invoice_no, 'created_by' => auth()->id()]);
            }
        }
    }

    /** Empties received now, plus cylinders the customer still holds. */
    public static function postEmpties(Sale $sale, array $refillBySize, int $received, int $emptyBrandId): void
    {
        $expected = array_sum($refillBySize);
        if ($received > 0) {
            // Spread received empties across sizes in order of the memo.
            $left = $received;
            foreach ($refillBySize as $size => $q) {
                $take = min($q, $left);
                if ($take > 0) {
                    $brand = Brand::findOrFail($emptyBrandId);
                    if (! in_array((int) $size, $brand->sizes ?? [], true)) throw new RuntimeException("{$brand->name} is not set up for {$size} kg empties.");
                    StockMovement::post(['kind' => 'empty_in', 'brand_id' => $emptyBrandId, 'size_kg' => $size, 'empty_delta' => $take,
                        'reference' => $sale->invoice_no, 'note' => 'Empty with sale', 'trip_id' => $sale->trip_id]);
                    $left -= $take;
                }
            }
            if ($left > 0) {
                $s = array_key_first($refillBySize) ?? 12;
                StockMovement::post(['kind' => 'empty_in', 'brand_id' => $emptyBrandId, 'size_kg' => $s, 'empty_delta' => $left, 'reference' => $sale->invoice_no, 'note' => 'Extra empty with sale', 'trip_id' => $sale->trip_id]);
                if ($sale->customer_id) {
                    $own = Brand::query()->where('for_sale', true)->value('id');
                    CustomerCylinder::create(['customer_id' => $sale->customer_id, 'brand_id' => $own, 'size_kg' => $s, 'qty_delta' => -$left, 'reference' => $sale->invoice_no, 'sale_id' => $sale->id]);
                }
            }
        }
        $short = $expected - $received;
        if ($short > 0) {
            if (! $sale->customer_id) throw new RuntimeException('A walk-in customer must hand over an empty for every refill. Pick a saved customer to let them owe empties.');
            $own = Brand::query()->where('for_sale', true)->value('id');
            $left = $received;
            foreach ($refillBySize as $size => $q) {
                $got = min($q, $left); $left -= $got;
                if ($q - $got > 0) {
                    CustomerCylinder::create(['customer_id' => $sale->customer_id, 'brand_id' => $own, 'size_kg' => $size, 'qty_delta' => $q - $got, 'reference' => $sale->invoice_no, 'sale_id' => $sale->id]);
                }
            }
        }
    }

    public static function postMoney(Sale $sale): void
    {
        if (! $sale->customer_id) return;
        CustomerLedger::create(['customer_id' => $sale->customer_id, 'kind' => 'sale', 'reference' => $sale->invoice_no, 'sale_id' => $sale->id,
            'debit' => $sale->total, 'credit' => 0, 'created_by' => auth()->id()]);
        if ((float) $sale->paid > 0) {
            CustomerLedger::create(['customer_id' => $sale->customer_id, 'kind' => 'payment', 'reference' => $sale->invoice_no, 'sale_id' => $sale->id, 'trip_id' => $sale->trip_id,
                'debit' => 0, 'credit' => $sale->paid, 'method' => $sale->payment_method, 'note' => 'Paid with '.$sale->invoice_no, 'created_by' => auth()->id()]);
        }
    }

    /** Undo everything a sale posted, with reversing entries. */
    public static function void(Sale $sale, string $reason): void
    {
        DB::transaction(function () use ($sale, $reason) {
            $moves = StockMovement::query()->where('reference', $sale->invoice_no)->whereIn('kind', ['sale', 'empty_in'])->whereDoesntHave('reversedBy')->get();
            foreach ($moves as $m) {
                StockMovement::post(['kind' => 'reversal', 'brand_id' => $m->brand_id, 'size_kg' => $m->size_kg, 'trip_id' => $m->trip_id,
                    'full_delta' => -$m->full_delta, 'empty_delta' => -$m->empty_delta, 'reference' => $sale->invoice_no,
                    'note' => 'Void: '.$reason, 'reversal_of' => $m->id]);
            }
            foreach (ItemMovement::query()->where('reference', $sale->invoice_no)->where('kind', 'sale')->get() as $im) {
                ItemMovement::create(['product_id' => $im->product_id, 'qty_delta' => -$im->qty_delta, 'kind' => 'void', 'reference' => $sale->invoice_no, 'created_by' => auth()->id()]);
            }
            foreach (CustomerCylinder::query()->where('sale_id', $sale->id)->get() as $cc) {
                CustomerCylinder::create(['customer_id' => $cc->customer_id, 'brand_id' => $cc->brand_id, 'size_kg' => $cc->size_kg, 'qty_delta' => -$cc->qty_delta, 'reference' => 'VOID '.$sale->invoice_no, 'sale_id' => $sale->id]);
            }
            $sums = CustomerLedger::query()->where('sale_id', $sale->id)->selectRaw('coalesce(sum(debit),0) d, coalesce(sum(credit),0) c')->first();
            if ($sale->customer_id && ((float) $sums->d > 0 || (float) $sums->c > 0)) {
                CustomerLedger::create(['customer_id' => $sale->customer_id, 'kind' => 'void', 'reference' => $sale->invoice_no, 'sale_id' => $sale->id,
                    'debit' => $sums->c, 'credit' => $sums->d, 'note' => 'Void: '.$reason, 'created_by' => auth()->id()]);
            }
            $sale->update(['stage' => 'voided', 'voided_by' => auth()->id(), 'voided_at' => now(), 'void_reason' => $reason]);
        });
    }
}
