<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $f = $request->query('f');
        $dues = CustomerLedger::query()->selectRaw('customer_id, sum(debit - credit) d')->groupBy('customer_id')->pluck('d', 'customer_id');
        $outs = CustomerCylinder::query()->selectRaw('customer_id, sum(qty_delta) q')->groupBy('customer_id')->pluck('q', 'customer_id');

        $list = Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', "%{$q}%")->orWhere('phone', 'like', '%'.Customer::normalisePhone($q).'%')->orWhere('area', 'ilike', "%{$q}%")))
            ->when($f === 'dealer', fn ($w) => $w->where('type', 'dealer'))
            ->orderBy('name')->get()
            ->when($f === 'due', fn ($c) => $c->filter(fn ($x) => ($dues[$x->id] ?? 0) > 0.004))
            ->when($f === 'empties', fn ($c) => $c->filter(fn ($x) => ($outs[$x->id] ?? 0) > 0));

        $sel = $request->query('c') ? Customer::find($request->query('c')) : null;
        $detail = null;
        if ($sel) {
            $rows = CustomerLedger::query()->with('user')->where('customer_id', $sel->id)->orderBy('id')->get();
            $run = 0;
            $rows->each(function ($r) use (&$run) { $run += (float) $r->debit - (float) $r->credit; $r->running = $run; });
            $monthKg = Sale::query()->where('customer_id', $sel->id)->where('stage', '!=', 'voided')->where('created_at', '>=', now()->startOfMonth())
                ->with('items')->get()->flatMap->items->sum(fn ($i) => $i->size_kg ? $i->size_kg * $i->qty : 0);
            $detail = [
                'rows' => $rows,
                'due' => $sel->due(),
                'cyl' => $sel->cylindersOut(),
                'monthKg' => $monthKg,
                'openOrders' => Sale::query()->where('customer_id', $sel->id)->where('stage', 'delivered')->whereColumn('paid', '<', 'total')->get(),
            ];
        }

        return view('customers', [
            'list' => $list, 'dues' => $dues, 'outs' => $outs, 'q' => $q, 'f' => $f, 'sel' => $sel, 'detail' => $detail,
            'counts' => ['all' => Customer::count(), 'dealer' => Customer::where('type', 'dealer')->count(),
                'due' => $dues->filter(fn ($d) => $d > 0.004)->count(), 'empties' => $outs->filter(fn ($q) => $q > 0)->count()],
            'edit' => $request->boolean('new') ? new Customer(['type' => 'retail', 'credit_limit' => 0]) : ($request->query('edit') ? Customer::find($request->query('edit')) : null),
            'brands' => Brand::query()->where('empty_collection', true)->orderBy('code')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $id = $request->input('id');
        $request->merge(['phone' => Customer::normalisePhone((string) $request->input('phone'))]);
        $data = $request->validate([
            'id' => ['nullable', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', Rule::unique('customers', 'phone')->ignore($id)],
            'type' => ['required', Rule::in(array_keys(Customer::TYPES))],
            'area' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'opening_due' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['phone.regex' => 'Enter an 11-digit mobile number like 01XXXXXXXXX.']);
        $limit = (float) ($data['credit_limit'] ?? 0);
        if ($limit > 0 && ! in_array($request->user()->role, ['super_admin', 'admin'], true)) {
            $current = $id ? (float) Customer::find($id)->credit_limit : 0;
            if (abs($current - $limit) > 0.004) abort(403, 'Only Super Admin or Admin can set a credit limit.');
        }
        $opening = (float) ($data['opening_due'] ?? 0);
        unset($data['id'], $data['opening_due']);
        $data['credit_limit'] = $limit;

        $c = DB::transaction(function () use ($id, $data, $opening, $request) {
            if ($id) {
                $c = Customer::findOrFail($id);
                $c->fill($data)->save();
            } else {
                $c = Customer::create($data + ['code' => Customer::nextCode()]);
                if ($opening > 0) {
                    CustomerLedger::create(['customer_id' => $c->id, 'kind' => 'opening', 'reference' => 'Opening', 'debit' => $opening, 'note' => 'Opening due', 'created_by' => $request->user()->id]);
                }
            }
            return $c;
        });
        AuditLog::record($id ? 'customer_updated' : 'customer_created', "{$c->code} {$c->name}");

        return redirect()->route('customers.index', ['c' => $c->id])->with('ok', "{$c->name} saved.");
    }

    public function pay(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:100000000'],
            'method' => ['required', Rule::in(['cash', 'bkash', 'nagad', 'bank'])],
            'reference' => ['nullable', 'string', 'max:40'],
            'sale_id' => ['nullable', 'exists:sales,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($data, $customer, $request) {
            $sale = null;
            if (! empty($data['sale_id'])) {
                $sale = Sale::query()->lockForUpdate()->findOrFail($data['sale_id']);
                if ($sale->customer_id !== $customer->id) abort(422);
                if ((float) $data['amount'] > $sale->due() + 0.004) throw new RuntimeException("{$sale->invoice_no} has only ৳".number_format($sale->due()).' left.');
                $sale->update(['paid' => (float) $sale->paid + (float) $data['amount']]);
            }
            CustomerLedger::create(['customer_id' => $customer->id, 'kind' => 'payment', 'reference' => $data['reference'] ?? $sale?->invoice_no,
                'sale_id' => $sale?->id, 'credit' => $data['amount'], 'method' => $data['method'], 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
        });
        AuditLog::record('customer_payment', "{$customer->code} ৳{$data['amount']} {$data['method']}");

        return back()->with('ok', 'Payment of ৳'.number_format((float) $data['amount']).' saved.');
    }

    public function empties(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'size_kg' => ['required', Rule::in(Brand::SIZES)],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        try {
            DB::transaction(function () use ($data, $customer) {
                $ref = 'EMP-'.$customer->code;
                StockMovement::post(['kind' => 'empty_in', 'brand_id' => $data['brand_id'], 'size_kg' => $data['size_kg'], 'empty_delta' => $data['qty'], 'reference' => $ref, 'note' => 'Returned by '.$customer->name]);
                $own = Brand::query()->where('for_sale', true)->value('id');
                CustomerCylinder::create(['customer_id' => $customer->id, 'brand_id' => $own, 'size_kg' => $data['size_kg'], 'qty_delta' => -$data['qty'], 'reference' => $ref]);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }
        AuditLog::record('customer_empties', "{$customer->code} {$data['qty']} × {$data['size_kg']}kg");
        return back()->with('ok', "{$data['qty']} empties received from {$customer->name}.");
    }
}
