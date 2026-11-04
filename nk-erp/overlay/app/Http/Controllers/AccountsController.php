<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Models\AuditLog;
use App\Models\CompanyEntry;
use App\Models\CompanyRate;
use App\Models\Expense;
use App\Models\MoneyAccount;
use App\Models\Partner;
use App\Models\SaleItem;
use App\Models\Sale;
use App\Support\CompanyCalc;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountsController extends Controller
{
    public function index(Request $request)
    {
        $tz = 'Asia/Dhaka';
        $ym = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('m')) ? $request->query('m') : now()->timezone($tz)->format('Y-m');
        $start = Carbon::parse($ym.'-01', $tz); $end = $start->copy()->endOfMonth();
        $toDate = min($end->toDateString(), now()->timezone($tz)->toDateString());
        $from = $start->copy()->utc(); $to = $end->copy()->endOfDay()->utc();

        $items = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.stage', '!=', 'voided')
            ->where(fn ($q) => $q->where('sales.type', 'retail')->orWhere('sales.stage', 'delivered'))
            ->whereBetween('sales.created_at', [$from, $to])->get(['sale_items.*', 'sales.created_at as sold_at']);
        $sales = Sale::query()->where('stage', '!=', 'voided')->where(fn ($q) => $q->where('type', 'retail')->orWhere('stage', 'delivered'))->whereBetween('created_at', [$from, $to]);
        $byCat = fn ($c) => (float) $items->whereIn('category', (array) $c)->sum('line_total');
        $delivery = (float) (clone $sales)->sum('delivery_charge'); $discount = (float) (clone $sales)->sum('discount');
        $totalSales = $byCat('refill') + $byCat('new_package') + $byCat(['accessory', 'service']) + $delivery - $discount;

        $cost = 0; $transport = 0; $pkgUncosted = 0;
        foreach ($items->whereIn('category', ['refill', 'new_package']) as $i) {
            $r = CompanyRate::forDate((int) $i->size_kg, Carbon::parse($i->sold_at)->timezone($tz)->toDateString());
            if (! $r) continue;
            if ($i->category === 'new_package') { $pkgUncosted += $i->qty; }
            $cost += $i->qty * (float) $r->do_rate; $transport += $i->qty * (float) $r->transport;
        }
        $gross = $totalSales - $cost;
        $calc = CompanyCalc::month($ym);
        $expenses = Expense::query()->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])->selectRaw('head, sum(amount) s')->groupBy('head')->pluck('s', 'head');
        $net = $gross + $transport + $calc['gross'] - $calc['ait'] + $calc['commission'] - (float) $expenses->sum();

        $partners = Partner::query()->orderBy('id')->get();
        $inv = (float) $partners->sum('investment');

        return view('accounts', [
            'ym' => $ym, 'start' => $start, 'toDate' => $toDate,
            'balances' => Money::balances($toDate), 'companyBal' => CompanyEntry::balanceUntil($toDate),
            'pl' => compact('delivery', 'discount', 'totalSales', 'cost', 'gross', 'transport', 'net', 'pkgUncosted') + ['refill' => $byCat('refill'), 'pkg' => $byCat('new_package'), 'acc' => $byCat(['accessory', 'service'])],
            'calc' => $calc, 'expenses' => $expenses,
            'partners' => $partners, 'investment' => $inv,
            'accounts' => MoneyAccount::query()->orderBy('id')->get(),
            'recent' => Expense::query()->with('account', 'reversedBy')->latest('id')->limit(12)->get(),
        ]);
    }

    public function expense(Request $request)
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date'], 'head' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:1'], 'account_id' => ['required', 'exists:money_accounts,id'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $e = Expense::create($data + ['created_by' => $request->user()->id]);
        AuditLog::record('expense', "#{$e->id} {$e->head} ৳{$e->amount}");
        return back()->with('ok', 'Expense saved.');
    }

    public function reverseExpense(Request $request, Expense $expense)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if ($expense->reversal_of || $expense->reversedBy) return back()->withErrors(['reason' => 'Already reversed.']);
        Expense::create(['expense_date' => now()->timezone('Asia/Dhaka')->toDateString(), 'head' => $expense->head, 'amount' => -$expense->amount,
            'account_id' => $expense->account_id, 'note' => 'Reverses #'.$expense->id.': '.$request->input('reason'), 'reversal_of' => $expense->id, 'created_by' => $request->user()->id]);
        AuditLog::record('expense_reversal', '#'.$expense->id);
        return back()->with('ok', 'Expense reversed.');
    }

    public function account(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'type' => ['required', Rule::in(array_keys(MoneyAccount::TYPES))],
            'opening_balance' => ['required', 'numeric'], 'opening_date' => ['required', 'date'],
        ]);
        DB::transaction(function () use ($data) {
            $isFirst = ! MoneyAccount::query()->where('type', $data['type'])->exists();
            MoneyAccount::create($data + ['is_default' => $isFirst]);
        });
        AuditLog::record('money_account', $data['name'].' opening '.$data['opening_balance']);
        return back()->with('ok', $data['name'].' added.');
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'transfer_date' => ['required', 'date'], 'from_account_id' => ['required', 'exists:money_accounts,id', 'different:to_account_id'],
            'to_account_id' => ['required', 'exists:money_accounts,id'], 'amount' => ['required', 'numeric', 'min:1'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        AccountTransfer::create($data + ['created_by' => $request->user()->id]);
        AuditLog::record('account_transfer', '৳'.$data['amount']);
        return back()->with('ok', 'Transfer saved.');
    }

    public function partner(Request $request)
    {
        $data = $request->validate(['id' => ['nullable', 'exists:partners,id'], 'name' => ['required', 'string', 'max:120'], 'investment' => ['required', 'numeric', 'min:0']]);
        $p = ! empty($data['id']) ? tap(Partner::findOrFail($data['id']))->update($data) : Partner::create($data);
        AuditLog::record('partner', $p->name.' ৳'.$p->investment);
        return back()->with('ok', $p->name.' saved.');
    }
}
