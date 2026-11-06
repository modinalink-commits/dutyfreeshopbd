<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandSettlement;
use App\Models\Customer;
use App\Models\CylinderDeposit;
use App\Models\CylinderExchange;
use App\Models\CylinderExchangeLine;
use App\Models\ExchangeCharge;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Trip;
use App\Models\User;
use App\Support\ExchangeService;
use App\Support\Ui;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Cylinder exchange & brand settlement (Inventory & cylinder management → Cylinder exchange).
 * Counter staff and the route app enter exchanges; managers approve; unsafe, unknown or unauthorised cylinders are held for an admin.
 */
class CylinderExchangeController extends Controller
{
    public const DECIDERS = ['super_admin', 'admin', 'manager'];
    public const ADMINS = ['super_admin', 'admin'];
    public const VIEWS = ['dash' => 'Dashboard', 'new' => 'New exchange', 'all' => 'All transactions', 'own' => 'Own brand', 'cross' => 'Cross-brand',
        'stock' => 'Brand-wise empty stock', 'settle' => 'Brand settlement', 'rates' => 'Rate settings', 'approve' => 'Approval', 'reports' => 'Reports'];

    public function index(Request $request)
    {
        $v = array_key_exists($request->query('v'), self::VIEWS) ? $request->query('v') : 'dash';
        $u = $request->user();
        $brands = Brand::query()->orderByDesc('for_sale')->orderBy('name')->get();
        $active = $brands->where('is_active', true)->values();
        $own = $brands->firstWhere('for_sale', true);
        $pendingCount = CylinderExchange::query()->whereIn('status', ['pending', 'held'])->count();
        $d = compact('v', 'brands', 'active', 'own', 'pendingCount') + ['canDecide' => in_array($u->role, self::DECIDERS, true), 'isAdmin' => in_array($u->role, self::ADMINS, true), 'isSuper' => $u->role === 'super_admin'];

        if ($v === 'dash') {
            $day = [now('Asia/Dhaka')->startOfDay()->utc(), now('Asia/Dhaka')->endOfDay()->utc()];
            $today = CylinderExchange::query()->where('status', 'approved')->whereBetween('decided_at', $day);
            $tl = CylinderExchangeLine::query()->whereIn('exchange_id', (clone $today)->pluck('id'));
            $d['k'] = [
                'today' => (clone $today)->count(), 'todayAmt' => (float) (clone $today)->sum('total'),
                'own' => (clone $today)->where('type', 'A')->count(), 'cross' => (clone $today)->whereIn('type', ['B', 'C', 'D', 'MX'])->count(),
                'in' => (int) (clone $tl)->sum('in_qty'), 'out' => (int) (clone $tl)->where('out_kind', 'full')->sum('out_qty'),
                'pending' => CylinderExchange::query()->where('status', 'pending')->count(), 'held' => CylinderExchange::query()->where('status', 'held')->count(),
                'quarantine' => (int) StockMovement::query()->where('kind', 'damaged')->where('note', 'like', 'Exchange %')->sum('damaged_delta')
                    + (int) StockMovement::query()->where('kind', 'reversal')->where('note', 'like', 'Reverse exchange %')->sum('damaged_delta'),
            ];
            $d['balances'] = $this->balances();
            $d['k']['settle'] = collect($d['balances'])->filter(fn ($b) => $b['net'] != 0)->count();
            $d['recent'] = CylinderExchange::query()->with(['customer', 'lines.inBrand', 'lines.outBrand', 'sale'])->latest('id')->limit(8)->get();
        } elseif (in_array($v, ['all', 'own', 'cross', 'approve'], true)) {
            $s = $request->query('s');
            $d['s'] = $s;
            $d['list'] = CylinderExchange::query()->with(['customer', 'lines.inBrand', 'lines.outBrand', 'requester', 'decider', 'sale', 'trip.vehicle'])
                ->when($v === 'own', fn ($q) => $q->where('type', 'A'))
                ->when($v === 'cross', fn ($q) => $q->whereIn('type', ['B', 'C', 'D', 'MX']))
                ->when($v === 'approve', fn ($q) => $q->whereIn('status', ['pending', 'held']))
                ->when($s && isset(CylinderExchange::STATUS[$s]), fn ($q) => $q->where('status', $s))
                ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('ref_no', 'ilike', "%$t%")->orWhere('walkin_name', 'ilike', "%$t%")->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%$t%"))))
                ->orderByRaw("case when status in ('pending','held') then 0 else 1 end")->orderByDesc('id')->paginate(30)->withQueryString();
            if ($v === 'approve') foreach ($d['list'] as $x) $x->blockers = ExchangeService::blockers($x);
        } elseif ($v === 'new') {
            $d['customers'] = Customer::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone', 'type']);
            $d['trips'] = Trip::query()->with(['vehicle', 'salesman', 'driver'])->where('status', 'dispatched')->orderByDesc('id')->get();
            $d['types'] = CylinderExchange::enabledTypes();
        } elseif ($v === 'stock') {
            $rows = [];
            foreach ($brands as $b) foreach (array_map('intval', $b->sizes ?? []) as $s) {
                $g = StockMovement::balance($b->id, $s);
                $veh = StockMovement::query()->where('brand_id', $b->id)->where('size_kg', $s)->whereNotNull('trip_id')->selectRaw('coalesce(sum(full_delta),0) f, coalesce(sum(empty_delta),0) e')->first();
                $xin = (int) CylinderExchangeLine::query()->where('in_brand_id', $b->id)->where('in_size', $s)->whereHas('exchange', fn ($q) => $q->where('status', 'approved'))->sum('in_qty');
                if (! array_sum($g) && ! $veh->f && ! $veh->e && ! $xin) continue;
                $rows[] = ['brand' => $b, 'size' => $s, 'g' => $g, 'vf' => (int) $veh->f, 've' => (int) $veh->e, 'xin' => $xin];
            }
            $d['rows'] = $rows;
        } elseif ($v === 'settle') {
            $d['balances'] = $this->balances();
            $d['ledger'] = BrandSettlement::query()->with(['brand', 'creator', 'exchange'])->when($request->query('brand'), fn ($q, $b) => $q->where('brand_id', $b))->latest('id')->paginate(40)->withQueryString();
        } elseif ($v === 'rates') {
            $d['charges'] = ExchangeCharge::query()->get()->groupBy('brand_id')->map(fn ($r) => $r->pluck('charge', 'size_kg'));
            $d['types'] = CylinderExchange::enabledTypes();
            $d['edit'] = $request->query('brand') ? Brand::find((int) $request->query('brand')) : null;
        } elseif ($v === 'reports') {
            $d['reports'] = collect(\App\Support\Reports::all()['Cylinder exchange'] ?? [])->filter(fn ($r) => in_array($u->role, $r[1], true));
            $d['from'] = $request->query('from', now('Asia/Dhaka')->startOfMonth()->toDateString());
            $d['to'] = $request->query('to', now('Asia/Dhaka')->toDateString());
            $d['rep'] = $request->query('r') && isset($d['reports'][$request->query('r')]) ? $request->query('r') : $d['reports']->keys()->first();
            if ($d['rep']) [$d['head'], $d['rows'], $d['total']] = \App\Support\Reports::build($d['rep'], ['from' => $d['from'], 'to' => $d['to']]);
        }
        return view('exchange.index', $d);
    }

    /** Brand-wise settlement balance: + we hold that many of their cylinders. Amounts only for Super Admin. */
    private function balances(): array
    {
        return BrandSettlement::query()->with('brand')->selectRaw('brand_id, size_kg, sum(qty_received) r, sum(qty_delivered) d, sum(amount) a, max(created_at) last')
            ->groupBy('brand_id', 'size_kg')->get()
            ->map(fn ($x) => ['brand' => $x->brand, 'size' => (int) $x->size_kg, 'in' => (int) $x->r, 'out' => (int) $x->d, 'net' => (int) $x->r - (int) $x->d, 'amount' => (float) $x->a, 'last' => $x->last])
            ->sortBy(fn ($x) => $x['brand']?->name)->values()->all();
    }

    /** Live price for the form: the same checks the save runs. */
    public function quote(Request $request)
    {
        try {
            $r = ExchangeService::prepare($this->rows($request), $request->input('channel') === 'route' ? 'route' : 'counter');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => Ui::msg($e)]);
        }
        return response()->json(['ok' => true, 'total' => $r['total'], 'type' => $r['type'], 'hold' => $r['hold'],
            'lines' => array_map(fn ($l) => ['type' => $l['type'], 'refill' => $l['refill_price'], 'fee' => $l['fee'], 'qty' => $l['out_qty'], 'total' => $l['line_total'], 'product' => $l['product']?->name], $r['lines'])]);
    }

    private function rows(Request $r): array
    {
        $rows = [];
        foreach ((array) $r->input('lines', []) as $l) {
            if (! is_array($l) || empty($l['in_brand_id'])) continue;
            $rows[] = array_intersect_key($l, array_flip(['in_brand_id', 'in_size', 'in_qty', 'in_condition', 'in_ownership', 'out_brand_id', 'out_size', 'out_qty', 'out_kind', 'out_condition']));
        }
        return $rows;
    }

    public static function storeDoc(?UploadedFile $f, string $prefix): ?string
    {
        if (! $f) return null;
        $name = 'exchange-docs/'.$prefix.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.strtolower($f->extension() ?: 'bin');
        Storage::disk('local')->put($name, file_get_contents($f->getRealPath()));
        return $name;
    }

    /** Documents are private: served only to signed-in office staff. */
    public function doc(Request $request)
    {
        $p = (string) $request->query('p');
        abort_unless(preg_match('#^exchange-docs/[A-Za-z0-9._-]+$#', $p) && Storage::disk('local')->exists($p), 404);
        if (str_contains($p, '/settle-')) abort_unless($request->user()->role === 'super_admin', 403);
        return Storage::disk('local')->response($p);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exchange_date' => ['required', 'date', 'before_or_equal:'.now('Asia/Dhaka')->toDateString()],
            'trip_id' => ['nullable', 'exists:trips,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'walkin_name' => ['nullable', 'string', 'max:120'],
            'walkin_phone' => ['nullable', 'string', 'max:20'],
            'method' => ['required', Rule::in(array_keys(Sale::METHODS))],
            'paid' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'deposit_adj' => ['nullable', 'numeric', 'min:-10000000', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
            'lines' => ['required', 'array', 'min:1', 'max:20'],
            'lines.*.in_brand_id' => ['required', 'exists:brands,id'], 'lines.*.out_brand_id' => ['required', 'exists:brands,id'],
            'lines.*.in_size' => ['required', Rule::in(Brand::SIZES)], 'lines.*.out_size' => ['required', Rule::in(Brand::SIZES)],
            'lines.*.in_qty' => ['required', 'integer', 'min:1', 'max:500'], 'lines.*.out_qty' => ['required', 'integer', 'min:1', 'max:500'],
            'lines.*.in_condition' => ['required', Rule::in(array_keys(CylinderExchange::CONDITIONS))], 'lines.*.in_ownership' => ['required', Rule::in(array_keys(CylinderExchange::OWNERSHIP))],
            'lines.*.out_kind' => ['required', Rule::in(['full', 'empty'])],
        ], [], ['lines.*.in_qty' => 'received quantity', 'lines.*.out_qty' => 'delivered quantity']);
        $user = $request->user();
        if ($data['exchange_date'] !== now('Asia/Dhaka')->toDateString() && ! in_array($user->role, self::ADMINS, true)) {
            return back()->withInput()->withErrors(['exchange_date' => 'Only Super Admin or Admin can enter an exchange for an earlier date.']);
        }
        $trip = ! empty($data['trip_id']) ? Trip::find($data['trip_id']) : null;
        if ($trip && $trip->status !== 'dispatched') return back()->withInput()->withErrors(['trip_id' => "{$trip->trip_no} is not on the road. Pick the godown or a vehicle that is out."]);
        try {
            $x = DB::transaction(function () use ($data, $user, $trip, $request) {
                $p = ExchangeService::prepare($this->rows($request), $trip ? 'route' : 'counter');
                $total = $p['total'];
                $paid = $data['method'] === 'due' ? (float) ($data['paid'] ?? 0) : $total;
                if ($paid > $total + 0.004) throw new RuntimeException('Paid amount is more than the bill.');
                if (empty($data['customer_id']) && $paid < $total - 0.004) throw new RuntimeException('A walk-in customer must pay in full. Pick a saved customer to leave a due.');
                $dep = (float) ($data['deposit_adj'] ?? 0);
                if ($dep < 0 && empty($data['customer_id'])) throw new RuntimeException('A deposit can be returned only to a saved customer.');
                if ($dep < 0 && abs($dep) > (float) CylinderDeposit::query()->where('customer_id', $data['customer_id'])->sum('amount') + 0.004) throw new RuntimeException('This customer has less deposit on record than you are returning.');
                Ui::lockNumbers();
                $first = $p['lines'][0];
                $x = CylinderExchange::create([
                    'ref_no' => CylinderExchange::nextRef(), 'channel' => $trip ? 'route' : 'counter', 'trip_id' => $trip?->id, 'customer_id' => $data['customer_id'] ?? null,
                    'walkin_name' => $data['walkin_name'] ?? null, 'walkin_phone' => $data['walkin_phone'] ?? null, 'exchange_date' => $data['exchange_date'],
                    'size_kg' => $first['out_size'], 'qty' => array_sum(array_column($p['lines'], 'out_qty')), 'given_brand_id' => $first['in_brand_id'], 'taken_brand_id' => $first['out_brand_id'],
                    'unit_price' => $first['refill_price'], 'charge_unit' => $first['fee'], 'total' => $total, 'paid' => $paid, 'method' => $data['method'], 'deposit_adj' => $dep,
                    'type' => $p['type'], 'status' => 'pending', 'note' => $data['note'] ?? null, 'requested_by' => $user->id,
                    'attachment' => self::storeDoc($request->file('attachment'), 'ex'), 'hold_reason' => $p['hold'] ? implode('; ', $p['hold']) : null,
                ]);
                foreach ($p['lines'] as $l) { unset($l['product']); $x->lines()->create($l); }
                if ($p['hold']) $x->forceFill(['status' => 'held'])->save();
                elseif (in_array($user->role, self::DECIDERS, true)) ExchangeService::post($x->fresh('lines'), $user);
                else AuditLog::record('cylinder_exchange', "{$x->ref_no} requested · ৳{$total}");
                if ($p['hold']) AuditLog::record('cylinder_exchange', "{$x->ref_no} held for review: {$x->hold_reason}");
                return $x->fresh(['sale']);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['lines' => Ui::msg($e)]);
        }
        if ($x->status === 'approved') {
            return $x->sale ? redirect()->route('sales.show', $x->sale)->with('ok', "{$x->ref_no} posted — invoice {$x->sale->invoice_no}.")
                : redirect()->route('cx.index', ['v' => 'all'])->with('ok', "{$x->ref_no} posted (empty swap, no bill).");
        }
        return redirect()->route('cx.index', ['v' => 'approve'])->with('ok', $x->status === 'held' ? "{$x->ref_no} is held for review: {$x->hold_reason}." : "{$x->ref_no} sent for approval.");
    }

    public function approve(Request $request, CylinderExchange $exchange)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $u = $request->user();
        try {
            DB::transaction(function () use ($exchange, $u, $data) {
                $x = CylinderExchange::query()->lockForUpdate()->findOrFail($exchange->id);
                if (! in_array($x->status, ['pending', 'held'], true)) throw new RuntimeException("{$x->ref_no} is already ".strtolower(CylinderExchange::STATUS[$x->status]).'.');
                if ($x->status === 'held') {
                    if (! in_array($u->role, self::ADMINS, true)) throw new RuntimeException('Held exchanges need Super Admin or Admin.');
                    if (trim((string) ($data['note'] ?? '')) === '') throw new RuntimeException('Write what you checked before releasing a held exchange.');
                }
                if ($x->trip && $x->trip->status === 'closed') throw new RuntimeException("The trip for {$x->ref_no} is already closed. Reject it and enter it again from the godown.");
                if (! empty($data['note'])) $x->forceFill(['decision_note' => $data['note']])->save();
                ExchangeService::post($x, $u);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['lines' => Ui::msg($e)]);
        }
        return back()->with('ok', "{$exchange->ref_no} approved and posted.");
    }

    public function reject(Request $request, CylinderExchange $exchange)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $n = CylinderExchange::query()->whereKey($exchange->id)->whereIn('status', ['pending', 'held'])
            ->update(['status' => 'rejected', 'decision_note' => $data['reason'], 'decided_by' => $request->user()->id, 'decided_at' => now(), 'updated_at' => now()]);
        if (! $n) return back()->withErrors(['lines' => "{$exchange->ref_no} is not waiting any more."]);
        AuditLog::record('cylinder_exchange', "{$exchange->ref_no} rejected: {$data['reason']}");
        return back()->with('ok', "{$exchange->ref_no} rejected.");
    }

    public function reverse(Request $request, CylinderExchange $exchange)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        try {
            DB::transaction(function () use ($exchange, $request, $data) {
                $x = CylinderExchange::query()->lockForUpdate()->with(['sale', 'lines', 'trip'])->findOrFail($exchange->id);
                if ($x->trip && $x->trip->status !== 'dispatched') throw new RuntimeException("{$x->trip->trip_no} is back from the market. Fix it with an adjustment in Daily closing.");
                ExchangeService::reverse($x, $request->user(), $data['reason']);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['lines' => Ui::msg($e)]);
        }
        return back()->with('ok', "{$exchange->ref_no} reversed with adjustment entries.");
    }

    public function saveCharges(Request $request)
    {
        $data = $request->validate(['c' => ['array'], 'c.*' => ['array'], 'c.*.*' => ['nullable', 'numeric', 'min:0', 'max:100000']]);
        $n = 0;
        foreach ($data['c'] ?? [] as $brandId => $sizes) {
            if (! Brand::query()->whereKey($brandId)->exists()) continue;
            foreach ($sizes as $size => $v) {
                if (! in_array((int) $size, Brand::SIZES, true)) continue;
                $cur = ExchangeCharge::query()->where('brand_id', $brandId)->where('size_kg', $size)->first();
                if ($v === null || $v === '') { if ($cur) { $cur->delete(); $n++; } continue; }
                if ($cur && (float) $cur->charge === (float) $v) continue;
                ExchangeCharge::updateOrCreate(['brand_id' => $brandId, 'size_kg' => $size], ['charge' => $v, 'updated_by' => $request->user()->id]);
                $n++;
            }
        }
        if ($n) AuditLog::record('exchange_charges', "{$n} exchange charge(s) changed");
        return redirect()->route('cx.index', ['v' => 'rates'])->with('ok', $n ? "{$n} exchange charge(s) saved." : 'No change.');
    }

    public function saveTypes(Request $request)
    {
        $data = $request->validate(['types' => ['array'], 'types.*' => [Rule::in(array_keys(CylinderExchange::TYPES))]]);
        $t = array_values(array_intersect(array_keys(CylinderExchange::TYPES), $data['types'] ?? []));
        Setting::put('exchange_types', implode(',', $t));
        AuditLog::record('exchange_types', 'Enabled: '.($t ? implode(', ', $t) : 'none'));
        return redirect()->route('cx.index', ['v' => 'rates'])->with('ok', 'Exchange types saved.');
    }

    public function saveRule(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'exchange_allowed' => ['required', 'boolean'], 'exchange_partner' => ['required', 'boolean'],
            'ownership_policy' => ['required', Rule::in(array_keys(Brand::OWNERSHIP))], 'agreement_ref' => ['nullable', 'string', 'max:80'],
            'deposit_rule' => ['nullable', 'string', 'max:255'], 'settlement_rule' => ['nullable', 'string', 'max:255'], 'fee_rule' => ['nullable', 'string', 'max:255'],
        ]);
        if ($data['exchange_allowed'] && $data['exchange_partner'] && trim((string) ($data['agreement_ref'] ?? '')) === '' && ! $brand->for_sale) {
            return back()->withInput()->withErrors(['agreement_ref' => "Write the supplier agreement reference for {$brand->name} before allowing exchange."]);
        }
        $brand->update($data);
        AuditLog::record('brand_exchange_rule', "{$brand->name}: ".($brand->exchangeAuthorized() ? 'exchange allowed ('.$brand->agreement_ref.')' : 'exchange not allowed'));
        return redirect()->route('cx.index', ['v' => 'rates'])->with('ok', "{$brand->name} exchange rules saved.");
    }

    /** A settlement with a brand: cylinders returned to / taken back from them, and money (Super Admin only). */
    public function settle(Request $request)
    {
        $data = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'], 'size_kg' => ['required', Rule::in(Brand::SIZES)],
            'qty_returned' => ['nullable', 'integer', 'min:0', 'max:100000'], 'qty_taken' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'amount' => ['nullable', 'numeric', 'min:-100000000', 'max:100000000'], 'status' => ['required', Rule::in(['open', 'settled'])],
            'reference' => ['nullable', 'string', 'max:40'], 'note' => ['required', 'string', 'max:255'],
            'document' => ['nullable', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
        $b = Brand::find($data['brand_id']);
        if ($b->for_sale) return back()->withErrors(['brand_id' => 'Settlement is with other brands only.']);
        if (! $b->agreement_ref) return back()->withInput()->withErrors(['brand_id' => "No agreement reference on record for {$b->name}. Settlements must trace to an approved agreement."]);
        if (! ($data['qty_returned'] ?? 0) && ! ($data['qty_taken'] ?? 0) && ! (float) ($data['amount'] ?? 0)) return back()->withInput()->withErrors(['note' => 'Enter cylinders or an amount.']);
        $isSuper = $request->user()->role === 'super_admin';
        if (! $isSuper && (float) ($data['amount'] ?? 0) != 0) return back()->withInput()->withErrors(['amount' => 'Only Super Admin can record settlement money.']);
        try {
            DB::transaction(function () use ($data, $b, $request) {
                $ref = $data['reference'] ?? ('BS-'.now('Asia/Dhaka')->format('ymdHis'));
                $ret = (int) ($data['qty_returned'] ?? 0); $tak = (int) ($data['qty_taken'] ?? 0);
                // Cylinders physically leaving or entering the godown move stock like any other entry.
                if ($ret) StockMovement::post(['kind' => 'exchange', 'brand_id' => $b->id, 'size_kg' => $data['size_kg'], 'empty_delta' => -$ret, 'reference' => $ref, 'note' => "Brand settlement: returned to {$b->name}"]);
                if ($tak) StockMovement::post(['kind' => 'exchange', 'brand_id' => $b->id, 'size_kg' => $data['size_kg'], 'empty_delta' => $tak, 'reference' => $ref, 'note' => "Brand settlement: received from {$b->name}"]);
                BrandSettlement::create(['brand_id' => $b->id, 'size_kg' => $data['size_kg'], 'kind' => 'settlement', 'qty_received' => $tak, 'qty_delivered' => $ret,
                    'amount' => (float) ($data['amount'] ?? 0), 'status' => $data['status'], 'reference' => $ref, 'agreement_ref' => $b->agreement_ref,
                    'document' => self::storeDoc($request->file('document'), 'settle'), 'note' => $data['note'], 'created_by' => $request->user()->id]);
                if ($data['status'] === 'settled') BrandSettlement::query()->where('brand_id', $b->id)->where('size_kg', $data['size_kg'])->where('status', 'open')->update(['status' => 'settled']);
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['qty_returned' => Ui::msg($e)]);
        }
        AuditLog::record('brand_settlement', "{$b->name} {$data['size_kg']} kg · returned ".($data['qty_returned'] ?? 0).' · taken '.($data['qty_taken'] ?? 0));
        return redirect()->route('cx.index', ['v' => 'settle'])->with('ok', "Settlement with {$b->name} recorded.");
    }
}
