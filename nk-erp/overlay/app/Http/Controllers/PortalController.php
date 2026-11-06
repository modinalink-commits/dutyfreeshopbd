<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerCylinder;
use App\Models\CustomerLedger;
use App\Models\CustomerOrder;
use App\Models\Sale;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/** Customer self-service: OTP login by SMS; a customer sees only his own shop's account. */
class PortalController extends Controller
{
    private function me(Request $r): Customer
    {
        $c = Customer::find($r->session()->get('cust_id'));
        abort_unless($c && $c->is_active && $c->portal_enabled, 403);
        if (! $c->portal_last_seen || $c->portal_last_seen->lt(now()->subMinutes(5))) $c->forceFill(['portal_last_seen' => now()])->saveQuietly();
        return $c;
    }

    public function login(Request $request)
    {
        if ($request->session()->get('cust_id')) return redirect()->route('my.home');
        return view('portal.login', ['phone' => $request->session()->get('otp_phone'), 'sms' => Sms::configured()]);
    }

    public function sendCode(Request $request)
    {
        $phone = Customer::normalisePhone((string) $request->input('phone'));
        $c = Customer::query()->where('phone', $phone)->where('is_active', true)->first();
        $request->session()->put('otp_phone', $phone);
        // The same answer whether or not the number is ours, so nobody can test which numbers are customers.
        if ($c && $c->portal_enabled) {
            $last = \Illuminate\Support\Facades\DB::table('customer_otps')->where('phone', $phone)->latest('id')->value('created_at');
            if (! $last || now()->diffInSeconds(\Carbon\Carbon::parse($last), true) >= 60) {
                $code = (string) random_int(100000, 999999);
                \Illuminate\Support\Facades\DB::table('customer_otps')->insert(['phone' => $phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'created_at' => now()]);
                Sms::queue('otp', $phone, $code.' — '.\App\Models\Setting::get('company_name', 'NK Corporation').' লগইন কোড। ১০ মিনিট চলবে। কাউকে বলবেন না।', null, $c->id);
            }
        }
        return redirect()->route('my.login')->with('ok', 'নম্বরটি আমাদের গ্রাহক তালিকায় থাকলে ৬ সংখ্যার কোড SMS-এ যাবে।');
    }

    public function verify(Request $request)
    {
        $phone = $request->session()->get('otp_phone');
        $code = preg_replace('/\D/', '', implode('', (array) $request->input('code', [])));
        if (! $phone || strlen($code) !== 6) return back()->withErrors(['code' => '৬ সংখ্যার কোড দিন।']);
        $row = \Illuminate\Support\Facades\DB::table('customer_otps')->where('phone', $phone)->where('expires_at', '>', now())->latest('id')->first();
        if (! $row || $row->attempts >= 5 || ! Hash::check($code, $row->code_hash)) {
            if ($row) \Illuminate\Support\Facades\DB::table('customer_otps')->where('id', $row->id)->increment('attempts');
            return back()->withErrors(['code' => 'কোড সঠিক নয় বা মেয়াদ শেষ।']);
        }
        \Illuminate\Support\Facades\DB::table('customer_otps')->where('phone', $phone)->delete();
        $c = Customer::query()->where('phone', $phone)->firstOrFail();
        $request->session()->regenerate();
        $request->session()->put('cust_id', $c->id);
        AuditLog::create(['action' => 'portal_login', 'details' => $c->code, 'ip' => $request->ip()]);
        return redirect()->route('my.home');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['cust_id', 'otp_phone']);
        $request->session()->regenerateToken();
        return redirect()->route('my.login');
    }

    public function home(Request $request)
    {
        $c = $this->me($request);
        $year = now()->timezone('Asia/Dhaka')->startOfYear();
        $sales = Sale::query()->where('customer_id', $c->id)->where('stage', '!=', 'voided');
        $lastSale = (clone $sales)->latest('id')->first();
        $lastPay = CustomerLedger::query()->where('customer_id', $c->id)->where('kind', 'payment')->latest('id')->first();
        $bought = (int) \App\Models\SaleItem::query()->whereIn('sale_id', (clone $sales)->pluck('id'))->whereIn('category', ['refill', 'new_package'])->sum('qty');
        $refills = (int) \App\Models\SaleItem::query()->whereIn('sale_id', (clone $sales)->pluck('id'))->where('category', 'refill')->sum('qty');
        $out = $c->cylindersOut();
        $tx = CustomerLedger::query()->with('sale')->where('customer_id', $c->id)->latest('id')->limit(6)->get();
        return view('portal.home', ['c' => $c, 'due' => $c->due(), 'lastSale' => $lastSale, 'lastPay' => $lastPay, 'bought' => $bought, 'returned' => $refills - array_sum($out), 'out' => $out,
            'yearBuy' => (float) (clone $sales)->where('created_at', '>=', $year->utc())->sum('total'),
            'yearPay' => (float) CustomerLedger::query()->where('customer_id', $c->id)->where('kind', 'payment')->where('created_at', '>=', $year->utc())->sum('credit'), 'tx' => $tx]);
    }

    public function purchases(Request $request)
    {
        $c = $this->me($request);
        $r = $request->query('r', '2m');
        $q = trim((string) $request->query('q', ''));
        $from = $r === 'month' ? now()->timezone('Asia/Dhaka')->startOfMonth() : ($r === 'date' && $request->query('from') ? \Carbon\Carbon::parse($request->query('from'), 'Asia/Dhaka') : now()->timezone('Asia/Dhaka')->subMonths(2));
        $inv = Sale::query()->with('items')->where('customer_id', $c->id)->where('stage', '!=', 'voided')->where('created_at', '>=', $from->copy()->utc())
            ->when($q, fn ($x) => $x->where('invoice_no', 'ilike', "%$q%"))->latest('id')->get();
        return view('portal.purchases', compact('c', 'inv', 'r', 'q'));
    }

    public function invoice(Request $request, Sale $sale)
    {
        $c = $this->me($request);
        abort_unless($sale->customer_id === $c->id, 404);
        $sale->load(['items', 'customer', 'user', 'trip.vehicle', 'trip.salesman']);
        if ($request->query('print') === 'a4' || $request->query('size')) {
            return view('sales.print', ['sale' => $sale, 'size' => $request->query('size') === '80' ? '80' : 'a4', 's' => collect($sale->company ?? \App\Models\Setting::snapshot())]);
        }
        return view('portal.invoice', ['sale' => $sale]);
    }

    public function payments(Request $request)
    {
        $c = $this->me($request);
        $rows = CustomerLedger::query()->with('sale')->where('customer_id', $c->id)->orderBy('id')->get();
        $run = 0; $list = [];
        foreach ($rows as $r) {
            $prev = $run; $run += (float) $r->debit - (float) $r->credit;
            if ($r->kind === 'payment') $list[] = ['r' => $r, 'prev' => $prev, 'cur' => $run];
        }
        $list = array_reverse($list);
        if ($request->query('r', '2m') === '2m') $list = array_values(array_filter($list, fn ($x) => $x['r']->created_at->gte(now()->subMonths(2))));
        return view('portal.payments', ['c' => $c, 'list' => $list, 'due' => $run]);
    }

    public function empties(Request $request)
    {
        $c = $this->me($request);
        $saleIds = Sale::query()->where('customer_id', $c->id)->where('stage', '!=', 'voided')->pluck('id');
        $bySize = [];
        foreach (\App\Models\Brand::SIZES as $s) {
            $bought = (int) \App\Models\SaleItem::query()->whereIn('sale_id', $saleIds)->where('category', 'refill')->where('size_kg', $s)->sum('qty');
            $owed = (int) CustomerCylinder::query()->where('customer_id', $c->id)->where('size_kg', $s)->sum('qty_delta');
            $bySize[$s] = ['bought' => $bought, 'ret' => $bought - $owed, 'owed' => $owed];
        }
        $newPkg = \App\Models\SaleItem::query()->whereIn('sale_id', $saleIds)->where('category', 'new_package')->selectRaw('size_kg, sum(qty) q')->groupBy('size_kg')->pluck('q', 'size_kg');
        $tx = Sale::query()->with('items')->where('customer_id', $c->id)->where('stage', '!=', 'voided')->where('empties_expected', '>', 0)->latest('id')->limit(8)->get();
        return view('portal.empty', ['c' => $c, 'bySize' => $bySize, 'newPkg' => $newPkg, 'tx' => $tx, 'brand' => \App\Models\Brand::query()->where('for_sale', true)->orderBy('id')->value('name') ?? 'Aygaz United']);
    }

    public function statement(Request $request)
    {
        $c = $this->me($request);
        $from = $request->query('from', now()->timezone('Asia/Dhaka')->startOfMonth()->subMonth()->toDateString());
        $to = $request->query('to', now()->timezone('Asia/Dhaka')->toDateString());
        $a = \Carbon\Carbon::parse($from, 'Asia/Dhaka')->startOfDay()->utc(); $b = \Carbon\Carbon::parse($to, 'Asia/Dhaka')->endOfDay()->utc();
        $open = (float) CustomerLedger::query()->where('customer_id', $c->id)->where('created_at', '<', $a)->selectRaw('coalesce(sum(debit - credit),0) d')->value('d');
        $rows = CustomerLedger::query()->with('sale.items')->where('customer_id', $c->id)->whereBetween('created_at', [$a, $b])->orderBy('id')->get();
        $items = \App\Models\SaleItem::query()->whereIn('sale_id', $rows->pluck('sale_id')->filter()->unique())->where('category', 'refill')->get();
        $cyl = [];
        foreach ($items->groupBy('size_kg') as $s => $g) {
            $got = Sale::query()->whereIn('id', $g->pluck('sale_id'))->sum('empties_received');
            $cyl[$s] = [(int) $g->sum('qty'), (int) min($got, $g->sum('qty'))];
        }
        return view('portal.statement', compact('c', 'from', 'to', 'open', 'rows', 'cyl') + ['buy' => (float) $rows->where('kind', 'sale')->sum('debit'), 'pay' => (float) $rows->where('kind', 'payment')->sum('credit'),
            'adj' => (float) $rows->whereNotIn('kind', ['sale', 'payment'])->sum(fn ($r) => (float) $r->debit - (float) $r->credit)]);
    }

    public function order(Request $request)
    {
        $c = $this->me($request);
        return view('portal.order', ['c' => $c, 'orders' => CustomerOrder::query()->where('customer_id', $c->id)->latest('id')->limit(6)->get(), 'brand' => \App\Models\Brand::query()->where('for_sale', true)->orderBy('id')->value('name') ?? 'Aygaz United']);
    }

    public function storeOrder(Request $request)
    {
        $c = $this->me($request);
        $data = $request->validate(['size' => ['required', Rule::in(\App\Models\Brand::SIZES)], 'qty' => ['required', 'integer', 'min:1', 'max:500'], 'type' => ['nullable', Rule::in(['ex', 'nw'])],
            'address' => ['required', 'string', 'max:255'], 'wanted_on' => ['required', 'date', 'after_or_equal:today'], 'note' => ['nullable', 'string', 'max:255']]);
        if (CustomerOrder::query()->where('customer_id', $c->id)->where('status', 'new')->count() >= 3) return back()->withErrors(['qty' => 'আপনার ৩টি অর্ডার অফিসের অনুমোদনের অপেক্ষায় আছে।']);
        $o = CustomerOrder::create(['order_no' => CustomerOrder::nextNo(), 'customer_id' => $c->id, 'items' => [['size' => (int) $data['size'], 'qty' => (int) $data['qty'], 'type' => $data['type'] ?? 'ex']],
            'address' => $data['address'], 'wanted_on' => $data['wanted_on'], 'note' => $data['note'] ?? null, 'status' => 'new']);
        if (! $c->address) $c->update(['address' => $data['address']]);
        AuditLog::create(['action' => 'customer_order', 'details' => "{$o->order_no} {$c->code} ".$o->itemsText(), 'ip' => $request->ip()]);
        return redirect()->route('my.order')->with('ok', "অর্ডার {$o->order_no} পাঠানো হয়েছে। অফিস অনুমোদন করলে জানানো হবে।");
    }
}
