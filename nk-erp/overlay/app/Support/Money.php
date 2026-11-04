<?php

namespace App\Support;

use App\Models\AccountTransfer;
use App\Models\CompanyEntry;
use App\Models\CustomerLedger;
use App\Models\Expense;
use App\Models\MoneyAccount;
use App\Models\Sale;
use Carbon\Carbon;

class Money
{
    /**
     * Live balance of each money account: opening balance on its opening date, plus every posted
     * movement on or after that date. Nothing before the opening date is counted again.
     */
    public static function balances(?string $until = null): array
    {
        $tz = 'Asia/Dhaka';
        $until = $until ?? now()->timezone($tz)->toDateString();
        $accounts = MoneyAccount::query()->orderBy('id')->get();
        $out = [];
        foreach ($accounts as $a) $out[$a->id] = ['account' => $a, 'balance' => (float) $a->opening_balance, 'from' => ($a->opening_date ?? $a->created_at)->toDateString()];
        $pick = function (string $type) use ($accounts) { return $accounts->where('type', $type)->sortByDesc('is_default')->first()?->id; };
        $methodType = ['cash' => 'cash', 'bkash' => 'mobile', 'nagad' => 'mobile', 'bank' => 'bank'];
        $add = function (?int $id, string $date, float $amt) use (&$out, $until) {
            if (! $id || ! isset($out[$id]) || $date < $out[$id]['from'] || $date > $until) return;
            $out[$id]['balance'] += $amt;
        };
        $d = fn ($ts) => Carbon::parse($ts)->timezone($tz)->toDateString();

        foreach (Sale::query()->whereNull('customer_id')->get(['payment_method', 'paid', 'stage', 'created_at', 'voided_at']) as $s) {
            $id = $pick($methodType[$s->payment_method] ?? 'cash');
            $add($id, $d($s->created_at), (float) $s->paid);
            if ($s->stage === 'voided' && $s->voided_at) $add($id, $d($s->voided_at), -(float) $s->paid);
        }
        foreach (CustomerLedger::query()->whereIn('kind', ['payment', 'void'])->get(['kind', 'method', 'credit', 'debit', 'sale_id', 'created_at']) as $r) {
            if ($r->kind === 'payment') { $add($pick($methodType[$r->method] ?? 'cash'), $d($r->created_at), (float) $r->credit); continue; }
            $m = Sale::query()->whereKey($r->sale_id)->value('payment_method');
            $add($pick($methodType[$m] ?? 'cash'), $d($r->created_at), -(float) $r->debit);
        }
        $bank = $pick('bank');
        foreach (CompanyEntry::query()->where('kind', 'deposit')->get(['entry_date', 'amount']) as $e) $add($bank, $e->entry_date->toDateString(), -(float) $e->amount);
        foreach (Expense::query()->get(['account_id', 'expense_date', 'amount']) as $x) $add($x->account_id, $x->expense_date->toDateString(), -(float) $x->amount);
        foreach (AccountTransfer::query()->get() as $t) {
            $add($t->from_account_id, $t->transfer_date->toDateString(), -(float) $t->amount);
            $add($t->to_account_id, $t->transfer_date->toDateString(), (float) $t->amount);
        }
        return $out;
    }
}
