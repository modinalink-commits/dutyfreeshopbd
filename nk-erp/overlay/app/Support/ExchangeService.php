<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandSettlement;
use App\Models\Customer;
use App\Models\CylinderDeposit;
use App\Models\CylinderExchange;
use App\Models\ExchangeCharge;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use RuntimeException;

/**
 * Cylinder exchange: the customer hands over cylinders of one brand and takes cylinders of another (or the same) brand.
 * Price per full = refill price of the brand handed out + the exchange charge of every other brand involved.
 * Posting makes an ordinary sale (stock, dues, invoice, reports all follow the usual rules),
 * records other-brand cylinders in the brand settlement ledger and keeps deposits apart from sales.
 */
class ExchangeService
{
    public static function ownId(): int { return (int) Brand::query()->where('for_sale', true)->orderBy('id')->value('id'); }

    public static function typeOf(int $in, int $out, string $outKind, int $own): string
    {
        if ($outKind === 'empty') return 'E';
        return $in === $own ? ($out === $own ? 'A' : 'C') : ($out === $own ? 'B' : 'D');
    }

    /** Charge per cylinder: each brand involved that is not ours adds its own charge. Same brand both sides = no charge. */
    public static function fee(int $in, int $out, int $size, int $own): float
    {
        if ($in === $out) return 0.0;
        $sum = 0.0;
        foreach (array_unique([$in, $out]) as $b) if ($b !== $own) $sum += (float) (ExchangeCharge::query()->where('brand_id', $b)->where('size_kg', $size)->value('charge') ?? 0);
        return $sum;
    }

    public static function refillProduct(int $brandId, int $size): ?Product
    {
        return Product::query()->where('brand_id', $brandId)->where('category', 'refill')->where('size_kg', $size)->where('is_active', true)->orderBy('id')->first();
    }

    /** Single-line price used by the live price box. */
    public static function quote(int $givenId, int $takenId, int $size, string $channel, string $outKind = 'full'): array
    {
        $r = self::prepare([['in_brand_id' => $givenId, 'in_size' => $size, 'in_qty' => 1, 'out_brand_id' => $takenId, 'out_size' => $size, 'out_qty' => 1, 'out_kind' => $outKind]], $channel);
        $l = $r['lines'][0];
        return ['product' => $l['product'], 'unit' => $l['refill_price'], 'charge' => $l['fee'], 'type' => $l['type'], 'hold' => $r['hold'],
            'given' => Brand::find($givenId), 'taken' => Brand::find($takenId)];
    }

    /**
     * Check and price the lines. Throws for anything that can never be posted; returns reasons to hold for review
     * (unsafe or unknown cylinders, brands without an exchange agreement).
     */
    public static function prepare(array $rows, string $channel): array
    {
        $own = self::ownId();
        if (! $own) throw new RuntimeException('Set our own brand (For sale = Yes) in Cylinder brands first.');
        $enabled = CylinderExchange::enabledTypes();
        $out = []; $hold = [];
        foreach (array_values($rows) as $i => $r) {
            $n = $i + 1;
            $in = Brand::find((int) ($r['in_brand_id'] ?? 0));
            $ob = Brand::find((int) ($r['out_brand_id'] ?? 0));
            if (! $in || ! $ob) throw new RuntimeException("Line {$n}: pick both brands.");
            $inSize = (int) ($r['in_size'] ?? 0); $outSize = (int) ($r['out_size'] ?? $inSize);
            $inQty = (int) ($r['in_qty'] ?? 0); $outQty = (int) ($r['out_qty'] ?? $inQty);
            $kind = ($r['out_kind'] ?? 'full') === 'empty' ? 'empty' : 'full';
            if ($inQty < 1 || $outQty < 1) throw new RuntimeException("Line {$n}: quantity must be at least 1 on both sides.");
            foreach ([[$in, $inSize], [$ob, $outSize]] as [$b, $s]) {
                if (! $b->is_active) throw new RuntimeException("Line {$n}: {$b->name} is inactive.");
                if (! in_array($s, array_map('intval', $b->sizes ?? []), true)) throw new RuntimeException("Line {$n}: {$b->name} is not set up for {$s} kg cylinders (Cylinder brands → sizes).");
            }
            $type = self::typeOf($in->id, $ob->id, $kind, $own);
            if (! in_array($type, $enabled, true)) throw new RuntimeException("Line {$n}: exchange type {$type} (".CylinderExchange::TYPES[$type].') is switched off in Exchange rate settings.');
            if (($r['out_condition'] ?? 'good') !== 'good') throw new RuntimeException("Line {$n}: only cylinders in good condition can be handed out.");
            $product = null; $price = 0.0;
            if ($kind === 'full') {
                $product = self::refillProduct($ob->id, $outSize);
                if (! $product) throw new RuntimeException("Line {$n}: no active {$ob->name} {$outSize} kg refill product. Add it in Products & prices.");
                $p = $channel === 'route' ? ($product->dealer_price ?? $product->retail_price) : $product->retail_price;
                if ($p === null) throw new RuntimeException("Set the refill price of {$product->name} in Products & prices first.");
                $price = (float) $p;
            }
            $fee = self::fee($in->id, $ob->id, $outSize, $own);
            $cond = array_key_exists($r['in_condition'] ?? 'good', CylinderExchange::CONDITIONS) ? ($r['in_condition'] ?? 'good') : 'unknown';
            $owner = array_key_exists($r['in_ownership'] ?? 'customer', CylinderExchange::OWNERSHIP) ? ($r['in_ownership'] ?? 'customer') : 'unknown';
            if ($cond !== 'good') $hold[] = "Line {$n}: received cylinder is ".strtolower(CylinderExchange::CONDITIONS[$cond]);
            if ($owner === 'unknown') $hold[] = "Line {$n}: ownership of the received cylinder is unknown";
            foreach (array_unique([$in->id, $ob->id]) as $bid) {
                $b = $bid === $in->id ? $in : $ob;
                if ($bid !== $own && ! $b->exchangeAuthorized()) $hold[] = "Line {$n}: no approved exchange agreement with {$b->name}";
            }
            $out[] = ['type' => $type, 'in_brand_id' => $in->id, 'in_size' => $inSize, 'in_qty' => $inQty, 'in_condition' => $cond, 'in_ownership' => $owner,
                'out_brand_id' => $ob->id, 'out_size' => $outSize, 'out_qty' => $outQty, 'out_kind' => $kind, 'out_condition' => 'good',
                'refill_price' => $price, 'fee' => $fee, 'line_total' => round($outQty * ($price + $fee), 2), 'product' => $product];
        }
        if (! $out) throw new RuntimeException('Add at least one line.');
        $types = array_unique(array_column($out, 'type'));
        return ['lines' => $out, 'hold' => array_values(array_unique($hold)), 'total' => round(array_sum(array_column($out, 'line_total')), 2), 'type' => count($types) === 1 ? $types[0] : 'MX'];
    }

    /** Reasons that block posting even with a manager's approval (agreement missing). Re-checked at approval time. */
    public static function blockers(CylinderExchange $x): array
    {
        $own = self::ownId(); $out = [];
        foreach ($x->lines as $l) {
            foreach (array_unique([$l->in_brand_id, $l->out_brand_id]) as $bid) {
                $b = Brand::find($bid);
                if ($bid !== $own && $b && ! $b->exchangeAuthorized()) $out[] = "No approved exchange agreement with {$b->name}. Record it in Exchange rate settings → brand rules first.";
            }
        }
        return array_values(array_unique($out));
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

    /** Post an exchange: invoice, stock, customer ledger, brand settlement, deposit. Call inside a transaction. */
    public static function post(CylinderExchange $x, User $by): ?Sale
    {
        if ($b = self::blockers($x)) throw new RuntimeException($b[0]);
        $own = self::ownId();
        $x->loadMissing(['lines.inBrand', 'lines.outBrand', 'trip.vehicle']);
        $customer = $x->customer_id ? Customer::find($x->customer_id) : null;
        $total = round($x->lines->sum('line_total'), 2);
        $paid = min((float) $x->paid, $total);
        SalesService::checkCredit($customer, $total - $paid);
        Ui::lockNumbers();
        $sale = null;
        $fulls = $x->lines->where('out_kind', 'full');
        if ($total > 0 || $fulls->isNotEmpty()) {
            $first = $x->lines->first();
            $sale = Sale::create([
                'invoice_no' => Sale::nextNo('retail'), 'type' => 'retail', 'customer_id' => $customer?->id, 'trip_id' => $x->trip_id,
                'walkin_name' => $customer ? null : ($x->walkin_name ?: 'Walk-in'), 'walkin_phone' => $customer ? null : $x->walkin_phone,
                'stage' => 'posted', 'subtotal' => $total, 'discount' => 0, 'delivery_charge' => 0, 'total' => $total, 'paid' => $paid,
                'payment_method' => $paid <= 0 ? 'due' : $x->method, 'empty_brand_id' => $first->in_brand_id,
                'empties_expected' => (int) $x->lines->sum('in_qty'), 'empties_received' => (int) $x->lines->sum('in_qty'), 'vehicle' => $x->trip?->vehicle?->code,
                'note' => "Cylinder exchange {$x->ref_no}", 'created_by' => $by->id, 'delivered_at' => now(),
            ]);
            $cp = null;
            foreach ($x->lines as $l) {
                if ($l->out_kind === 'full') {
                    $p = self::refillProduct($l->out_brand_id, $l->out_size);
                    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $p->id, 'description' => $p->name." (exchange for {$l->inBrand->name} {$l->in_size} kg empty)", 'category' => 'refill',
                        'brand_id' => $l->out_brand_id, 'size_kg' => $l->out_size, 'qty' => $l->out_qty, 'unit_price' => $l->refill_price, 'line_total' => round($l->out_qty * $l->refill_price, 2)]);
                }
                if ($l->fee > 0) {
                    $cp ??= self::chargeProduct();
                    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $cp->id, 'description' => "Exchange charge ({$l->inBrand->name} {$l->in_size} kg → {$l->outBrand->name} {$l->out_size} kg)", 'category' => 'service',
                        'brand_id' => null, 'size_kg' => null, 'qty' => $l->out_qty, 'unit_price' => $l->fee, 'line_total' => round($l->out_qty * $l->fee, 2)]);
                }
            }
        }
        $ref = $sale?->invoice_no ?? $x->ref_no;
        foreach ($x->lines as $l) {
            $own_note = CylinderExchange::OWNERSHIP[$l->in_ownership] ?? $l->in_ownership;
            if ($l->in_condition === 'good') {
                StockMovement::post(['kind' => 'empty_in', 'brand_id' => $l->in_brand_id, 'size_kg' => $l->in_size, 'empty_delta' => $l->in_qty, 'reference' => $ref, 'trip_id' => $x->trip_id,
                    'note' => "Exchange {$x->ref_no} in · {$own_note}"]);
            } else {
                StockMovement::post(['kind' => 'damaged', 'brand_id' => $l->in_brand_id, 'size_kg' => $l->in_size, 'damaged_delta' => $l->in_qty, 'reference' => $ref, 'trip_id' => $x->trip_id,
                    'note' => "Exchange {$x->ref_no} in · ".CylinderExchange::CONDITIONS[$l->in_condition]." · {$own_note} · quarantined"]);
            }
            if ($l->out_kind === 'full') {
                StockMovement::post(['kind' => 'sale', 'brand_id' => $l->out_brand_id, 'size_kg' => $l->out_size, 'full_delta' => -$l->out_qty, 'reference' => $ref, 'trip_id' => $x->trip_id,
                    'note' => "Exchange {$x->ref_no} out · {$l->outBrand->name} {$l->out_size} kg full"]);
            } else {
                StockMovement::post(['kind' => 'exchange', 'brand_id' => $l->out_brand_id, 'size_kg' => $l->out_size, 'empty_delta' => -$l->out_qty, 'reference' => $ref, 'trip_id' => $x->trip_id,
                    'note' => "Exchange {$x->ref_no} out · {$l->outBrand->name} {$l->out_size} kg empty swap"]);
            }
            if ($l->in_brand_id !== $own) {
                BrandSettlement::create(['brand_id' => $l->in_brand_id, 'size_kg' => $l->in_size, 'kind' => 'exchange', 'qty_received' => $l->in_qty, 'reference' => $ref, 'exchange_id' => $x->id,
                    'agreement_ref' => $l->inBrand->agreement_ref, 'note' => "Received in {$x->ref_no} · {$own_note}", 'created_by' => $by->id]);
            }
            if ($l->out_brand_id !== $own) {
                BrandSettlement::create(['brand_id' => $l->out_brand_id, 'size_kg' => $l->out_size, 'kind' => 'exchange', 'qty_delivered' => $l->out_qty, 'reference' => $ref, 'exchange_id' => $x->id,
                    'agreement_ref' => $l->outBrand->agreement_ref, 'note' => "Handed out in {$x->ref_no}", 'created_by' => $by->id]);
            }
        }
        if ($sale) SalesService::postMoney($sale);
        if (abs((float) $x->deposit_adj) > 0.004) {
            CylinderDeposit::create(['customer_id' => $customer?->id, 'walkin_name' => $customer ? null : ($x->walkin_name ?: 'Walk-in'), 'exchange_id' => $x->id, 'amount' => $x->deposit_adj,
                'method' => $x->method === 'due' ? 'cash' : $x->method, 'reference' => $x->ref_no, 'note' => $x->deposit_adj > 0 ? 'Deposit taken with exchange' : 'Deposit returned with exchange', 'created_by' => $by->id]);
        }
        $x->forceFill(['status' => 'approved', 'sale_id' => $sale?->id, 'total' => $total, 'paid' => $paid, 'decided_by' => $by->id, 'decided_at' => now()])->save();
        AuditLog::record('cylinder_exchange', "{$x->ref_no} posted".($sale ? " → {$sale->invoice_no}" : '')." · {$x->lines->count()} line(s) · ৳{$total}");
        return $sale;
    }

    /** Undo a posted exchange with reversing entries only. Call inside a transaction. */
    public static function reverse(CylinderExchange $x, User $by, string $reason): void
    {
        if ($x->status !== 'approved') throw new RuntimeException("{$x->ref_no} is not posted, nothing to reverse.");
        $refs = [$x->ref_no];
        if ($x->sale) {
            $refs[] = $x->sale->invoice_no;
            if ($x->sale->stage !== 'voided') SalesService::void($x->sale, "Exchange {$x->ref_no} reversed: {$reason}");
        }
        $left = StockMovement::query()->whereIn('reference', $refs)->where('kind', '!=', 'reversal')->where('note', 'like', "Exchange {$x->ref_no}%")->whereDoesntHave('reversedBy')->get();
        foreach ($left as $m) {
            StockMovement::post(['kind' => 'reversal', 'brand_id' => $m->brand_id, 'size_kg' => $m->size_kg, 'trip_id' => $m->trip_id,
                'full_delta' => -$m->full_delta, 'empty_delta' => -$m->empty_delta, 'damaged_delta' => -$m->damaged_delta, 'company_delta' => -$m->company_delta,
                'reference' => $m->reference, 'note' => "Reverse exchange {$x->ref_no}: {$reason}", 'reversal_of' => $m->id]);
        }
        foreach (BrandSettlement::query()->where('exchange_id', $x->id)->where('kind', 'exchange')->get() as $s) {
            BrandSettlement::create(['brand_id' => $s->brand_id, 'size_kg' => $s->size_kg, 'kind' => 'reversal', 'qty_received' => -$s->qty_received, 'qty_delivered' => -$s->qty_delivered,
                'reference' => $s->reference, 'exchange_id' => $x->id, 'note' => "Reverse {$x->ref_no}: {$reason}", 'created_by' => $by->id]);
        }
        $dep = (float) CylinderDeposit::query()->where('exchange_id', $x->id)->sum('amount');
        if (abs($dep) > 0.004) {
            CylinderDeposit::create(['customer_id' => $x->customer_id, 'walkin_name' => $x->customer_id ? null : $x->walkin_name, 'exchange_id' => $x->id, 'amount' => -$dep, 'method' => 'cash',
                'reference' => $x->ref_no, 'note' => "Reverse {$x->ref_no}: {$reason}", 'created_by' => $by->id]);
        }
        $x->forceFill(['status' => 'reversed', 'reversed_by' => $by->id, 'reversed_at' => now(), 'reverse_reason' => $reason])->save();
        AuditLog::record('cylinder_exchange', "{$x->ref_no} reversed: {$reason}");
    }
}
