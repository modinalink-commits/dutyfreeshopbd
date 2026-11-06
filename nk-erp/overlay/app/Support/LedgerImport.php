<?php

namespace App\Support;

use App\Models\CompanyEntry;
use App\Models\CompanyMonth;
use App\Models\CompanyRate;
use Illuminate\Support\Facades\DB;

/** Import of the old NK LPG ledger export into the company ledger. Nothing is guessed: open questions must be answered first. */
class LedgerImport
{
    public static function analyse(array $j): array
    {
        $e = collect($j['entries'] ?? []);
        $ch = $e->filter(fn ($x) => ! empty($x['qty']));
        $dep = $e->filter(fn ($x) => empty($x['qty']) && ((float) ($x['deposit'] ?? 0) || (float) ($x['depPaid'] ?? 0)));
        $bad = $e->filter(fn ($x) => empty($x['date']) || ! strtotime((string) $x['date']) || (! empty($x['qty']) && empty($x['kg'])));
        $depPaid = $dep->filter(fn ($x) => isset($x['depPaid']) && ($x['depKind'] ?? '') !== 'comm');
        $comm = $dep->filter(fn ($x) => ($x['depKind'] ?? '') === 'comm');
        $dups = 0;
        foreach ($ch as $x) {
            if (! empty($x['challan']) && CompanyEntry::query()->where('entry_date', $x['date'])->where('challan_no', $x['challan'])->where('size_kg', (int) $x['kg'])->exists()) $dups++;
        }
        foreach ($dep as $x) {
            $amt = (float) ($x['depPaid'] ?? 0) ?: (float) ($x['deposit'] ?? 0);
            if (($x['depKind'] ?? '') !== 'comm' && CompanyEntry::query()->where('kind', 'deposit')->whereDoesntHave('reversedBy')->where('entry_date', $x['date'])->where('amount', $amt)->exists()) $dups++;
        }
        $months = collect($j['months'] ?? []);
        $rates = collect($j['rates'] ?? []);
        $emptyRates = $rates->filter(fn ($r) => ! (float) ($r['target'] ?? 0) && ! (float) ($r['slab'] ?? 0) && ! (float) ($r['deadline'] ?? 0) && ! (float) ($r['late'] ?? 0) && ! (float) ($r['dd'] ?? 0))
            ->map(fn ($r) => substr((string) $r['from'], 0, 7))->unique()->values();
        $dates = $e->pluck('date')->filter()->sort()->values();
        $missingRate = $ch->filter(fn ($x) => ! self::rateFor($rates, (int) $x['kg'], $x['date']))->count();
        return ['rows' => $e->count(), 'challans' => $ch->count(), 'deposits' => $dep->count(), 'valid' => $e->count() - $bad->count() - $missingRate, 'bad' => $bad->count() + $missingRate,
            'from' => $dates->first(), 'to' => $dates->last(), 'depPaid' => $depPaid, 'comm' => $comm, 'dups' => $dups, 'emptyRates' => $emptyRates, 'months' => $months->keys(),
            'sample' => ['date' => $ch->first()['date'] ?? '', 'challan' => $ch->first()['challan'] ?? '', 'kg' => $ch->first()['kg'] ?? '', 'qty' => $ch->first()['qty'] ?? '', 'rate' => $ch->first()['rate'] ?? '',
                'deposit' => $dep->first()['deposit'] ?? '', 'depPaid' => $depPaid->first()['depPaid'] ?? '', 'comm' => $comm->first()['depPaid'] ?? ($comm->first()['deposit'] ?? ''), 'withdraw' => $e->first()['withdraw'] ?? 0]];
    }

    private static function rateFor($rates, int $kg, string $date)
    {
        return $rates->filter(fn ($r) => (int) $r['size'] === $kg && $r['from'] <= $date)->sortByDesc('from')->first();
    }

    /** @param array{dep_paid: string, comm: string, opening: float, opening_date: string} $o */
    public static function run(array $j, int $uid, array $o): int
    {
        $n = 0;
        DB::transaction(function () use ($j, $uid, $o, &$n) {
            foreach ($j['rates'] as $r) {
                CompanyRate::query()->updateOrCreate(['size_kg' => (int) $r['size'], 'from_date' => $r['from']], ['do_rate' => $r['do'], 'transport' => $r['transport'] ?? 0,
                    'target' => $r['target'] ?? 0, 'slab' => $r['slab'] ?? 0, 'exclusive' => $r['exclusive'] ?? 0, 'deadline' => $r['deadline'] ?? 0, 'late' => $r['late'] ?? 0, 'dd' => $r['dd'] ?? 0]);
            }
            foreach (($j['months'] ?? []) as $ym => $m) {
                $flags = [];
                foreach (($m['disc'] ?? []) as $k => $v) if (preg_match('/^[a-z]+_\d+$/', $k)) $flags[$k] = ($v['st'] ?? '') === 'yes';
                CompanyMonth::query()->updateOrCreate(['month' => $ym], ['kg_commission' => $m['kgComm'] ?? 0, 'deadline_day' => $m['last'] ?? 16, 'flags' => $flags, 'ait_pct' => 10, 'tons_target' => $m['tons'] ?? 0, 'excl_target' => $m['excl'] ?? 0]);
            }
            if ((float) $o['opening'] != 0) {
                CompanyEntry::create(['entry_date' => $o['opening_date'], 'kind' => 'opening', 'amount' => (float) $o['opening'], 'note' => 'Opening balance confirmed at import', 'created_by' => $uid]);
            }
            usort($j['entries'], fn ($a, $b) => [$a['date'], $a['createdAt'] ?? 0] <=> [$b['date'], $b['createdAt'] ?? 0]);
            foreach ($j['entries'] as $e) {
                if (! empty($e['qty'])) {
                    $rate = CompanyRate::forDate((int) $e['kg'], $e['date']);
                    if (! empty($e['challan']) && CompanyEntry::query()->where('entry_date', $e['date'])->where('challan_no', $e['challan'])->where('size_kg', (int) $e['kg'])->exists()) continue;
                    $ct = stripos((string) ($e['type'] ?? ''), 'pack') !== false && (float) $rate->pkg_rate > 0 ? 'package' : 'refill';
                    $do = $rate->doFor($ct);
                    CompanyEntry::create(['entry_date' => $e['date'], 'kind' => 'challan', 'ctype' => $ct, 'challan_no' => ($e['challan'] ?? '') ?: null, 'size_kg' => (int) $e['kg'], 'qty' => (int) $e['qty'],
                        'do_rate' => $do, 'transport' => $rate->transport, 'amount' => -round($e['qty'] * ($do - (float) $rate->transport), 2), 'note' => ($e['note'] ?? '') ?: null, 'created_by' => $uid]);
                } else {
                    $amt = (float) ($e['depPaid'] ?? 0) ?: (float) ($e['deposit'] ?? 0);
                    if (! $amt) continue;
                    if (($e['depKind'] ?? '') === 'comm') {
                        // Arrives 40 days after the 1st of the month it was earned (incentive) or 45 days (commission).
                        $kind = $o['comm'] === 'commission' ? 'commission' : 'incentive';
                        $ym = date('Y-m', strtotime($e['date'].($kind === 'commission' ? ' -45 days' : ' -40 days')));
                        CompanyEntry::create(['entry_date' => $e['date'], 'kind' => $kind, 'for_month' => $ym, 'amount' => $amt,
                            'gross' => $kind === 'incentive' ? round($amt / 0.9, 2) : null, 'ait' => $kind === 'incentive' ? round($amt / 0.9 - $amt, 2) : null,
                            'source' => $e['source'] ?? null, 'note' => 'Imported. Old ledger label: '.($e['challan'] ?? ''), 'created_by' => $uid]);
                    } else {
                        $paid = isset($e['depPaid']);
                        // Same deposit already in the ledger (earlier import or typed in by hand): skip, never count it twice.
                        if (CompanyEntry::query()->where('kind', 'deposit')->whereDoesntHave('reversedBy')->where('entry_date', $e['date'])->where('amount', $amt)
                            ->when(($e['challan'] ?? '') !== '', fn ($q) => $q->where(fn ($w) => $w->where('challan_no', $e['challan'])->orWhereNull('challan_no')))->exists()) continue;
                        CompanyEntry::create(['entry_date' => $e['date'], 'kind' => 'deposit', 'deposit_kind' => $paid ? $o['dep_paid'] : 'bank',
                            'source' => $e['source'] ?? ($paid ? 'Deposit paid' : null), 'amount' => $amt, 'challan_no' => ($e['challan'] ?? '') ?: null,
                            'note' => $paid ? 'Imported "Deposit paid" as '.\App\Models\CompanyEntry::DEPOSIT_KINDS[$o['dep_paid']] : null, 'created_by' => $uid]);
                    }
                }
                $n++;
            }
        });
        return $n;
    }

    /** Month-by-month balance check from what is in the ledger now. */
    public static function monthly(): array
    {
        $rows = [];
        $first = CompanyEntry::query()->min('entry_date');
        if (! $first) return [];
        $m = \Carbon\Carbon::parse($first)->startOfMonth();
        $now = now()->timezone('Asia/Dhaka');
        while ($m->lte($now)) {
            $a = $m->toDateString(); $b = $m->copy()->endOfMonth()->toDateString();
            $q = fn () => CompanyEntry::query()->whereBetween('entry_date', [$a, $b]);
            $opening = (float) CompanyEntry::query()->where('entry_date', '<', $a)->sum('amount') + (float) $q()->where('kind', 'opening')->sum('amount');
            $ch = $q()->where('kind', 'challan')->whereDoesntHave('reversedBy')->get();
            $rows[] = ['m' => $m->format('M Y'), 'opening' => $opening, 'hasOpening' => CompanyEntry::query()->where('kind', 'opening')->exists(),
                'deposits' => (float) $q()->where('kind', 'deposit')->whereDoesntHave('reversedBy')->sum('amount'), 'credits' => (float) $q()->whereIn('kind', ['incentive', 'commission'])->sum('amount'),
                'bill' => -(float) $ch->sum('amount'), 'tons' => $ch->sum(fn ($c) => $c->qty * $c->size_kg) / 1000, 'close' => (float) CompanyEntry::query()->where('entry_date', '<=', $b)->sum('amount')];
            $m->addMonth();
        }
        return $rows;
    }
}
