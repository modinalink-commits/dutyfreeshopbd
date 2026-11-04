<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WebsiteOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $orders = WebsiteOrder::query()
            ->with('handler')
            ->when($status && isset(WebsiteOrder::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = WebsiteOrder::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('orders.index', compact('orders', 'status', 'counts'));
    }

    public function update(Request $request, WebsiteOrder $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WebsiteOrder::STATUSES))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $old = $order->status;
        $order->fill($data + ['handled_by' => $request->user()->id])->save();
        AuditLog::record('order_status', "#{$order->id}: {$old} → {$order->status}");

        return back()->with('ok', "Order #{$order->id} updated.");
    }
}
