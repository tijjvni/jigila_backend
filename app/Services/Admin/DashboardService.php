<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function stats(): array
    {
        $activeShipmentStatuses = ['processing', 'in_transit', 'at_port'];

        return [
            // Stat cards
            'total_users'        => User::where('role', 'user')->count(),
            'total_orders'       => Order::count(),
            'active_shipments'   => Order::whereIn('status', $activeShipmentStatuses)->count(),
            'total_revenue'      => (float) Order::whereNotNull('bid_price')->sum(DB::raw('CAST(bid_price AS REAL)')),

            // Chart: orders by status
            'orders_by_status'   => Order::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),

            // Chart: orders per month (current year)
            'orders_by_month'    => $this->ordersByMonth(),

            // Chart: revenue by service
            'revenue_by_service' => $this->revenueByService(),

            // Metrics
            'order_completion_rate' => $this->completionRate(),
            'average_order_value'   => $this->averageOrderValue(),

            // Table
            'recent_orders'      => Order::with('user')->latest()->limit(10)->get(),
        ];
    }

    private function ordersByMonth(): array
    {
        $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => 0]);

        $driver     = DB::connection()->getDriverName();
        $monthExpr  = match ($driver) {
            'pgsql'              => 'EXTRACT(MONTH FROM created_at)::int',
            'sqlite'             => "CAST(strftime('%m', created_at) AS INTEGER)",
            'mysql', 'mariadb'   => 'MONTH(created_at)',
            default              => throw new \RuntimeException("Unsupported driver for ordersByMonth(): {$driver}"),
        };

        $data = Order::selectRaw("{$monthExpr} as month, count(*) as count")
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')
            ->pluck('count', 'month');

        return $months->map(fn ($_, $m) => (int) ($data[$m] ?? 0))
            ->values()
            ->toArray();
    }

    private function revenueByService(): array
    {
        $totals = ['trucking' => 0.0, 'shipping' => 0.0];

        // Only fetch the two columns needed; cursor() streams rows one at a time
        Order::whereNotNull('services')
            ->whereNotNull('bid_price')
            ->select(['bid_price', 'services'])
            ->cursor()
            ->each(function ($order) use (&$totals) {
                $services = is_array($order->services)
                    ? $order->services
                    : json_decode($order->services, true) ?? [];

                $count = count($services);
                if ($count === 0) return;

                $split = (float) $order->bid_price / $count;
                foreach ($services as $service) {
                    if (array_key_exists($service, $totals)) {
                        $totals[$service] += $split;
                    }
                }
            });

        return $totals;
    }

    private function completionRate(): float
    {
        $total = Order::count();
        if ($total === 0) return 0.0;

        $completed = Order::where('status', 'delivered')->count();

        return round(($completed / $total) * 100, 1);
    }

    private function averageOrderValue(): float
    {
        return round(
            (float) Order::whereNotNull('bid_price')->avg(DB::raw('CAST(bid_price AS REAL)')),
            2
        );
    }
}
