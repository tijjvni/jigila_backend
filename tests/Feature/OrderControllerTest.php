<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_user_sees_only_own_orders(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Order::factory()->create(['user_id' => $user->id]);
        Order::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_sees_all_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create()->each(fn ($u) => Order::factory()->create(['user_id' => $u->id]));

        $response = $this->actingAs($admin)->getJson('/api/v1/orders');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_guest_cannot_list_orders(): void
    {
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_user_can_create_order(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/orders', [
            'vin'               => '1HGCM82633A004352',
            'auction_source'    => 'Copart',
            'condition'         => 'Run and Drive',
            'already_purchased' => false,
            'bid_price'         => '7500',
            'services'          => ['trucking'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.vin', '1HGCM82633A004352')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', ['vin' => '1HGCM82633A004352', 'user_id' => $user->id]);
    }

    public function test_create_order_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/orders', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vin', 'auction_source', 'condition', 'already_purchased']);
    }

    public function test_create_order_rejects_invalid_auction_source(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/orders', [
                'vin'               => '1HGCM82633A004352',
                'auction_source'    => 'InvalidSource',
                'condition'         => 'Run and Drive',
                'already_purchased' => false,
                'bid_price'         => '5000',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auction_source']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_user_can_view_own_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', (string) $order->id);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_view_any_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_user_can_update_own_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)
            ->putJson("/api/v1/orders/{$order->id}", ['status' => 'processing'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'processing');
    }

    public function test_user_cannot_update_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAs($user)
            ->putJson("/api/v1/orders/{$order->id}", ['status' => 'processing'])
            ->assertStatus(403);
    }

    public function test_update_rejects_invalid_status(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/orders/{$order->id}", ['status' => 'flying'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_user_can_delete_own_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Order deleted.']);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_user_cannot_delete_another_users_order(): void
    {
        $user  = User::factory()->create();
        $order = Order::factory()->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertStatus(403);
    }
}
