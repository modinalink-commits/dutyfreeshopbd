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
        return view('sales.print', ['sale' => $sale->load(['items', 'customer', 'user']), 'size' => $size, 's' => \App\Models\Setting::query()->pluck('value', 'key')]);
    }

    public function void(Request $request, Sale $sale)
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        if ($sale->stage === 'voided') return back()->withErrors(['reason' => 'Already voided.']);
        try {
            SalesService::void($sale, $request->input('reason'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }
        AuditLog::record('sale_void', "{$sale->invoice_no}: {$request->input('reason')}");
        return back()->with('ok', "{$sale->invoice_no} voided. Stock, empties and the customer account were reversed. Refund any money taken.");
    }
}
