<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CylinderExchange;
use App\Models\ExchangeCharge;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cylinder exchange: the customer gives an empty of one brand and takes a full of another.
 * Price = refill price of the brand taken + the exchange charge set for the other brand and size.
 * Posting makes an ordinary sale, so stock, dues, invoice and reports all follow the usual rules.
 */
class ExchangeService
{
    /** Price for one swap, or an error message that says what is missing. */
    public static function quote(int $givenId, int $takenId, int $size, string $channel): array
    {
        if ($givenId === $takenId) throw new RuntimeException('Both sides are the same brand — that is a normal refill. Use Retail sale (POS) instead.');
        $given = Brand::findOrFail($givenId);
        $taken = Brand::findOrFail($takenId);
        if (! in_array($size, array_map('intval', $given->sizes ?? []), true)) throw new RuntimeException("{$given->name} is not set up for {$size} kg cylinders (Cylinder brands → sizes).");
        $p = Product::query()->where('brand_id', $takenId)->where('category', 'refill')->where('size_kg', $size)->where('is_active', true)->orderBy('id')->first();
        if (! $p) throw new RuntimeException("No active {$taken->name} {$size} kg refill product. Add it in Products & prices.");
        $price = $channel === 'route' ? ($p->dealer_price ?? $p->retail_price) : $p->retail_price;
        if ($price === null) throw new RuntimeException("Set the refill price of {$p->name} in Products & prices first.");
        return ['product' => $p, 'unit' => (float) $price, 'charge' => ExchangeCharge::for($givenId, $takenId, $size), 'given' => $given, 'taken' => $taken];
    }

    /** Hidden product that carries the exchange charge line on the invoice. */
    public static function chargeProduct(): Product
    {
        $p = Product::query()->where('category', 'service')->where('name', 'Cylinder exchange charge')->first();
        if ($p) return $p;
        $n = (int) Product::query()->max('id') + 1;
        do { $code = 'P-'.str_pad((string) $n++, 3, '0', STR_PAD_LEFT); } while (Product::query()->where('code', $code)->exists());
        return Product::create(['code' => $code, 'name' => 'Cylinder exchange charge', 'category' => 'service', 'note' => 'Added automatically on brand exchange invoices', 'is_active' => false]);
    }

    /** Turn an exchange into a posted sale. Call inside a transaction. */
    public static function post(CylinderExchange $x, User $by): Sale
    {
        $q = self::quote($x->given_brand_id, $x->taken_brand_id, $x->size_kg, $x->channel);
        $customer = $x->customer_id ? Customer::find($x->customer_id) : null;
        $total = round($x->qty * ($x->unit_price + $x->charge_unit), 2);
        $paid = min((float) $x->paid, $total);
        SalesService::checkCredit($customer, $total - $paid);
        Ui::lockNumbers();
        $sale = Sale::create([
            'invoice_no' => Sale::nextNo('retail'), 'type' => 'retail', 'customer_id' => $customer?->id, 'trip_id' => $x->trip_id,
            'walkin_name' => $customer ? null : ($x->walkin_name ?: 'Walk-in'), 'walkin_phone' => $customer ? null : $x->walkin_phone,
            'stage' => 'posted', 'subtotal' => $total, 'discount' => 0, 'delivery_charge' => 0, 'total' => $total, 'paid' => $paid,
            'payment_method' => $paid <= 0 ? 'due' : $x->method, 'empty_brand_id' => $x->given_brand_id,
            'empties_expected' => $x->qty, 'empties_received' => $x->qty, 'vehicle' => $x->trip?->vehicle?->code,
            'note' => "Exchange {$x->ref_no}: {$q['given']->name} {$x->size_kg} kg empty → {$q['taken']->name} full",
            'created_by' => $by->id, 'delivered_at' => now(),
        ]);
        $lines = [['product' => $q['product'], 'qty' => $x->qty, 'price' => $x->unit_price, 'total' => round($x->qty * $x->unit_price, 2)]];
        SaleItem::create(['sale_id' => $sale->id, 'product_id' => $q['product']->id, 'description' => $q['product']->name.' (exchange for '.$q['given']->name.' empty)', 'category' => 'refill',
            'brand_id' => $x->taken_brand_id, 'size_kg' => $x->size_kg, 'qty' => $x->qty, 'unit_price' => $x->unit_price, 'line_total' => $lines[0]['total']]);
        if ($x->charge_unit > 0) {
            $cp = self::chargeProduct();
            SaleItem::create(['sale_id' => $sale->id, 'product_id' => $cp->id, 'description' => "Brand exchange charge ({$q['given']->name} → {$q['taken']->name}, {$x->size_kg} kg)", 'category' => 'service',
                'brand_id' => null, 'size_kg' => null, 'qty' => $x->qty, 'unit_price' => $x->charge_unit, 'line_total' => round($x->qty * $x->charge_unit, 2)]);
        }
        SalesService::postOut($sale, $lines);
        SalesService::postEmpties($sale, [$x->size_kg => $x->qty], $x->qty, $x->given_brand_id);
        SalesService::postMoney($sale);
        $x->forceFill(['status' => 'approved', 'sale_id' => $sale->id, 'total' => $total, 'paid' => $paid, 'decided_by' => $by->id, 'decided_at' => now()])->save();
        AuditLog::record('cylinder_exchange', "{$x->ref_no} approved → {$sale->invoice_no} · {$x->qty} × {$x->size_kg} kg {$q['given']->name} → {$q['taken']->name} · ৳{$total}");
        return $sale;
    }
}
