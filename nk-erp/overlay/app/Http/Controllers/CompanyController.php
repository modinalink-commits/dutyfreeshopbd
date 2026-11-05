<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\CompanyEntry;
use App\Models\CompanyMonth;
use App\Models\CompanyRate;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Support\CompanyCalc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $ym = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('m')) ? $request->query('m') : now()->timezone('Asia/Dhaka')->format('Y-m');
        $start = Carbon::parse($ym.'-01'); $end = (clone $start)->endOfMonth();
        $prev = (clone $start)->subMonth()->format('Y-m'); $next = (clone $start)->addMonth()->format('Y-m');
        $opening = (float) CompanyEntry::query()->where('entry_date', '<', $start->toDateString())->sum('amount');
        $entries = CompanyEntry::query()->with('reversedBy')->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])->orderBy('entry_date')->orderBy('id')->get();

        // Group challan lines of the same challan and day into one row like the design.
        $rows = [['d' => $start->format('d-m-Y'), 't' => 'Opening balance', 'q' => '', 'do' => null, 'tr' => null, 'bill' => null, 'dep' => null, 'bal' => $opening, 'kind' => 'o', 'ids' => []]];
        $bal = $opening;
        foreach ($entries->groupBy(fn ($e) => $e->kind === 'challan' ? 'c|'.$e->entry_date->toDateString().'|'.$e->challan_no : 'e|'.$e->id) as $g) {
            $e = $g->first();
            $amt = (float) $g->sum('amount'); $bal += $amt;
            if ($e->kind === 'challan') {
                $q = []; foreach (Brand::SIZES as $s) { $q[] = (int) $g->where('size_kg', $s)->sum('qty'); }
                $rows[] = ['d' => $e->entry_date->format('d-m-Y'), 't' => 'Challan '.$e->challan_no, 'q' => implode(' / ', $q),
                    'do' => $g->sum(fn ($x) => $x->qty * $x->do_rate), 'tr' => $g->sum(fn ($x) => $x->qty * $x->transport), 'bill' => -$amt, 'dep' => null, 'bal' => $bal, 'kind' => '', 'ids' => $g->pluck('id')->all(), 'rev' => $g->every(fn ($x) => $x->reversedBy)];
            } else {
                $label = match ($e->kind) {
                    'deposit' => 'Deposit · '.($e->source ?: (CompanyEntry::DEPOSIT_KINDS[$e->deposit_kind] ?? 'Deposit')),
                    'incentive' => '＋ Deposit + Incentive ('.Carbon::parse($e->for_month.'-01')->format('F').')',
                    'commission' => '＋ Deposit + Commission ('.Carbon::parse($e->for_month.'-01')->format('F').')',
                    default => CompanyEntry::KINDS[$e->kind].($e->note ? ' · '.$e->note : ''),
                };
                $rows[] = ['d' => $e->entry_date->format('d-m-Y'), 't' => $label, 'q' => '', 'do' => null, 'tr' => null,
                    'bill' => $amt < 0 ? -$amt : null, 'dep' => $amt >= 0 ? $amt : null, 'bal' => $bal,
                    'kind' => in_array($e->kind, ['incentive', 'commission']) ? 'p' : ($e->kind === 'deposit' ? 'd' : ''), 'ids' => [$e->id], 'rev' => (bool) $e->reversedBy];
            }
        }

        // Faded rows: incentive and commission still to come for earlier months.
        $coming = [];
        foreach ([(clone $start)->subMonths(2)->format('Y-m'), $prev] as $cm) {
            $c = CompanyCalc::month($cm);
            if (! $c['incentiveReceived'] && $c['net'] > 0 && $c['incentiveDate'] >= $start->toDateString() && $c['incentiveDate'] <= $end->toDateString()) {
                $coming[] = ['d' => $c['incentiveDate'], 't' => '＋ Deposit + Incentive ('.Carbon::parse($cm.'-01')->format('F').')', 'amt' => $c['net'], 'm' => $cm, 'k' => 'incentive'];
            }
            if (! $c['commissionReceived'] && $c['commission'] > 0 && $c['commissionDate'] >= $start->toDateString() && $c['commissionDate'] <= $end->toDateString()) {
                $coming[] = ['d' => $c['commissionDate'], 't' => '＋ Deposit + Commission ('.Carbon::parse($cm.'-01')->format('F').')', 'amt' => $c['commission'], 'm' => $cm, 'k' => 'commission'];
            }
        }
        usort($coming, fn ($a, $b) => strcmp($a['d'], $b['d']));
        $run = $bal;
        foreach ($coming as $c) { $run += $c['amt']; $rows[] = ['d' => Carbon::parse($c['d'])->format('d-m-Y'), 't' => $c['t'], 'q' => '', 'do' => null, 'tr' => null, 'bill' => null, 'dep' => $c['amt'], 'bal' => $run, 'kind' => 'pf', 'ids' => []]; }

        $calcPrev = CompanyCalc::month($prev);
        $deps = $entries->where('kind', 'deposit');
        $chal = $entries->where('kind', 'challan');
        $today = now()->timezone('Asia/Dhaka')->toDateString();

        // "View calculation": how today's balance adds up, month by month and kind by kind.
        $bn = ['January' => 'জানুয়ারি', 'February' => 'ফেব্রুয়ারি', 'March' => 'মার্চ', 'April' => 'এপ্রিল', 'May' => 'মে', 'June' => 'জুন', 'July' => 'জুলাই', 'August' => 'আগস্ট', 'September' => 'সেপ্টেম্বর', 'October' => 'অক্টোবর', 'November' => 'নভেম্বর', 'December' => 'ডিসেম্বর'];
        $kindBn = ['opening' => 'শুরুর ব্যালান্স', 'deposit' => 'জমা', 'challan' => 'চালান বিল', 'incentive' => 'ইনসেনটিভ', 'commission' => 'কমিশন', 'adjustment' => 'সংশোধন', 'reversal' => 'বাতিল/রিভার্সাল'];
        $order = array_flip(array_keys($kindBn));
        $explain = CompanyEntry::query()->where('entry_date', '<=', $today)
            ->selectRaw("to_char(entry_date, 'YYYY-MM') ym, kind, sum(amount) amt, count(*) n, max(entry_date) last")->groupBy('ym', 'kind')->get()
            ->sortBy(fn ($r) => $r->ym.sprintf('%02d', $order[$r->kind] ?? 9))
            ->filter(fn ($r) => abs((float) $r->amt) > 0.004)
            ->map(fn ($r) => ['t' => ($r->kind === 'opening' ? '' : ($bn[Carbon::parse($r->ym.'-01')->format('F')] ?? $r->ym).'-এর ').($kindBn[$r->kind] ?? $r->kind), 'a' => (float) $r->amt, 'n' => (int) $r->n, 'm' => $r->ym])->values();
        $prevChal = CompanyEntry::query()->where('kind', 'challan')->whereBetween('entry_date', [Carbon::parse($prev.'-01')->toDateString(), Carbon::parse($prev.'-01')->endOfMonth()->toDateString()])->get();

        return view('company.index', [
            'explain' => $explain, 'prevTons' => $prevChal->sum(fn ($x) => $x->qty * $x->size_kg) / 1000, 'bnMonth' => $bn,
            'ym' => $ym, 'start' => $start, 'prev' => $prev, 'next' => $next, 'rows' => $rows,
            'balanceToday' => CompanyEntry::balanceUntil($today),
            'deposited' => (float) $deps->sum('amount'), 'depCount' => $deps->count(),
            'tons' => $chal->sum(fn ($x) => $x->qty * $x->size_kg) / 1000, 'challans' => $chal->pluck('challan_no')->unique()->count(), 'billed' => -(float) $chal->sum('amount'),
            'calc' => $calcPrev, 's' => Setting::query()->pluck('value', 'key'),
        ]);
    }

    public function storeDeposit(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:1'],
            'deposit_kind' => ['required', Rule::in(array_keys(CompanyEntry::DEPOSIT_KINDS))],
            'source' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:255'],
        ]);
        $e = CompanyEntry::create($data + ['kind' => 'deposit', 'created_by' => $request->user()->id]);
        AuditLog::record('company_deposit', "#{$e->id} ৳{$e->amount}");
        return redirect()->route('company.index', ['m' => $e->entry_date->format('Y-m')])->with('ok', 'Deposit saved.');
    }

    public function storeChallan(Request $request)
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'], 'challan_no' => ['required', 'string', 'max:40'],
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'to_stock' => ['nullable', 'boolean'], 'empties' => ['nullable', 'array'], 'empties.*' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'], 'confirm_dup' => ['nullable', 'boolean'],
        ]);
        // Same challan number saved before? Ask first: the bill would be cut twice.
        if (empty($data['confirm_dup'])) {
            $prev = CompanyEntry::query()->where('kind', 'challan')->where('challan_no', trim($data['challan_no']))->orderBy('id')->get();
            if ($prev->isNotEmpty()) {
                return back()->withInput()->with('dup', ['no' => $data['challan_no'], 'date' => $prev->first()->entry_date->format('d-m-Y'), 'm' => $prev->first()->entry_date->format('Y-m'),
                    'q' => $prev->map(fn ($e) => $e->size_kg.' কেজি × '.$e->qty)->implode(', ')]);
            }
        }
        try {
            DB::transaction(function () use ($data, $request) {
                $any = false;
                foreach (Brand::SIZES as $s) {
                    $q = (int) ($data['qty'][$s] ?? 0);
                    if (! $q) continue;
                    $rate = CompanyRate::forDate($s, $data['entry_date']);
                    if (! $rate) throw new RuntimeException("{$s} কেজির রেট এই তারিখে ({$data['entry_date']}) বসানো হয়নি, তাই বিল তৈরি হয়নি (০ ধরে বিল হবে না)। Rate Master-এ {$s} কেজির রেট লিখুন, তারপর আবার সেভ করুন।");
                    CompanyEntry::create(['entry_date' => $data['entry_date'], 'kind' => 'challan', 'challan_no' => $data['challan_no'], 'size_kg' => $s, 'qty' => $q,
                        'do_rate' => $rate->do_rate, 'transport' => $rate->transport, 'amount' => -round($q * ((float) $rate->do_rate - (float) $rate->transport), 2),
                        'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
                    if (! empty($data['to_stock'])) {
                        $own = Brand::query()->where('for_sale', true)->value('id');
                        StockMovement::post(['kind' => 'received', 'brand_id' => $own, 'size_kg' => $s, 'full_delta' => $q,
                            'empty_delta' => -((int) ($data['empties'][$s] ?? 0)), 'reference' => 'Challan '.$data['challan_no'], 'note' => 'From company ledger']);
                    }
                    $any = true;
                }
                if (! $any) throw new RuntimeException('Enter at least one quantity.');
            });
        } catch (RuntimeException $e) {
            $r = back()->withInput()->withErrors(['qty' => $e->getMessage()]);
            return str_contains($e->getMessage(), 'Rate Master') ? $r->with('err_link', [route('company.rates'), 'Rate Master খুলুন →']) : $r;
        }
        AuditLog::record('company_challan', $data['challan_no']);
        return redirect()->route('company.index', ['m' => substr($data['entry_date'], 0, 7)])->with('ok', "Challan {$data['challan_no']} saved.");
    }

    public function receive(Request $request)
    {
        $data = $request->validate([
            'for_month' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'kind' => ['required', Rule::in(['incentive', 'commission'])],
            'entry_date' => ['required', 'date'], 'actual' => ['nullable', 'numeric', 'min:0'],
        ]);
        $c = CompanyCalc::month($data['for_month']);
        if ($c[$data['kind'].'Received']) return back()->withErrors(['actual' => 'Already recorded for this month.']);
        $calc = $data['kind'] === 'incentive' ? $c['net'] : $c['commission'];
        $amount = $data['actual'] !== null && $data['actual'] !== '' ? (float) $data['actual'] : $calc;
        CompanyEntry::create(['entry_date' => $data['entry_date'], 'kind' => $data['kind'], 'for_month' => $data['for_month'], 'amount' => $amount,
            'gross' => $data['kind'] === 'incentive' ? round($amount / (1 - (float) $c['month']->ait_pct / 100), 2) : $amount,
            'ait' => $data['kind'] === 'incentive' ? round($amount / (1 - (float) $c['month']->ait_pct / 100) - $amount, 2) : 0,
            'note' => abs($amount - $calc) > 0.5 ? 'Calculated ৳'.number_format($calc, 2).', received ৳'.number_format($amount, 2) : null,
            'created_by' => $request->user()->id]);
        AuditLog::record('company_'.$data['kind'], "{$data['for_month']} ৳{$amount}");
        return back()->with('ok', ucfirst($data['kind']).' for '.$data['for_month'].' recorded.');
    }

    public function reverse(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:company_entries,id'], 'reason' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($data, $request) {
            foreach (CompanyEntry::query()->whereIn('id', $data['ids'])->get() as $e) {
                if ($e->kind === 'reversal' || $e->reversedBy()->exists()) continue;
                CompanyEntry::create(['entry_date' => now()->timezone('Asia/Dhaka')->toDateString(), 'kind' => 'reversal', 'amount' => -$e->amount,
                    'challan_no' => $e->challan_no, 'note' => 'Reverses '.CompanyEntry::KINDS[$e->kind].' of '.$e->entry_date->format('d-m-Y').': '.$data['reason'],
                    'reversal_of' => $e->id, 'created_by' => $request->user()->id]);
            }
        });
        AuditLog::record('company_reversal', implode(',', $data['ids']).' '.$data['reason']);
        return back()->with('ok', 'Reversed. Both entries stay on record.');
    }

    public function rates()
    {
        return view('company.rates', ['rates' => CompanyRate::query()->orderByDesc('from_date')->orderBy('size_kg')->get(),
            'months' => CompanyMonth::query()->orderByDesc('month')->get()]);
    }

    public function saveRate(Request $request)
    {
        $data = $request->validate([
            'size_kg' => ['required', Rule::in(Brand::SIZES)], 'from_date' => ['required', 'date'],
            'do_rate' => ['required', 'numeric', 'min:0'], 'transport' => ['nullable', 'numeric', 'min:0'],
        ] + collect(array_keys(CompanyRate::PARTS))->mapWithKeys(fn ($p) => [$p => ['nullable', 'numeric', 'min:0']])->all());
        $data = array_map(fn ($v) => $v ?? 0, $data);
        $r = CompanyRate::query()->updateOrCreate(['size_kg' => $data['size_kg'], 'from_date' => $data['from_date']], $data);
        AuditLog::record('company_rate', "{$r->size_kg}kg from {$data['from_date']} DO {$r->do_rate}");
        return back()->with('ok', 'Rate saved.');
    }

    public function saveMonth(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'], 'kg_commission' => ['nullable', 'numeric', 'min:0'],
            'deadline_day' => ['required', 'integer', 'min:1', 'max:31'], 'ait_pct' => ['required', 'numeric', 'min:0', 'max:50'],
            'flags' => ['nullable', 'array'], 'note' => ['nullable', 'string', 'max:255'],
            'tons_target' => ['nullable', 'numeric', 'min:0'], 'excl_target' => ['nullable', 'numeric', 'min:0'],
        ]);
        $flags = [];
        foreach (array_keys(CompanyRate::PARTS) as $p) foreach (Brand::SIZES as $s) $flags[$p.'_'.$s] = ! empty($data['flags'][$p.'_'.$s]);
        CompanyMonth::query()->updateOrCreate(['month' => $data['month']], ['kg_commission' => $data['kg_commission'] ?? 0, 'deadline_day' => $data['deadline_day'],
            'ait_pct' => $data['ait_pct'], 'flags' => $flags, 'note' => $data['note'] ?? null,
            'tons_target' => $data['tons_target'] ?? 0, 'excl_target' => $data['excl_target'] ?? 0]);
        AuditLog::record('company_month', $data['month']);
        return back()->with('ok', 'Month rules saved for '.$data['month'].'.');
    }

    /** One-time import of the existing NK LPG ledger (JSON exported from the old ledger app). */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        if (CompanyEntry::query()->exists()) return back()->withErrors(['file' => 'The company ledger already has entries. Import works only on an empty ledger.']);
        $j = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($j) || ! isset($j['entries'], $j['rates'])) return back()->withErrors(['file' => 'This is not a ledger export file.']);
        if (! empty($j['entries']) && collect($j['entries'])->contains(fn ($e) => isset($e['depPaid']) || ($e['depKind'] ?? '') === 'comm')) {
            return redirect()->route('imports.index')->withErrors(['file' => 'This file has rows that need your decision. Use Import data to answer them first.']);
        }
        $n = \App\Support\LedgerImport::run($j, $request->user()->id, ['dep_paid' => 'bill_payment', 'comm' => 'incentive', 'opening' => 0, 'opening_date' => now()->toDateString()]);
        AuditLog::record('company_import', "{$n} entries");
        return redirect()->route('company.index')->with('ok', "{$n} ledger entries imported. Check the balance against your old ledger.");
    }
}
