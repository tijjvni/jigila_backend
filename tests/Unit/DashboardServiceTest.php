<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Services\Admin\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardService();
    }

    public function test_stats_returns_correct_user_count(): void
    {
        User::factory()->create(['role' => 'admin']);
        User::factory()->count(4)->create(['role' => 'user']);

        $stats = $this->service->stats();

        $this->assertEquals(4, $stats['total_users']);
    }

    public function test_stats_returns_correct_order_count(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(7)->create(['user_id' => $user->id]);

        $stats = $this->service->stats();

        $this->assertEquals(7, $stats['total_orders']);
    }

    public function test_stats_groups_orders_by_status(): void
    {
        Order::factory()->count(3)->create(['status' => 'pending']);
        Order::factory()->count(2)->create(['status' => 'processing']);

        $stats = $this->service->stats();

        $this->assertEquals(3, $stats['orders_by_status']['pending']);
        $this->assertEquals(2, $stats['orders_by_status']['processing']);
    }

    public function test_stats_recent_orders_capped_at_ten(): void
    {
        Order::factory()->count(15)->create();

        $stats = $this->service->stats();

        $this->assertCount(10, $stats['recent_orders']);
    }

    public function test_stats_recent_orders_have_user_relation_loaded(): void
    {
        Order::factory()->count(2)->create();

        $stats = $this->service->stats();

        foreach ($stats['recent_orders'] as $order) {
            $this->assertTrue($order->relationLoaded('user'));
        }
    }
}
