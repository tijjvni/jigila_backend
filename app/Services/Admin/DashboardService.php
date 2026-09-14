<?php

namespace App\Services\Admin;

use App\Enums\ServiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function stats(): array
    {
        $activeShipmentStatuses = ['processing', 'in_transit', 'at_port'];

        // Cache only scalar/array data — never Eloquent models, which can't be
        // safely serialized/unserialized by PHP's cache driver.
        $stats = Cache::remember('dashboard.stats', now()->addMinutes(5), function () use ($activeShipmentStatuses) {
            return [
                // Stat cards
                'total_users'           => User::where('role', 'user')->count(),
                'total_orders'          => Order::count(),
                'active_shipments'      => Order::whereIn('status', $activeShipmentStatuses)->count(),
                'total_revenue'         => (float) Order::whereNotNull('bid_price')->sum('bid_price'),

                // Chart: orders by status — toArray() gives a plain PHP array, not a Collection
                'orders_by_status'      => Order::selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),

                // Chart: orders by auction source (Copart / IAAI / Co-Parts)
                'orders_by_auction_source' => Order::selectRaw('auction_source, count(*) as count')
                    ->whereNotNull('auction_source')
                    ->groupBy('auction_source')
                    ->pluck('count', 'auction_source')
                    ->toArray(),

                // Chart: orders per month (current year)
                'orders_by_month'       => $this->ordersByMonth(),

                // Chart: revenue by service
                'revenue_by_service'    => $this->revenueByService(),

                // Metrics
                'order_completion_rate' => $this->completionRate(),
                'average_order_value'   => $this->averageOrderValue(),
            ];
        });

        // Fetch recent orders fresh every request — Eloquent models with loaded
        // relationships cannot be safely round-tripped through the cache serializer.
        $stats['recent_orders'] = Order::with(['user', 'invoice'])->latest()->limit(10)->get();

        // Money totals are deliberately outside the 5-minute cache: BUG-037
        // requires the figures to move as soon as a payment is recorded.
        $stats += $this->paymentTotals();

        return $stats;
    }

    /**
     * Platform-wide paid / outstanding totals (BUG-037).
     *
     * Derived from invoices rather than orders, so the numbers reflect money
     * actually billed and actually received. One grouped query rather than
     * three scalar sums.
     */
    private function paymentTotals(): array
    {
        $byStatus = Invoice::selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $paid        = (float) ($byStatus['paid'] ?? 0);
        $outstanding = (float) ($byStatus['pending'] ?? 0);

        return [
            'total_paid'        => round($paid, 2),
            'total_outstanding' => round($outstanding, 2),
            'total_invoiced'    => round($paid + $outstanding, 2),
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

    /**
     * Bid revenue split evenly across the services attached to each order.
     *
     * Aggregated in SQL rather than by streaming every order through PHP: the
     * old cursor-based version scaled linearly and took ~1.5 s at 20k orders,
     * which the 5-minute cache only masked until it expired.
     *
     * `services` is a short JSON array, so membership is tested with a LIKE on
     * the serialized text. That keeps one query working on both SQLite and
     * MySQL without depending on driver-specific JSON functions. The service
     * list comes from the enum, so adding a ServiceType needs no change here.
     */
    private function revenueByService(): array
    {
        $services = ServiceType::values();

        if ($services === []) {
            return [];
        }

        // Values originate from a PHP enum, never user input. Assert the shape
        // anyway — these are interpolated into SQL.
        foreach ($services as $service) {
            if (preg_match('/^[a-z_]+$/', $service) !== 1) {
                throw new \RuntimeException("Unsupported ServiceType value for aggregation: {$service}");
            }
        }

        $isMember = fn (string $s) => "(CASE WHEN services LIKE '%\"{$s}\"%' THEN 1 ELSE 0 END)";

        // How many of the known services this order carries — the divisor.
        $serviceCount = implode(' + ', array_map($isMember, $services));

        $selects = array_map(
            fn (string $s) => sprintf(
                'COALESCE(SUM(CASE WHEN %s = 1 THEN bid_price * 1.0 / (%s) ELSE 0 END), 0) AS agg_%s',
                $isMember($s),
                $serviceCount,
                $s,
            ),
            $services,
        );

        $row = Order::query()
            ->whereNotNull('services')
            ->whereNotNull('bid_price')
            // Guards the divisor: an order tagged with no known service is
            // excluded rather than dividing by zero.
            ->whereRaw("({$serviceCount}) > 0")
            ->selectRaw(implode(', ', $selects))
            ->first();

        $totals = [];
        foreach ($services as $service) {
            $totals[$service] = round((float) ($row->{"agg_{$service}"} ?? 0), 2);
        }

        return $totals;
    }

    private function completionRate(): float
    {
        $total = Order::count();
        if ($total === 0) {
            return 0.0;
        }

        $completed = Order::where('status', 'delivered')->count();

        return round(($completed / $total) * 100, 1);
    }

    private function averageOrderValue(): float
    {
        return round(
            (float) Order::whereNotNull('bid_price')->avg('bid_price'),
            2
        );
    }
}
