<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
    }

    public function test_list_returns_only_users_own_orders(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create();

        Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $other->id]);

        $orders = $this->service->list($user);

        $this->assertCount(1, $orders);
        $this->assertEquals($user->id, $orders->first()->user_id);
    }

    public function test_list_returns_all_orders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Order::factory()->count(3)->create();

        $orders = $this->service->list($admin);

        $this->assertCount(3, $orders);
    }

    public function test_create_makes_order_belonging_to_user(): void
    {
        $user = User::factory()->create();

        $order = $this->service->create($user, [
            'vin'               => '1HGCM82633A004352',
            'auction_source'    => 'Copart',
            'condition'         => 'Run and Drive',
            'already_purchased' => false,
            'bid_price'         => '5000',
            'services'          => ['trucking'],
        ]);

        $this->assertEquals($user->id, $order->user_id);
        $this->assertEquals('pending', $order->status->value);
        $this->assertDatabaseHas('orders', ['vin' => '1HGCM82633A004352']);
    }

    public function test_find_eager_loads_user_relation(): void
    {
        $order = Order::factory()->create();

        $found = $this->service->find($order);

        $this->assertTrue($found->relationLoaded('user'));
    }

    public function test_update_modifies_fillable_order_fields(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $updated = $this->service->update($order, ['stock_id' => 'STK-999']);

        $this->assertEquals('STK-999', $updated->stock_id);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'stock_id' => 'STK-999']);
    }

    public function test_update_cannot_change_guarded_status_field(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $updated = $this->service->update($order, ['status' => 'delivered']);

        $this->assertEquals('pending', $updated->status->value);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_delete_removes_order_from_database(): void
    {
        $order = Order::factory()->create();

        $this->service->delete($order);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_authorize_aborts_when_user_does_not_own_order(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create();

        $this->expectException(HttpException::class);

        $this->service->authorize($user, $order);
    }

    public function test_authorize_passes_for_order_owner(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->service->authorize($user, $order);

        $this->assertTrue(true);
    }

    public function test_authorize_passes_for_admin_regardless_of_ownership(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create();

        $this->service->authorize($admin, $order);

        $this->assertTrue(true);
    }
}
