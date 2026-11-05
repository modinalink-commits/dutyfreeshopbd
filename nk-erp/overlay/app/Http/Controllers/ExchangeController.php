<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\BrandExchange;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class ExchangeController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'from_brand_id' => ['required', 'exists:brands,id', 'different:to_brand_id'],
            'to_brand_id' => ['required', 'exists:brands,id'],
            'size_kg' => ['required', Rule::in(Brand::SIZES)],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'party' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $n = BrandExchange::query()->whereDate('created_at', today())->count() + 1;
        $x = BrandExchange::create($data + [
            'ref_no' => 'BX-'.now()->timezone('Asia/Dhaka')->format('dm').'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'-'.random_int(10, 99),
            'status' => 'waiting',
            'requested_by' => $request->user()->id,
        ]);
        AuditLog::record('exchange_requested', $x->ref_no);

        return back()->with('ok', "{$x->ref_no} sent for approval.");
    }

    public function decide(Request $request, BrandExchange $exchange)
    {
        $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        if ($exchange->status !== 'waiting') {
            return back()->withErrors(['decision' => 'This exchange is already decided.']);
        }
        if ($exchange->requested_by === $request->user()->id && $request->user()->role !== 'super_admin') {
            return back()->withErrors(['decision' => 'Someone other than the requester must approve this exchange.']);
        }

        if ($request->input('decision') === 'reject') {
            $exchange->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            AuditLog::record('exchange_rejected', $exchange->ref_no);
            return back()->with('ok', "{$exchange->ref_no} rejected.");
        }

        try {
            DB::transaction(function () use ($exchange, $request) {
                $note = 'Exchange '.$exchange->ref_no.($exchange->party ? ' · '.$exchange->party : '');
                StockMovement::post(['kind' => 'exchange', 'brand_id' => $exchange->from_brand_id, 'size_kg' => $exchange->size_kg,
                    'empty_delta' => -$exchange->qty, 'reference' => $exchange->ref_no, 'note' => $note]);
                StockMovement::post(['kind' => 'exchange', 'brand_id' => $exchange->to_brand_id, 'size_kg' => $exchange->size_kg,
                    'empty_delta' => $exchange->qty, 'reference' => $exchange->ref_no, 'note' => $note]);
                $exchange->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            });
        } catch (RuntimeException $ex) {
            return back()->withErrors(['decision' => \App\Support\Ui::msg($ex)]);
        }
        AuditLog::record('exchange_approved', $exchange->ref_no);

        return back()->with('ok', "{$exchange->ref_no} approved and posted to stock.");
    }
}
