<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\CompanyEntry;
use App\Models\CompanyMonth;
use App\Models\CompanyRate;
use Carbon\Carbon;

class CompanyCalc
{
    /** Incentive and commission for one month, from challans and the month's rules. */
    public static function month(string $ym): array
    {
        $start = Carbon::parse($ym.'-01');
        $end = (clone $start)->endOfMonth();
        $m = CompanyMonth::find($ym) ?? new CompanyMonth(['month' => $ym, 'kg_commission' => 0, 'deadline_day' => 16, 'flags' => [], 'ait_pct' => 10]);
        $lines = CompanyEntry::query()->where('kind', 'challan')->whereDoesntHave('reversedBy')->whereBetween('entry_date', [$start->toDateString(), $end->toDateString()])
            ->whereDoesntHave('reversedBy')->get();
        $parts = []; $gross = 0; $kg = 0; $bySize = [];
        foreach (Brand::SIZES as $s) {
            $kg += (int) $lines->where('size_kg', $s)->where('ctype', 'package')->sum('qty') * $s; // packages count for kg commission, not refill incentives
            $all = $lines->where('size_kg', $s)->where('ctype', '!=', 'package');
            $qty = (int) $all->sum('qty');
            if (! $qty) continue;
            $early = (int) $all->filter(fn ($l) => $l->entry_date->day <= $m->deadline_day)->sum('qty');
            $rate = CompanyRate::forDate($s, $start->toDateString());
            $bySize[$s] = $qty; $kg += $qty * $s;
            if (! $rate) continue;
            foreach (CompanyRate::PARTS as $p => $label) {
                $per = (float) $rate->{$p};
                if ($per <= 0 || ! $m->earns($p, $s)) continue;
                $q = $p === 'deadline' ? $early : ($p === 'late' ? $qty - $early : $qty);
                if ($q <= 0) continue;
                $amt = round($per * $q, 2);
                $parts[] = ['label' => "{$label} · {$s} kg", 'calc' => number_format($q).' × ৳'.rtrim(rtrim(number_format($per, 2), '0'), '.'), 'amount' => $amt];
                $gross += $amt;
            }
        }
        $ait = round($gross * (float) $m->ait_pct / 100, 2);
        $comm = round($kg * (float) $m->kg_commission, 2);
        $received = CompanyEntry::query()->where('for_month', $ym)->whereIn('kind', ['incentive', 'commission'])->whereDoesntHave('reversedBy')->get();
        return [
            'month' => $m, 'parts' => $parts, 'gross' => $gross, 'ait' => $ait, 'net' => round($gross - $ait, 2),
            'kg' => $kg, 'bySize' => $bySize, 'commission' => $comm,
            'incentiveDate' => (clone $start)->addDays(40)->toDateString(), 'commissionDate' => (clone $start)->addDays(45)->toDateString(),
            'incentiveReceived' => $received->where('kind', 'incentive')->first(), 'commissionReceived' => $received->where('kind', 'commission')->first(),
        ];
    }
}
