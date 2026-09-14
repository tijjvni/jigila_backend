<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\ShippingLine;
use App\Enums\ShippingType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateOrderShippingTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->create($attributes + ['status' => OrderStatus::AtPort->value]);
    }

    private function endpoint(Order $order): string
    {
        return "/api/v1/admin/orders/{$order->id}/shipping";
    }

    // ─── Auth & access ────────────────────────────────────────────────────────

    public function test_guest_cannot_update_shipping(): void
    {
        $order = $this->order();

        $this->patchJson($this->endpoint($order), ['vessel_name' => 'MV Grande Lagos'])
            ->assertStatus(401);
    }

    public function test_customer_cannot_update_shipping(): void
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = $this->order(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson($this->endpoint($order), ['vessel_name' => 'MV Grande Lagos'])
            ->assertStatus(403);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_admin_can_set_full_shipping_record(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), [
                'vessel_name'              => 'MV Grande Lagos',
                'container_number'         => 'MSKU1234567',
                'shipping_tracking_number' => 'TRK-99001',
                'shipping_line'            => ShippingLine::Grimaldi->value,
                'shipping_type'            => ShippingType::RoRo->value,
                'current_vessel_location'  => 'Mid-Atlantic',
                'port_received_at'         => '2026-07-01',
                'eta_start'                => '2026-07-12',
                'eta_end'                  => '2026-07-18',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.vessel_name', 'MV Grande Lagos')
            ->assertJsonPath('data.shipping_line', ShippingLine::Grimaldi->value)
            ->assertJsonPath('data.eta_start', '2026-07-12')
            ->assertJsonPath('data.eta_end', '2026-07-18');

        $this->assertDatabaseHas('orders', [
            'id'               => $order->id,
            'vessel_name'      => 'MV Grande Lagos',
            'container_number' => 'MSKU1234567',
        ]);
    }

    public function test_partial_update_does_not_clear_other_shipping_fields(): void
    {
        $admin = $this->adminUser();
        $order = $this->order([
            'vessel_name'      => 'MV Grande Lagos',
            'container_number' => 'MSKU1234567',
        ]);

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), ['current_vessel_location' => 'Approaching Lagos'])
            ->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id'                      => $order->id,
            'vessel_name'             => 'MV Grande Lagos',
            'container_number'        => 'MSKU1234567',
            'current_vessel_location' => 'Approaching Lagos',
        ]);
    }

    public function test_update_writes_an_audit_log_entry(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), ['vessel_name' => 'MV Sallaum Star'])
            ->assertStatus(200);

        $this->assertDatabaseHas('order_audit_logs', [
            'order_id' => $order->id,
            'user_id'  => $admin->id,
            'action'   => 'shipping_updated',
        ]);
    }

    public function test_update_notifies_the_customer(): void
    {
        $admin    = $this->adminUser();
        $customer = User::factory()->create(['role' => 'user']);
        $order    = $this->order(['user_id' => $customer->id]);

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), ['vessel_name' => 'MV Sallaum Star'])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->id,
            'type'    => 'shipping_updated',
        ]);
    }

    public function test_no_op_update_does_not_notify_or_log(): void
    {
        $admin    = $this->adminUser();
        $customer = User::factory()->create(['role' => 'user']);
        $order    = $this->order(['user_id' => $customer->id, 'vessel_name' => 'MV Grande Lagos']);

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), ['vessel_name' => 'MV Grande Lagos'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $customer->id,
            'type'    => 'shipping_updated',
        ]);
        $this->assertDatabaseMissing('order_audit_logs', [
            'order_id' => $order->id,
            'action'   => 'shipping_updated',
        ]);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_invalid_shipping_line_is_rejected(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), ['shipping_line' => 'hapag_lloyd'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_line']);
    }

    public function test_invalid_shipping_type_is_rejected(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), ['shipping_type' => 'air_freight'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shipping_type']);
    }

    public function test_eta_end_before_eta_start_is_rejected(): void
    {
        $admin = $this->adminUser();
        $order = $this->order();

        $this->actingAs($admin)
            ->patchJson($this->endpoint($order), [
                'eta_start' => '2026-07-18',
                'eta_end'   => '2026-07-12',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['eta_end']);
    }

    public function test_every_shipping_line_value_is_accepted(): void
    {
        $admin = $this->adminUser();

        foreach (ShippingLine::values() as $line) {
            $order = $this->order();
            $this->actingAs($admin)
                ->patchJson($this->endpoint($order), ['shipping_line' => $line])
                ->assertStatus(200, "Expected 200 for shipping_line={$line}");
        }
    }
}
