<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Admin\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * `revenueByService()` was moved from a PHP cursor to SQL aggregation. These
 * pin the behaviour that rewrite has to preserve, including the edge cases the
 * old implementation handled implicitly.
 */
class RevenueByServiceCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private function revenue(): array
    {
        $service = app(DashboardService::class);
        $method  = (new ReflectionClass($service))->getMethod('revenueByService');
        $method->setAccessible(true);

        return $method->invoke($service);
    }

    private function order(array $attributes): Order
    {
        return Order::factory()->create($attributes + [
            'user_id' => User::factory()->create(['role' => 'user'])->id,
        ]);
    }

    public function test_single_service_order_attributes_full_bid_price(): void
    {
        $this->order(['bid_price' => 1000, 'services' => ['trucking']]);

        $this->assertSame(1000.0, $this->revenue()['trucking']);
        $this->assertSame(0.0, $this->revenue()['shipping']);
    }

    public function test_two_service_order_splits_evenly(): void
    {
        $this->order(['bid_price' => 1000, 'services' => ['trucking', 'shipping']]);

        $revenue = $this->revenue();
        $this->assertSame(500.0, $revenue['trucking']);
        $this->assertSame(500.0, $revenue['shipping']);
    }

    public function test_totals_accumulate_across_orders(): void
    {
        $this->order(['bid_price' => 1000, 'services' => ['trucking', 'shipping']]);
        $this->order(['bid_price' => 600,  'services' => ['trucking']]);
        $this->order(['bid_price' => 200,  'services' => ['shipping']]);

        $revenue = $this->revenue();
        $this->assertSame(1100.0, $revenue['trucking']); // 500 + 600
        $this->assertSame(700.0, $revenue['shipping']);  // 500 + 200
    }

    public function test_orders_without_a_bid_price_are_ignored(): void
    {
        $this->order(['bid_price' => null, 'services' => ['trucking', 'shipping']]);

        $this->assertSame(0.0, $this->revenue()['trucking']);
    }

    public function test_orders_with_no_services_are_ignored(): void
    {
        $this->order(['bid_price' => 5000, 'services' => null]);

        $this->assertSame(0.0, $this->revenue()['trucking']);
        $this->assertSame(0.0, $this->revenue()['shipping']);
    }

    /** An empty array must not divide by zero. */
    public function test_order_with_an_empty_services_array_is_ignored(): void
    {
        $this->order(['bid_price' => 5000, 'services' => []]);

        $revenue = $this->revenue();
        $this->assertSame(0.0, $revenue['trucking']);
        $this->assertSame(0.0, $revenue['shipping']);
    }

    /** An unrecognised service must not divide the price into a void. */
    public function test_unknown_service_values_are_ignored(): void
    {
        $this->order(['bid_price' => 5000, 'services' => ['warehousing']]);

        $revenue = $this->revenue();
        $this->assertSame(0.0, $revenue['trucking']);
        $this->assertSame(0.0, $revenue['shipping']);
    }

    public function test_every_known_service_is_present_in_the_result(): void
    {
        $this->assertSame(['trucking', 'shipping'], array_keys($this->revenue()));
    }

    public function test_empty_table_returns_zeroes_not_nulls(): void
    {
        $revenue = $this->revenue();

        $this->assertSame(0.0, $revenue['trucking']);
        $this->assertSame(0.0, $revenue['shipping']);
    }
}
