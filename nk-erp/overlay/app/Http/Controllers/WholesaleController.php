<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\SalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class WholesaleController extends Controller
{
    public function index(Request $request)
    {
        $stage = $request->query('stage');
        $orders = Sale::query()->with(['customer', 'items'])->where('type', 'wholesale')
            ->when($stage, fn ($q) => $q->where('stage', $stage))
            ->where('created_at', '>=', now()->subDays(60))->latest('id')->get();
        $all = Sale::query()->where('type', 'wholesale')->where('stage', '!=', 'voided')->get();
        $delivered = $all->where('stage', 'delivered');
        return view('wholesale', [
            'orders' => $orders, 'stage' => $stage,
            'stats' => [
                'waiting' => [$all->where('stage', 'waiting')->count(), $all->where('stage', 'waiting')->sum(fn ($o) => $o->items()->sum('qty')).' items'],
                'on_road' => [$all->where('stage', 'on_road')->count(), 'out for delivery'],
                'unpaid' => [$delivered->filter(fn ($o) => (float) $o->paid <= 0 && $o->due() > 0)->count(), '৳'.number_format($delivered->filter(fn ($o) => (float) $o->paid <= 0)->sum(fn ($o) => $o->due())).' to collect'],
                'part' => [$delivered->filter(fn ($o) => (float) $o->paid > 0 && $o->due() > 0)->count(), '৳'.number_format($delivered->filter(fn ($o) => (float) $o->paid > 0)->sum(fn ($o) => $o->due())).' left'],
                'closed' => [$delivered->filter(fn ($o) => $o->due() <= 0 && $o->created_at >= now()->startOfMonth())->count(), 'this month'],
            ],
            'dealers' => $dealers = Customer::query()->where('is_active', true)->whereIn('type', ['dealer', 'commercial'])->orderBy('name')->get(),
            'dealerInfo' => $dealers->mapWithKeys(fn ($d) => [$d->id => ['due' => $d->due(), 'limit' => (float) $d->credit_limit, 'out' => array_sum($d->cylindersOut())]]),
            'products' => Product::query()->where('is_active', true)->whereIn('category', ['refill', 'new_package', 'accessory'])->orderBy('category')->orderBy('size_kg')->get(),
            'brands' => Brand::query()->where('empty_collection', true)->orderBy('code')->get(),
            'ownBrand' => Brand::query()->where('for_sale', true)->value('id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'qty' => ['required', 'array'], 'qty.*' => ['integer', 'min:0', 'max:100000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'vehicle' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
            'empties_collect' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'print' => ['nullable', 'boolean'],
        ]);
        try {
            $sale = DB::transaction(function () use ($data, $request) {
                $lines = SalesService::lines($data['qty'], 'dealer_price');
                $sub = array_sum(array_column($lines, 'total'));
                $total = round($sub - (float) ($data['discount'] ?? 0), 2);
                if ($total < 0) throw new RuntimeException('Discount is bigger than the bill.');
                \App\Support\Ui::lockNumbers();
                $sale = Sale::create(['invoice_no' => Sale::nextNo('wholesale'), 'type' => 'wholesale', 'customer_id' => $data['customer_id'], 'stage' => 'waiting',
                    'subtotal' => $sub, 'discount' => $data['discount'] ?? 0, 'total' => $total, 'paid' => 0,
                    'empties_expected' => isset($data['empties_collect']) ? (int) $data['empties_collect'] : array_sum(SalesService::refillBySize($lines)), 'vehicle' => $data['vehicle'] ?? null, 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
                foreach ($lines as $l) {
                    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $l['product']->id, 'description' => $l['product']->name, 'category' => $l['product']->category,
                        'brand_id' => $l['product']->brand_id, 'size_kg' => $l['product']->size_kg, 'qty' => $l['qty'], 'unit_price' => $l['price'], 'line_total' => $l['total']]);
                }
                return $sale;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => \App\Support\Ui::msg($e)]);
        }
        AuditLog::record('wholesale_order', "{$sale->invoice_no} ৳{$sale->total}");
        if (! empty($data['print'])) return redirect()->route('sales.print', [$sale, 'size' => 'a4', 'auto' => 1]);
        return redirect()->route('wholesale.index')->with('ok', "{$sale->invoice_no} created. Mark it loaded when it leaves the godown.");
    }

    private function linesOf(Sale $sale): array
    {
        return $sale->items()->with('product')->get()->map(fn ($i) => ['product' => $i->product, 'qty' => $i->qty, 'price' => (float) $i->unit_price, 'total' => (float) $i->line_total])->all();
    }

    public function load(Request $request, Sale $sale)
    {
        abort_unless($sale->type === 'wholesale' && $sale->stage === 'waiting', 422);
        try {
            DB::transaction(function () use ($sale, $request) {
                SalesService::postOut($sale, $this->linesOf($sale));
                $sale->update(['stage' => 'on_road', 'vehicle' => $request->input('vehicle') ?: $sale->vehicle]);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['qty' => \App\Support\Ui::msg($e)]);
        }
        AuditLog::record('wholesale_loaded', $sale->invoice_no);
        return back()->with('ok', "{$sale->invoice_no} loaded. Full cylinders are out of stock.");
    }

    public function deliver(Request $request, Sale $sale)
    {
        abort_unless($sale->type === 'wholesale' && $sale->stage === 'on_road', 422);
        $data = $request->validate([
            'empties' => ['required', 'integer', 'min:0', 'max:100000'],
            'empty_brand_id' => ['required', 'exists:brands,id'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'method' => ['required', Rule::in(['cash', 'bkash', 'nagad', 'bank', 'due'])],
        ]);
        try {
            DB::transaction(function () use ($sale, $data) {
                $paid = $data['method'] === 'due' ? 0 : (float) ($data['paid'] ?? 0);
                if ($paid > (float) $sale->total + 0.004) throw new RuntimeException('Paid is more than the bill.');
                SalesService::checkCredit($sale->customer, (float) $sale->total - $paid);
                $sale->update(['stage' => 'delivered', 'paid' => $paid, 'payment_method' => $data['method'], 'empty_brand_id' => $data['empty_brand_id'],
                    'empties_received' => $data['empties'], 'delivered_at' => now()]);
                SalesService::postEmpties($sale, SalesService::refillBySize($this->linesOf($sale)), (int) $data['empties'], (int) $data['empty_brand_id']);
                SalesService::postMoney($sale);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['qty' => \App\Support\Ui::msg($e)]);
        }
        AuditLog::record('wholesale_delivered', $sale->invoice_no);
        return back()->with('ok', "{$sale->invoice_no} delivered and billed to {$sale->customer->name}.");
    }
}
