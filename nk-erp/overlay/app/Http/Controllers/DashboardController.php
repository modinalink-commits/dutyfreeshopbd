<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebsiteOrder;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'newOrders' => WebsiteOrder::query()->where('status', 'new')->count(),
            'todayOrders' => WebsiteOrder::query()->whereDate('created_at', today())->count(),
            'openOrders' => WebsiteOrder::query()->whereIn('status', ['new', 'called', 'confirmed'])->count(),
            'users' => User::query()->where('is_active', true)->count(),
            'latest' => WebsiteOrder::query()->latest()->limit(8)->get(),
        ]);
    }
}
