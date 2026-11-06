<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\DutySession;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Models\GpsPoint;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\Trip;
use App\Models\User;
use App\Support\SalesService;
use App\Support\TripCalc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Salesman mobile app (installable web app). A salesman sees only his own trip, vehicle and route.
 * Sales saved without internet wait on the phone as drafts and get a final invoice number only when the server confirms them.
 */
class AppController extends Controller
{
    private const TZ = 'Asia/Dhaka';

    public static function employee(User $u): ?Employee
    {
        return Employee::query()->where('user_id', $u->id)->first()
            ?? ($u->phone ? Employee::query()->where('phone', Customer::normalisePhone($u->phone))->orWhere('phone', $u->phone)->first() : null);
    }

    private function ctx(Request $r): array
    {
        $e = self::employee($r->user());
        abort_unless($e, 403, 'এই লগইন কোনো কর্মীর সাথে যুক্ত নয়। অফিসে যোগাযোগ করুন।');
        $trip = Trip::query()->with(['vehicle', 'route', 'driver', 'salesman'])->where(fn ($q) => $q->where('salesman_id', $e->id)->orWhere('driver_id', $e->id))
            ->whereIn('status', ['dispatched', 'returned', 'settled'])->orderByDesc('trip_date')->orderByDesc('id')->first();
        return [$e, $trip];
    }

    public function loginForm()
    {
        if (Auth::check()) return redirect()->route('app.home');
        return view('app.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:120'], 'password' => ['required', 'string']]);
        $login = trim($data['login']);
        $u = str_contains($login, '@') ? User::query()->where('email', strtolower($login))->first()
            : User::query()->whereIn('phone', [$login, Customer::normalisePhone($login)])->first();
        if (! $u || ! $u->is_active || ! \Illuminate\Support\Facades\Hash::check($data['password'], $u->password)) {
            AuditLog::create(['action' => 'app_login_failed', 'details' => $login, 'ip' => $request->ip()]);
            return back()->withErrors(['login' => 'মোবাইল নম্বর বা পাসওয়ার্ড সঠিক নয়।'])->onlyInput('login');
        }
        Auth::login($u, true);
        $request->session()->regenerate();
        $u->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('app_login');
        return redirect()->route('app.home');
    }

    public function logout(Request $request)
    {
        AuditLog::record('app_logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('app.login');
    }

    public function home(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $c = $trip ? TripCalc::of($trip) : null;
        $onVeh = [];
        if ($trip) foreach (Brand::SIZES as $s) $onVeh[$s] = (int) StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $s)->sum('full_delta');
        $orders = $trip ? \App\Models\CustomerOrder::query()->with('customer')->whereIn('status', ['approved', 'on_trip'])->where(fn ($q) => $q->where('trip_id', $trip->id)->orWhere('vehicle_id', $trip->vehicle_id))->get() : collect();
        return view('app.home', compact('e', 'trip', 'c', 'onVeh', 'orders'));
    }

    private function prices(): array
    {
        $out = [];
        // The route vehicle carries our own brand only, so the app sells our brand's products.
        $own = Brand::query()->where('for_sale', true)->orderBy('id')->value('id');
        foreach (Product::query()->where('is_active', true)->whereIn('category', ['refill', 'new_package'])->when($own, fn ($q) => $q->where('brand_id', $own))->orderBy('id')->get() as $p) {
            $price = $p->dealer_price ?? $p->retail_price;
            if ($price === null || ! $p->size_kg) continue;
            $out[$p->size_kg][$p->category === 'refill' ? 'ex' : 'nw'] = ['id' => $p->id, 'price' => (float) $price, 'brand' => $p->brand_id, 'img' => $p->image];
        }
        return $out;
    }

    public function sale(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $cust = $request->query('customer') ? Customer::find((int) $request->query('customer')) : null;
        $onVeh = [];
        if ($trip) foreach (Brand::SIZES as $s) $onVeh[$s] = (int) StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $s)->sum('full_delta');
        $draftNo = 'D-'.str_pad((string) ((int) Sale::query()->where('trip_id', $trip?->id)->count() + 1), 3, '0', STR_PAD_LEFT);
        return view('app.sale', ['e' => $e, 'trip' => $trip, 'cust' => $cust, 'due' => $cust?->due() ?? 0, 'emptyOut' => $cust ? array_sum($cust->cylindersOut()) : 0,
            'onVeh' => $onVeh, 'prices' => $this->prices(), 'brand' => Brand::query()->where('for_sale', true)->orderBy('id')->first(), 'draftNo' => $draftNo]);
    }

    /** Saves one sale. Called by the page (online) or by the phone's sync queue later (offline). Same tx id never posts twice. */
    public function storeSale(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $data = $request->validate([
            'tx' => ['required', 'string', 'max:40'], 'customer_id' => ['nullable', 'exists:customers,id'], 'walkin_name' => ['nullable', 'string', 'max:120'],
            'size' => ['required', Rule::in(Brand::SIZES)], 'type' => ['required', Rule::in(['ex', 'nw'])], 'qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'empty' => ['nullable', 'integer', 'min:0', 'max:1000'], 'pay' => ['required', Rule::in(['cash', 'mfs', 'bank'])], 'paid' => ['required', 'numeric', 'min:0'],
            'condition' => ['nullable', 'string', 'max:40'], 'draft' => ['nullable', 'string', 'max:12'], 'at' => ['nullable', 'date'],
        ]);
        if ($dup = Sale::query()->where('client_tx', $data['tx'])->first()) return response()->json(['ok' => true, 'invoice' => $dup->invoice_no, 'id' => $dup->id, 'url' => route('app.invoice', $dup)]);
        if (! $trip || $trip->status !== 'dispatched') return response()->json(['ok' => false, 'error' => 'আপনার কোনো চালু ট্রিপ নেই। অফিস থেকে লোড অনুমোদন হলে বিক্রি করা যাবে।'], 422);
        $p = $this->prices()[$data['size']][$data['type']] ?? null;
        if (! $p) return response()->json(['ok' => false, 'error' => 'এই সিলিন্ডারের দাম ঠিক করা নেই। অফিসে জানান।'], 422);
        try {
            $sale = DB::transaction(function () use ($data, $trip, $p, $request) {
                $lines = SalesService::lines([$p['id'] => $data['qty']], 'dealer_price');
                $customer = ! empty($data['customer_id']) ? Customer::find($data['customer_id']) : null;
                $total = round(array_sum(array_column($lines, 'total')), 2);
                $paid = min((float) $data['paid'], $total);
                SalesService::checkCredit($customer, $total - $paid);
                $refills = SalesService::refillBySize($lines);
                $method = ['cash' => 'cash', 'mfs' => 'bkash', 'bank' => 'bank'][$data['pay']];
                $empties = $data['type'] === 'ex' ? (int) ($data['empty'] ?? $data['qty']) : 0;
                \App\Support\Ui::lockNumbers();
                $sale = Sale::create(['invoice_no' => Sale::nextNo('retail'), 'type' => 'retail', 'customer_id' => $customer?->id, 'trip_id' => $trip->id, 'client_tx' => $data['tx'],
                    'walkin_name' => $customer ? null : ($data['walkin_name'] ?? 'Walk-in'), 'stage' => 'posted', 'subtotal' => $total, 'discount' => 0, 'delivery_charge' => 0,
                    'total' => $total, 'paid' => $paid, 'payment_method' => $paid < $total && $paid == 0 ? 'due' : $method, 'empty_brand_id' => Brand::query()->where('for_sale', true)->orderBy('id')->value('id'),
                    'empties_expected' => array_sum($refills), 'empties_received' => $empties, 'vehicle' => $trip->vehicle->code,
                    'note' => trim(($data['condition'] ?? '') && $data['condition'] !== 'ভালো' ? 'Empty: '.$data['condition'] : '').(($data['draft'] ?? null) ? ' · draft '.$data['draft'] : ''),
                    'created_by' => $request->user()->id, 'delivered_at' => $data['at'] ?? now()]);
                foreach ($lines as $l) {
                    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $l['product']->id, 'description' => $l['product']->name, 'category' => $l['product']->category,
                        'brand_id' => $l['product']->brand_id, 'size_kg' => $l['product']->size_kg, 'qty' => $l['qty'], 'unit_price' => $l['price'], 'line_total' => $l['total']]);
                }
                SalesService::postOut($sale, $lines);
                SalesService::postEmpties($sale, $refills, $empties, (int) $sale->empty_brand_id);
                SalesService::postMoney($sale);
                return $sale;
            });
        } catch (RuntimeException $ex) {
            return response()->json(['ok' => false, 'error' => \App\Support\Ui::msgBn($ex)], 422);
        }
        if ($sale->customer_id) {
            \App\Models\CustomerOrder::query()->where('customer_id', $sale->customer_id)->whereIn('status', ['approved', 'on_trip'])
                ->where(fn ($q) => $q->where('trip_id', $trip->id)->orWhere('vehicle_id', $trip->vehicle_id))->oldest('id')->limit(1)->update(['status' => 'delivered', 'sale_id' => $sale->id, 'trip_id' => $trip->id]);
        }
        if ($sale->customer && $sale->customer->lat === null) {
            $pt = GpsPoint::query()->where('employee_id', $e->id)->where('recorded_at', '>=', now()->subMinutes(5))->latest('recorded_at')->first();
            if ($pt && ($pt->accuracy === null || $pt->accuracy <= 100)) $sale->customer->update(['lat' => $pt->lat, 'lng' => $pt->lng]);
        }
        AuditLog::record('trip_sale', "{$trip->trip_no} · {$sale->invoice_no} · ".$sale->buyerName()." ৳{$sale->total} · salesman app");
        return response()->json(['ok' => true, 'invoice' => $sale->invoice_no, 'id' => $sale->id, 'url' => route('app.invoice', $sale)]);
    }

    private function own(Request $r, Sale $sale): void
    {
        [$e, $trip] = $this->ctx($r);
        abort_unless($sale->created_by === $r->user()->id || ($sale->trip && ($sale->trip->salesman_id === $e->id || $sale->trip->driver_id === $e->id)), 403);
    }

    public function invoice(Request $request, Sale $sale)
    {
        $this->own($request, $sale);
        $sale->load(['items', 'customer', 'trip.vehicle']);
        $sms = \App\Models\SmsMessage::query()->where('sale_id', $sale->id)->latest('id')->first();
        return view('app.invoice', ['sale' => $sale, 's' => collect($sale->company ?? \App\Models\Setting::snapshot()), 'sms' => $sms, 'shopDue' => $sale->customer?->due()]);
    }

    public function resendSms(Request $request, Sale $sale)
    {
        $this->own($request, $sale);
        \App\Support\Sms::forSale($sale);
        return back()->with('ok', 'SMS আবার পাঠানো হচ্ছে।');
    }

    public function printPage(Request $request, Sale $sale)
    {
        $this->own($request, $sale);
        $sale->load(['items', 'customer', 'trip.vehicle']);
        $hist = AuditLog::query()->where('action', 'app_print')->where('user_id', $request->user()->id)->latest('id')->limit(6)->get();
        return view('app.print', ['sale' => $sale, 's' => collect($sale->company ?? \App\Models\Setting::snapshot()), 'hist' => $hist, 'shopDue' => $sale->customer?->due(), 'e' => self::employee($request->user())]);
    }

    /** The receipt itself (80 / 58 mm or A4) for the salesman's own sale. */
    public function receipt(Request $request, Sale $sale)
    {
        $this->own($request, $sale);
        $size = in_array($request->query('size'), ['a4', '80', '58'], true) ? $request->query('size') : '80';
        if ($size === 'a4') DB::table('sales')->where('id', $sale->id)->increment('a4_prints');
        return view('sales.print', ['sale' => $sale->load(['items', 'customer', 'user']), 'size' => $size, 's' => collect($sale->company ?? \App\Models\Setting::snapshot())]);
    }

    public function logPrint(Request $request, Sale $sale)
    {
        $this->own($request, $sale);
        $first = $sale->pos_prints === 0;
        DB::table('sales')->where('id', $sale->id)->increment('pos_prints');
        AuditLog::record('app_print', $sale->invoice_no.' · '.($first ? 'প্রথম প্রিন্ট' : 'আবার প্রিন্ট'));
        return response()->json(['ok' => true]);
    }

    public function sales(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $sales = $trip ? Sale::query()->with(['items', 'customer'])->where('trip_id', $trip->id)->where('stage', '!=', 'voided')->latest('id')->get() : collect();
        return view('app.sales', compact('e', 'trip', 'sales'));
    }

    public function shops(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $q = trim((string) $request->query('q', ''));
        $shops = Customer::query()->where('is_active', true)
            ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('name', 'ilike', "%$q%")->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $q).'%')))
            ->when(! $q && $trip?->route, fn ($x) => $x->where(fn ($w) => $w->whereIn('id', Sale::query()->whereIn('trip_id', Trip::query()->where('route_id', $trip->route_id)->pluck('id'))->pluck('customer_id'))->orWhere('area', 'ilike', '%'.explode(' ', $trip->route->name)[0].'%')))
            ->orderBy('name')->limit(60)->get();
        return view('app.shops', ['e' => $e, 'trip' => $trip, 'shops' => $shops, 'q' => $q, 'pick' => $request->query('pick', 'sale')]);
    }

    public function empty(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $collected = []; $holders = collect();
        if ($trip) {
            foreach (Brand::SIZES as $s) $collected[$s] = (int) StockMovement::query()->where('trip_id', $trip->id)->where('kind', 'empty_in')->where('size_kg', $s)->sum('empty_delta');
            $routeCust = Sale::query()->whereIn('trip_id', Trip::query()->where('route_id', $trip->route_id)->pluck('id'))->whereNotNull('customer_id')->distinct()->pluck('customer_id');
            $holders = CustomerCylinder::query()->with('customer')->whereIn('customer_id', $routeCust)->selectRaw('customer_id, size_kg, sum(qty_delta) q, max(created_at) last')
                ->groupBy('customer_id', 'size_kg')->havingRaw('sum(qty_delta) > 0')->get();
        }
        return view('app.empty', ['e' => $e, 'trip' => $trip, 'collected' => $collected, 'holders' => $holders, 'brands' => Brand::query()->where('empty_collection', true)->orderBy('position')->orderBy('code')->get(),
            'own' => Brand::query()->where('for_sale', true)->orderBy('id')->first(), 'sel' => $request->query('c') ? $holders->first(fn ($h) => $h->customer_id == $request->query('c') && $h->size_kg == $request->query('s')) : $holders->first()]);
    }

    public function storeEmpty(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id'], 'size' => ['required', Rule::in(Brand::SIZES)], 'qty' => ['required', 'integer', 'min:1', 'max:500'],
            'brand_id' => ['required', 'exists:brands,id'], 'condition' => ['required', Rule::in(['ভালো', 'ভাল্ভ সমস্যা', 'নষ্ট'])]]);
        if (! $trip || $trip->status !== 'dispatched') return back()->withErrors(['qty' => 'চালু ট্রিপ ছাড়া খালি সংগ্রহ করা যাবে না।']);
        $c = Customer::find($data['customer_id']);
        $owed = (int) CustomerCylinder::query()->where('customer_id', $c->id)->where('size_kg', $data['size'])->sum('qty_delta');
        if ($data['qty'] > $owed) return back()->withErrors(['qty' => "{$c->name}-এর কাছে {$data['size']} কেজির মাত্র {$owed}টি খালি বাকি।"]);
        $own = Brand::query()->where('for_sale', true)->orderBy('id')->value('id');
        DB::transaction(function () use ($data, $trip, $c, $own) {
            StockMovement::post(['kind' => 'empty_in', 'brand_id' => $data['brand_id'], 'size_kg' => $data['size'], 'empty_delta' => $data['qty'], 'reference' => $trip->trip_no,
                'note' => 'Empty collected from '.$c->name.' · '.$data['condition'].((int) $data['brand_id'] !== (int) $own ? ' · other brand, kept separate' : ''), 'trip_id' => $trip->id]);
            CustomerCylinder::create(['customer_id' => $c->id, 'brand_id' => $own, 'size_kg' => $data['size'], 'qty_delta' => -$data['qty'], 'reference' => $trip->trip_no.' empty']);
        });
        AuditLog::record('trip_empty', "{$trip->trip_no} · {$c->name} {$data['size']} kg × {$data['qty']} · {$data['condition']}");
        return back()->with('ok', 'খালি সংরক্ষণ হয়েছে।');
    }

    public function pay(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $cust = $request->query('customer') ? Customer::find((int) $request->query('customer')) : null;
        $todayDue = $trip ? Sale::query()->with('customer')->where('trip_id', $trip->id)->whereNotNull('customer_id')->get()->filter(fn ($s) => $s->due() > 0) : collect();
        $oldest = $cust ? CustomerLedger::query()->where('customer_id', $cust->id)->where('kind', 'sale')->oldest('id')->first() : null;
        $hist = $cust ? CustomerLedger::query()->where('customer_id', $cust->id)->where('kind', 'payment')->latest('id')->limit(5)->get() : collect();
        return view('app.pay', ['e' => $e, 'trip' => $trip, 'cust' => $cust, 'due' => $cust?->due() ?? 0, 'oldest' => $oldest, 'hist' => $hist, 'todayDue' => $todayDue, 'tab' => $request->query('tab', 'old')]);
    }

    public function storePay(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id'], 'amount' => ['required', 'numeric', 'min:1'], 'method' => ['required', Rule::in(['cash', 'bkash', 'bank'])], 'tx' => ['required', 'string', 'max:40'], 'sale_id' => ['nullable', 'exists:sales,id']]);
        if (CustomerLedger::query()->where('client_tx', $data['tx'])->exists()) return redirect()->route('app.pay', ['customer' => $data['customer_id']])->with('ok', 'আগেই সংরক্ষিত।');
        if (! $trip || $trip->status !== 'dispatched') return back()->withErrors(['amount' => 'চালু ট্রিপ ছাড়া টাকা আদায় করা যাবে না।']);
        $c = Customer::find($data['customer_id']);
        if ((float) $data['amount'] > $c->due() + 0.004) return back()->withErrors(['amount' => 'বকেয়ার চেয়ে বেশি টাকা নেওয়া যাবে না (বকেয়া ৳'.number_format($c->due()).')।']);
        DB::transaction(function () use ($data, $trip, $c, $request) {
            $sale = ! empty($data['sale_id']) ? Sale::query()->lockForUpdate()->find($data['sale_id']) : null;
            if ($sale && $sale->customer_id === $c->id && $sale->trip_id === $trip->id) $sale->update(['paid' => (float) $sale->paid + min((float) $data['amount'], $sale->due())]);
            CustomerLedger::create(['customer_id' => $c->id, 'kind' => 'payment', 'reference' => $sale?->invoice_no ?? $trip->trip_no, 'sale_id' => $sale && $sale->trip_id === $trip->id ? $sale->id : null,
                'credit' => $data['amount'], 'method' => $data['method'], 'note' => $sale ? 'Paid on trip' : 'Previous due collected on '.$trip->trip_no, 'created_by' => $request->user()->id, 'trip_id' => $trip->id, 'client_tx' => $data['tx']]);
        });
        rescue(fn () => \App\Support\Sms::forPayment($c, (float) $data['amount'], null), null, false);
        AuditLog::record('trip_due_collected', "{$trip->trip_no} · {$c->name} ৳{$data['amount']} {$data['method']} · salesman app");
        return redirect()->route('app.pay', ['customer' => $c->id])->with('ok', '৳'.number_format((float) $data['amount']).' আদায় সংরক্ষণ হয়েছে।');
    }

    public function summary(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        return view('app.summary', ['e' => $e, 'trip' => $trip, 'c' => $trip ? TripCalc::of($trip) : null]);
    }

    public function requestClose(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        if (! $trip || $trip->status !== 'dispatched') return back()->withErrors(['x' => 'চালু ট্রিপ নেই।']);
        if ($request->input('pending', 0) > 0) return back()->withErrors(['x' => 'সব বিক্রি সিঙ্ক না হলে পাঠানো যাবে না।']);
        $trip->update(['return_requested_at' => now()]);
        AuditLog::record('trip_return_requested', "{$trip->trip_no} · salesman sent vehicle return & closing request");
        return back()->with('ok', 'রিকোয়েস্ট পাঠানো হয়েছে। গুদাম এখন গুনবে।');
    }

    public function duty(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $d = DutySession::query()->where('employee_id', $e->id)->whereNull('ended_at')->latest('id')->first();
        $last = $d ? GpsPoint::query()->where('duty_id', $d->id)->latest('recorded_at')->first() : null;
        return view('app.duty', compact('e', 'trip', 'd', 'last'));
    }

    public function toggleDuty(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $d = DutySession::query()->where('employee_id', $e->id)->whereNull('ended_at')->latest('id')->first();
        if ($d) { $d->update(['ended_at' => now()]); AuditLog::record('duty_end', $e->name); return back()->with('ok', 'ডিউটি শেষ। লোকেশন পাঠানো বন্ধ।'); }
        DutySession::create(['employee_id' => $e->id, 'trip_id' => $trip?->id, 'started_at' => now(), 'gps_consent' => $request->boolean('consent')]);
        AuditLog::record('duty_start', $e->name.($request->boolean('consent') ? ' · location allowed' : ' · no location'));
        return back()->with('ok', 'ডিউটি শুরু হয়েছে।');
    }

    /** Location points from the phone. Accepted only during duty and only when the salesman allowed location on the phone. */
    public function gps(Request $request)
    {
        $e = self::employee($request->user());
        $d = $e ? DutySession::query()->where('employee_id', $e->id)->whereNull('ended_at')->latest('id')->first() : null;
        if (! $d) return response()->json(['ok' => false, 'stop' => true]);
        $data = $request->validate(['points' => ['required', 'array', 'max:200'], 'points.*.lat' => ['required', 'numeric', 'between:-90,90'], 'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.acc' => ['nullable', 'numeric'], 'points.*.spd' => ['nullable', 'numeric'], 'points.*.t' => ['required', 'integer']]);
        if (! $d->gps_consent) $d->update(['gps_consent' => true]);
        foreach ($data['points'] as $p) {
            GpsPoint::create(['duty_id' => $d->id, 'employee_id' => $e->id, 'lat' => $p['lat'], 'lng' => $p['lng'], 'accuracy' => isset($p['acc']) ? min(65000, (int) $p['acc']) : null,
                'speed' => isset($p['spd']) ? min(9999, (float) $p['spd']) : null, 'recorded_at' => \Carbon\Carbon::createFromTimestampMs($p['t'])]);
        }
        return response()->json(['ok' => true, 'at' => now()->timezone(self::TZ)->format('H:i:s')]);
    }

    public function expense(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $mine = ExpenseClaim::query()->where('created_by', $request->user()->id)->latest('id')->limit(8)->get();
        return view('app.expense', compact('e', 'trip', 'mine'));
    }

    public function storeExpense(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $data = $request->validate(['t' => ['required', Rule::in(['fuel', 'toll', 'food', 'mob', 'fix', 'other'])], 'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'from' => ['required', Rule::in(['trip', 'own'])], 'description' => ['nullable', 'string', 'max:255'], 'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:6144']]);
        $code = ['fuel' => 'EX-301', 'toll' => 'EX-306', 'food' => 'EX-408', 'mob' => 'EX-408', 'fix' => 'EX-901', 'other' => 'EX-901'][$data['t']];
        $cat = ExpenseCategory::query()->where('code', $code)->first() ?? ExpenseCategory::query()->whereNotNull('parent_id')->first();
        $label = ['fuel' => 'জ্বালানি', 'toll' => 'টোল', 'food' => 'নাস্তা', 'mob' => 'মোবাইল', 'fix' => 'মেরামত', 'other' => 'অন্যান্য'][$data['t']];
        $useTrip = $data['from'] === 'trip' && $trip && in_array($trip->status, ['dispatched', 'returned'], true);
        $claim = ExpenseClaim::create(['claim_no' => ExpenseClaim::nextNo(), 'category_id' => $cat->id, 'expense_date' => now()->timezone(self::TZ)->toDateString(), 'amount' => $data['amount'],
            'cost_center' => $trip ? 'Vehicle '.$trip->vehicle->code : 'Employee — '.$e->name, 'payment' => $useTrip ? 'trip' : 'salary', 'trip_id' => $useTrip ? $trip->id : null,
            'description' => trim($label.' · '.($data['description'] ?? '')), 'extra' => ['employee' => $e->name, 'type' => $label],
            'receipt_path' => $request->hasFile('receipt') ? $request->file('receipt')->store('receipts', 'local') : null,
            'status' => 'submitted', 'submitted_at' => now(), 'created_by' => $request->user()->id]);
        AuditLog::record('expense_submitted', "{$claim->claim_no} {$label} ৳{$claim->amount} · salesman app");
        return back()->with('ok', 'দাবি পাঠানো হয়েছে। ম্যানেজার অনুমোদন দিলে জানতে পারবেন।');
    }

    public function notify(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $items = collect();
        if ($trip) {
            $items->push(['t' => 'ট্রিপ অনুমোদিত', 'b' => $trip->trip_no.' লোড হয়েছে: '.$trip->loads->sortBy('size_kg')->map(fn ($l) => $l->size_kg.' কেজি '.$l->qty)->implode(' · '), 'at' => $trip->dispatched_at, 'c' => 'blue']);
            foreach (Brand::SIZES as $s) {
                $q = (int) StockMovement::query()->where('trip_id', $trip->id)->where('size_kg', $s)->sum('full_delta');
                $loaded = (int) $trip->loads->where('size_kg', $s)->sum('qty');
                if ($loaded && $q <= max(3, (int) round($loaded * 0.2))) $items->push(['t' => $s.' কেজি স্টক কমছে', 'b' => 'গাড়িতে '.$q.'টি ভরা '.$s.' কেজি বাকি।', 'at' => now(), 'c' => 'warn']);
            }
        }
        foreach (Product::query()->where('updated_at', '>=', now()->subDays(2))->whereIn('category', ['refill', 'new_package'])->get() as $p) {
            $items->push(['t' => 'দামের তালিকা হালনাগাদ', 'b' => $p->name.' এখন ৳'.number_format((float) ($p->dealer_price ?? $p->retail_price)).'। অ্যাপে নিজে থেকেই বসবে।', 'at' => $p->updated_at, 'c' => 'pos']);
        }
        foreach (ExpenseClaim::query()->where('created_by', $request->user()->id)->whereIn('status', ['posted', 'paid', 'rejected'])->where('updated_at', '>=', now()->subDays(7))->get() as $c) {
            $items->push(['t' => 'খরচের দাবি '.['posted' => 'অনুমোদিত', 'paid' => 'পরিশোধিত', 'rejected' => 'বাতিল'][$c->status], 'b' => $c->claim_no.' · ৳'.number_format((float) $c->amount).($c->reject_reason ? ' · '.$c->reject_reason : ''), 'at' => $c->updated_at, 'c' => $c->status === 'rejected' ? 'neg' : 'pos']);
        }
        return view('app.notify', ['e' => $e, 'trip' => $trip, 'items' => $items->sortByDesc('at')->values()]);
    }

    public function manifest()
    {
        return response()->json(['name' => 'NK Corporation সেলসম্যান', 'short_name' => 'NK সেলস', 'start_url' => '/app', 'scope' => '/app', 'display' => 'standalone',
            'background_color' => '#ffffff', 'theme_color' => '#123a86', 'icons' => [['src' => '/img/logo.webp', 'sizes' => '512x512', 'type' => 'image/webp']]], 200, ['Content-Type' => 'application/manifest+json']);
    }

    /** Quick exchange on the route: the request waits for office approval; nothing is posted from the phone. */
    public function exchange(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $own = Brand::query()->where('for_sale', true)->orderBy('id')->first();
        $active = Brand::query()->where('is_active', true)->orderBy('position')->orderBy('position')->orderBy('code')->get();
        $cust = $request->query('customer') ? Customer::find((int) $request->query('customer')) : null;
        $mine = $trip ? \App\Models\CylinderExchange::query()->with(['lines.inBrand', 'lines.outBrand', 'sale'])->where('trip_id', $trip->id)->orderByDesc('id')->get() : collect();
        $types = \App\Models\CylinderExchange::enabledTypes();
        $quotes = [];
        // The vehicle carries our own brand only, so the app hands out our full cylinders.
        if ($own) foreach ($active as $b) foreach (array_map('intval', $b->sizes ?? []) as $s) {
            if (! in_array($s, array_map('intval', $own->sizes ?? []), true)) continue;
            try { $q = \App\Support\ExchangeService::quote($b->id, $own->id, $s, 'route'); $quotes[$b->id][$s] = ['unit' => $q['unit'], 'charge' => $q['charge'], 'type' => $q['type'], 'hold' => $q['hold']]; } catch (\Throwable) {}
        }
        return view('app.exchange', ['e' => $e, 'trip' => $trip, 'own' => $own, 'brands' => $active, 'cust' => $cust, 'mine' => $mine, 'quotes' => $quotes, 'types' => $types]);
    }

    public function storeExchange(Request $request)
    {
        [$e, $trip] = $this->ctx($request);
        $data = $request->validate([
            'given_brand_id' => ['required', 'exists:brands,id'], 'size_kg' => ['required', Rule::in(Brand::SIZES)], 'qty' => ['required', 'integer', 'min:1', 'max:100'],
            'condition' => ['required', Rule::in(array_keys(\App\Models\CylinderExchange::CONDITIONS))], 'ownership' => ['required', Rule::in(array_keys(\App\Models\CylinderExchange::OWNERSHIP))],
            'customer_id' => ['nullable', 'exists:customers,id'], 'walkin_name' => ['nullable', 'string', 'max:120'], 'walkin_phone' => ['nullable', 'string', 'max:20'],
            'method' => ['required', Rule::in(['cash', 'bkash', 'due'])], 'paid' => ['nullable', 'numeric', 'min:0'], 'note' => ['nullable', 'string', 'max:255'], 'tx' => ['required', 'string', 'max:40'],
            'ack_name' => ['required', 'string', 'max:120'], 'photo' => ['nullable', 'image', 'max:8192'],
        ], ['ack_name.required' => 'ক্রেতার নাম লিখে স্বীকৃতি নিন।', 'qty.max' => 'একবারে ১০০টির বেশি নয়।']);
        if (\App\Models\CylinderExchange::query()->where('client_tx', $data['tx'])->exists()) return redirect()->route('app.exchange')->with('ok', 'আগেই পাঠানো হয়েছে।');
        if (! $trip || $trip->status !== 'dispatched') return back()->withInput()->withErrors(['qty' => 'চালু ট্রিপ ছাড়া এক্সচেঞ্জ করা যাবে না।']);
        $own = (int) Brand::query()->where('for_sale', true)->orderBy('id')->value('id');
        try {
            $x = DB::transaction(function () use ($data, $trip, $own, $request) {
                $p = \App\Support\ExchangeService::prepare([['in_brand_id' => $data['given_brand_id'], 'in_size' => $data['size_kg'], 'in_qty' => $data['qty'], 'in_condition' => $data['condition'], 'in_ownership' => $data['ownership'],
                    'out_brand_id' => $own, 'out_size' => $data['size_kg'], 'out_qty' => $data['qty'], 'out_kind' => 'full']], 'route');
                $total = $p['total'];
                $paid = $data['method'] === 'due' ? (float) ($data['paid'] ?? 0) : $total;
                if ($paid > $total + 0.004) throw new RuntimeException('বিলের চেয়ে বেশি টাকা নেওয়া যাবে না।');
                if (empty($data['customer_id']) && $paid < $total - 0.004) throw new RuntimeException('দোকান বাছাই না করলে পুরো টাকা নিতে হবে।');
                $vehFull = (int) StockMovement::balance($own, (int) $data['size_kg'], $trip->id)['full'];
                $waiting = (int) \App\Models\CylinderExchangeLine::query()->where('out_brand_id', $own)->where('out_size', $data['size_kg'])->where('out_kind', 'full')
                    ->whereHas('exchange', fn ($q) => $q->where('trip_id', $trip->id)->whereIn('status', ['pending', 'held']))->sum('out_qty');
                if ($vehFull - $waiting < $data['qty']) throw new RuntimeException('গাড়িতে '.\App\Support\Ui::bn($data['size_kg']).' কেজির ভরা সিলিন্ডার যথেষ্ট নেই (আছে '.\App\Support\Ui::bn(max(0, $vehFull - $waiting)).'টি)।');
                \App\Support\Ui::lockNumbers();
                $l = $p['lines'][0]; unset($l['product']);
                $x = \App\Models\CylinderExchange::create([
                    'ref_no' => \App\Models\CylinderExchange::nextRef(), 'channel' => 'route', 'trip_id' => $trip->id, 'customer_id' => $data['customer_id'] ?? null,
                    'walkin_name' => $data['walkin_name'] ?? null, 'walkin_phone' => $data['walkin_phone'] ?? null, 'exchange_date' => now('Asia/Dhaka')->toDateString(),
                    'size_kg' => $data['size_kg'], 'qty' => $data['qty'], 'given_brand_id' => $data['given_brand_id'], 'taken_brand_id' => $own, 'unit_price' => $l['refill_price'], 'charge_unit' => $l['fee'],
                    'total' => $total, 'paid' => $paid, 'method' => $data['method'], 'type' => $p['type'], 'status' => $p['hold'] ? 'held' : 'pending', 'hold_reason' => $p['hold'] ? implode('; ', $p['hold']) : null,
                    'note' => $data['note'] ?? null, 'requested_by' => $request->user()->id, 'client_tx' => $data['tx'], 'ack_name' => $data['ack_name'],
                    'photo' => \App\Http\Controllers\CylinderExchangeController::storeDoc($request->file('photo'), 'app'),
                ]);
                $x->lines()->create($l);
                return $x;
            });
        } catch (RuntimeException $ex) {
            return back()->withInput()->withErrors(['qty' => \App\Support\Ui::msgBn($ex)]);
        }
        AuditLog::record('cylinder_exchange', "{$x->ref_no} requested from {$trip->trip_no}".($x->status === 'held' ? " · held: {$x->hold_reason}" : ''));
        return redirect()->route('app.exchange')->with('ok', $x->status === 'held' ? "{$x->ref_no} রিভিউতে আটকে আছে — অফিস দেখে সিদ্ধান্ত দেবে। ভরা সিলিন্ডার এখন দেবেন না।" : "{$x->ref_no} অফিসে অনুমোদনের জন্য পাঠানো হয়েছে।");
    }
}
