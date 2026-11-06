<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Support\SalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class PosController extends Controller
{
    public function index()
    {
        $products = Product::query()->with('brand')->where('is_active', true)->orderByRaw("case category when 'refill' then 1 when 'new_package' then 2 when 'accessory' then 3 else 4 end")->orderBy('size_kg')->get();
        // Retail sale sells our own brand only (Aygaz United); other brands' cylinders go through Cylinder exchange.
        $ownB = \App\Support\ExchangeService::ownId();
        $products = $products->reject(fn ($p) => $p->brand_id && $p->brand_id !== $ownB)
            ->sortBy(fn ($p) => [['refill' => 1, 'new_package' => 2, 'accessory' => 3][$p->category] ?? 4, (float) $p->size_kg])->values();
        $items = $products->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'cat' => $p->category, 'img' => $p->image, 'size' => $p->size_kg ? $p->size_kg.' kg' : ($p->category === 'service' ? 'Service' : 'Part'),
            'note' => $p->note, 'price' => $p->retail_price !== null ? (float) $p->retail_price : null, 'stock' => $p->available(),
        ])->values();

        return view('pos', [
            'items' => $items,
            'brands' => Brand::query()->where('empty_collection', true)->orderBy('position')->orderBy('code')->get(),
            'ownBrand' => Brand::query()->where('for_sale', true)->orderBy('id')->value('id'),
            'todayCount' => Sale::query()->where('type', 'retail')->where('stage', '!=', 'voided')->where('created_at', '>=', now()->timezone('Asia/Dhaka')->startOfDay()->utc())->count(),
            'nextNo' => Sale::nextNo('retail'),
        ]);
    }

    public function lookup(Request $request)
    {
        $p = Customer::normalisePhone((string) $request->query('phone'));
        $c = strlen($p) >= 11 ? Customer::query()->where('phone', $p)->first() : null;
        return response()->json($c ? ['found' => true, 'id' => $c->id, 'name' => $c->name, 'type' => $c->type, 'due' => $c->due(), 'credit' => (float) $c->credit_limit] : ['found' => false]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['integer', 'min:0', 'max:10000'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'walkin_name' => ['nullable', 'string', 'max:120'],
            'walkin_phone' => ['nullable', 'string', 'max:20'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'empties' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'empty_brand_id' => ['required', 'exists:brands,id'],
            'method' => ['required', Rule::in(array_keys(Sale::METHODS))],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if ((float) ($data['discount'] ?? 0) > 0 && ! in_array($request->user()->role, ['super_admin', 'admin', 'manager'], true)) {
            return back()->withInput()->withErrors(['discount' => 'Only a manager can give a discount.']);
        }

        try {
            $sale = DB::transaction(function () use ($data, $request) {
                $lines = SalesService::lines($data['qty'], 'retail_price');
                $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
                $sub = array_sum(array_column($lines, 'total'));
                $total = round($sub + (float) ($data['delivery_charge'] ?? 0) - (float) ($data['discount'] ?? 0), 2);
                if ($total < 0) throw new RuntimeException('Discount is bigger than the bill.');
                $paid = $data['method'] === 'due' ? (float) ($data['paid'] ?? 0) : $total;
                if ($paid > $total + 0.004) throw new RuntimeException('Paid amount is more than the bill.');
                SalesService::checkCredit($customer, $total - $paid);
                $refills = SalesService::refillBySize($lines);

                \App\Support\Ui::lockNumbers();
                $sale = Sale::create([
                    'invoice_no' => Sale::nextNo('retail'), 'type' => 'retail', 'customer_id' => $customer?->id,
                    'walkin_name' => $customer ? null : ($data['walkin_name'] ?? null), 'walkin_phone' => $customer ? null : ($data['walkin_phone'] ?? null),
                    'stage' => 'posted', 'subtotal' => $sub, 'discount' => $data['discount'] ?? 0, 'delivery_charge' => $data['delivery_charge'] ?? 0,
                    'total' => $total, 'paid' => $paid, 'payment_method' => $data['method'], 'empty_brand_id' => $data['empty_brand_id'],
                    'empties_expected' => array_sum($refills), 'empties_received' => (int) ($data['empties'] ?? array_sum($refills)),
                    'note' => $data['note'] ?? null, 'created_by' => $request->user()->id, 'delivered_at' => now(),
                ]);
                foreach ($lines as $l) {
                    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $l['product']->id, 'description' => $l['product']->name, 'category' => $l['product']->category,
                        'brand_id' => $l['product']->brand_id, 'size_kg' => $l['product']->size_kg, 'qty' => $l['qty'], 'unit_price' => $l['price'], 'line_total' => $l['total']]);
                }
                SalesService::postOut($sale, $lines);
                SalesService::postEmpties($sale, $refills, $sale->empties_received, (int) $data['empty_brand_id']);
                SalesService::postMoney($sale);
                return $sale;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => \App\Support\Ui::msg($e)]);
        }
        AuditLog::record('sale_retail', "{$sale->invoice_no} ৳{$sale->total}");

        return redirect()->route('sales.show', $sale)->with('ok', "{$sale->invoice_no} saved.");
    }
}
