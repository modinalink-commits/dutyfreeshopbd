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

        return view('company.index', [
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
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        try {
            DB::transaction(function () use ($data, $request) {
                $any = false;
                foreach (Brand::SIZES as $s) {
                    $q = (int) ($data['qty'][$s] ?? 0);
                    if (! $q) continue;
                    $rate = CompanyRate::forDate($s, $data['entry_date']);
                    if (! $rate) throw new RuntimeException("No Rate Master row for {$s} kg on {$data['entry_date']}. Add it first.");
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
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
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
        $uid = $request->user()->id; $n = 0;
        DB::transaction(function () use ($j, $uid, &$n) {
            foreach ($j['rates'] as $r) {
                CompanyRate::query()->updateOrCreate(['size_kg' => (int) $r['size'], 'from_date' => $r['from']], ['do_rate' => $r['do'], 'transport' => $r['transport'] ?? 0,
                    'target' => $r['target'] ?? 0, 'slab' => $r['slab'] ?? 0, 'exclusive' => $r['exclusive'] ?? 0, 'deadline' => $r['deadline'] ?? 0, 'late' => $r['late'] ?? 0, 'dd' => $r['dd'] ?? 0]);
            }
            foreach (($j['months'] ?? []) as $ym => $m) {
                $flags = [];
                foreach (($m['disc'] ?? []) as $k => $v) if (preg_match('/^[a-z]+_\d+$/', $k)) $flags[$k] = ($v['st'] ?? '') === 'yes';
                CompanyMonth::query()->updateOrCreate(['month' => $ym], ['kg_commission' => $m['kgComm'] ?? 0, 'deadline_day' => $m['last'] ?? 16, 'flags' => $flags, 'ait_pct' => 10, 'tons_target' => $m['tons'] ?? 0, 'excl_target' => $m['excl'] ?? 0]);
            }
            usort($j['entries'], fn ($a, $b) => [$a['date'], $a['createdAt'] ?? 0] <=> [$b['date'], $b['createdAt'] ?? 0]);
            foreach ($j['entries'] as $e) {
                if (! empty($e['qty'])) {
                    $rate = CompanyRate::forDate((int) $e['kg'], $e['date']);
                    CompanyEntry::create(['entry_date' => $e['date'], 'kind' => 'challan', 'challan_no' => ($e['challan'] ?? '') ?: null, 'size_kg' => (int) $e['kg'], 'qty' => (int) $e['qty'],
                        'do_rate' => $rate->do_rate, 'transport' => $rate->transport, 'amount' => -round($e['qty'] * ((float) $rate->do_rate - (float) $rate->transport), 2), 'note' => ($e['note'] ?? '') ?: null, 'created_by' => $uid]);
                } else {
                    $amt = (float) ($e['depPaid'] ?? 0) ?: (float) ($e['deposit'] ?? 0);
                    if (! $amt) continue;
                    if (($e['depKind'] ?? '') === 'comm') {
                        // August 2026: 4,83,309 = 5,37,010 incentive less 10% AIT (arrived 40 days after the 1st).
                        $ym = date('Y-m', strtotime($e['date'].' -40 days'));
                        CompanyEntry::create(['entry_date' => $e['date'], 'kind' => 'incentive', 'for_month' => $ym, 'amount' => $amt, 'gross' => round($amt / 0.9, 2), 'ait' => round($amt / 0.9 - $amt, 2),
                            'source' => $e['source'] ?? null, 'note' => 'Imported. Old ledger label: '.($e['challan'] ?? ''), 'created_by' => $uid]);
                    } else {
                        CompanyEntry::create(['entry_date' => $e['date'], 'kind' => 'deposit', 'deposit_kind' => isset($e['depPaid']) ? 'bill_payment' : 'bank',
                            'source' => $e['source'] ?? (isset($e['depPaid']) ? 'Deposit paid' : null), 'amount' => $amt, 'challan_no' => ($e['challan'] ?? '') ?: null,
                            'note' => isset($e['depPaid']) ? 'Imported as "Deposit paid" — kind to be confirmed' : null, 'created_by' => $uid]);
                    }
                }
                $n++;
            }
        });
        AuditLog::record('company_import', "{$n} entries");
        return redirect()->route('company.index')->with('ok', "{$n} ledger entries imported. Check the balance against your old ledger.");
    }
}
