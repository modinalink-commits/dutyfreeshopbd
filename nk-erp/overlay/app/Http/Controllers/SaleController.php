<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Sale;
use App\Models\Setting;
use App\Support\SalesService;
use Illuminate\Http\Request;
use RuntimeException;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $day = $request->query('day', now()->timezone('Asia/Dhaka')->toDateString());
        $from = \Carbon\Carbon::parse($day, 'Asia/Dhaka')->startOfDay()->utc();
        $sales = Sale::query()->with(['customer', 'user'])->where('type', $request->query('type', 'retail'))
            ->whereBetween('created_at', [$from, (clone $from)->addDay()])->latest('id')->get();
        return view('sales.index', ['sales' => $sales, 'day' => $day, 'type' => $request->query('type', 'retail')]);
    }

    public function show(Sale $sale)
    {
        return view('sales.show', ['sale' => $sale->load(['items', 'customer', 'user', 'emptyBrand'])]);
    }

    public function print(Request $request, Sale $sale)
    {
        $size = in_array($request->query('size'), ['a4', '80', '58'], true) ? $request->query('size') : 'a4';
        \Illuminate\Support\Facades\DB::table('sales')->where('id', $sale->id)->increment($size === 'a4' ? 'a4_prints' : 'pos_prints');
        return view('sales.print', ['sale' => $sale->load(['items', 'customer', 'user']), 'size' => $size, 's' => collect($sale->company ?? \App\Models\Setting::snapshot())]);
    }

    public function void(Request $request, Sale $sale)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if ($sale->stage === 'voided') return back()->withErrors(['reason' => 'Already voided.']);
        if ($sale->trip && $sale->trip->status !== 'dispatched') return back()->withErrors(['reason' => "{$sale->trip->trip_no} is back from the market. Fix this sale with an adjustment in Daily closing."]);
        try {
            SalesService::void($sale, $request->input('reason'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => \App\Support\Ui::msg($e)]);
        }
        AuditLog::record('sale_void', "{$sale->invoice_no}: {$request->input('reason')}");
        return back()->with('ok', "{$sale->invoice_no} voided. Stock, empties and the customer account were reversed. Refund any money taken.");
    }
}
