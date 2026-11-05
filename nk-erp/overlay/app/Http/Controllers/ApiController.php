<?php

namespace App\Http\Controllers;

use App\Models\WebsiteOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiController extends Controller
{
    public function websiteOrder(Request $request)
    {
        // Honeypot: real visitors never fill this hidden field.
        if ($request->filled('website')) {
            return response()->json(['ok' => true]);
        }

        if (\App\Models\Setting::get('site_orders_open', '1') === '0') {
            return response()->json(['ok' => false, 'message' => \App\Models\Setting::get('site_closed_msg') ?: 'Online orders are paused right now. Please call us.'], 423);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^(\+?88)?01[3-9][0-9\-\s]{8,10}$/'],
            'cylinder' => ['required', Rule::in(['12 kg', '35 kg', '45 kg'])],
            'type' => ['required', 'string', 'max:40'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
        ]);

        $order = WebsiteOrder::create($data + [
            'quantity' => $data['quantity'] ?? 1,
            'status' => 'new',
            'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'id' => $order->id]);
    }
}
