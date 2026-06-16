<?php

namespace Tests\Feature;

use App\Enums\DeparturePort;
use App\Enums\DestinationPort;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateOrderLocationTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function order(): Order
    {
        return Order::factory()->create();
    }

    // ─── Auth & access ────────────────────────────────────────────────────────

    public function test_guest_cannot_update_location(): void
    {
        $order = $this->order();

        $this->patchJson("/api/admin/orders/{$order->id}/location", [
            'pickup_location' => 'Dallas, TX',
        ])->assertStatus(401);
    }

    public function test_non_admin_cannot_update_location(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = $this->order();

        $this->actingAs($user)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'pickup_location' => 'Dallas, TX',
            ])->assertStatus(403);
    }

    // ─── Valid payloads ───────────────────────────────────────────────────────

    public function test_admin_can_set_pickup_location(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'pickup_location' => 'Dallas, TX',
            ])->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'              => $order->id,
            'pickup_location' => 'Dallas, TX',
        ]);
    }

    public function test_admin_can_set_valid_departure_port(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'departure_port' => DeparturePort::HoustonTX->value,
            ])->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'             => $order->id,
            'departure_port' => DeparturePort::HoustonTX->value,
        ]);
    }

    public function test_admin_can_set_valid_destination_port(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'destination_port' => DestinationPort::TinCanLagos->value,
            ])->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'destination_port' => DestinationPort::TinCanLagos->value,
        ]);
    }

    public function test_admin_can_set_all_location_fields_at_once(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'pickup_location'  => 'Atlanta, GA',
                'departure_port'   => DeparturePort::SavannahGA->value,
                'destination_port' => DestinationPort::LagosApapa->value,
            ])->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'pickup_location'  => 'Atlanta, GA',
            'departure_port'   => DeparturePort::SavannahGA->value,
            'destination_port' => DestinationPort::LagosApapa->value,
        ]);
    }

    public function test_all_departure_port_values_are_accepted(): void
    {
        $admin  = $this->adminUser();
        $ports  = DeparturePort::values();

        foreach ($ports as $port) {
            $order = $this->order();
            $this->actingAs($admin)
                ->patchJson("/api/admin/orders/{$order->id}/location", [
                    'departure_port' => $port,
                ])->assertStatus(200, "Expected 200 for departure_port={$port}");
        }
    }

    public function test_all_destination_port_values_are_accepted(): void
    {
        $admin = $this->adminUser();
        $ports = DestinationPort::values();

        foreach ($ports as $port) {
            $order = $this->order();
            $this->actingAs($admin)
                ->patchJson("/api/admin/orders/{$order->id}/location", [
                    'destination_port' => $port,
                ])->assertStatus(200, "Expected 200 for destination_port={$port}");
        }
    }

    // ─── Validation rejections ────────────────────────────────────────────────

    public function test_invalid_departure_port_is_rejected(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'departure_port' => 'new_orleans_la',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['departure_port']);
    }

    public function test_invalid_destination_port_is_rejected(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [
                'destination_port' => 'mombasa_kenya',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['destination_port']);
    }

    public function test_empty_payload_is_accepted(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        // All fields are nullable — empty body should return 200
        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/location", [])
            ->assertStatus(200);
    }

    public function test_unknown_order_returns_404(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/api/admin/orders/99999/location', [
                'pickup_location' => 'Somewhere',
            ])->assertStatus(404);
    }
}
