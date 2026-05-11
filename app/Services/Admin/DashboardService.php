<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    public function stats(): array
    {
        return [
            'total_users'      => User::where('role', 'user')->count(),
            'total_orders'     => Order::count(),
            'orders_by_status' => Order::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'recent_orders'    => Order::with('user')->latest()->limit(10)->get(),
        ];
    }
}
