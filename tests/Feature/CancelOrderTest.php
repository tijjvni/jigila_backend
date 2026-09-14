<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelOrderTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Found a better vehicle elsewhere.';

    private function customerWithOrder(string $status = 'pending'): array
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => $status]);

        return [$user, $order];
    }

    // ─── Customer cancellation ────────────────────────────────────────────────

    public function test_customer_can_cancel_a_pending_order(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(200)
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
            ->assertJsonPath('data.cancellation_reason', self::REASON);

        $this->assertDatabaseHas('orders', [
            'id'           => $order->id,
            'status'       => OrderStatus::Cancelled->value,
            'cancelled_by' => $user->id,
        ]);
    }

    public function test_customer_cannot_cancel_after_an_operational_milestone(): void
    {
        [$user, $order] = $this->customerWithOrder(OrderStatus::InTransit->value);

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => OrderStatus::InTransit->value,
        ]);
    }

    public function test_customer_cannot_cancel_someone_elses_order(): void
    {
        $intruder = User::factory()->create(['role' => 'user']);
        [, $order] = $this->customerWithOrder();

        $this->actingAs($intruder)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(403);
    }

    public function test_guest_cannot_cancel(): void
    {
        [, $order] = $this->customerWithOrder();

        $this->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(401);
    }

    // ─── Admin cancellation ───────────────────────────────────────────────────

    public function test_admin_can_cancel_past_an_operational_milestone(): void
    {
        $admin     = User::factory()->create(['role' => 'admin']);
        [, $order] = $this->customerWithOrder(OrderStatus::OnVessel->value);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/cancel", ['reason' => 'Vessel booking fell through.'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);
    }

    public function test_non_admin_cannot_use_the_admin_cancel_route(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/admin/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(403);
    }

    // ─── Guards ───────────────────────────────────────────────────────────────

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        $admin     = User::factory()->create(['role' => 'admin']);
        [, $order] = $this->customerWithOrder(OrderStatus::Delivered->value);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(422);
    }

    public function test_cancelling_twice_is_rejected(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed my mind again.'])
            ->assertStatus(422);

        // The original reason must survive the second attempt.
        $this->assertDatabaseHas('orders', [
            'id'                  => $order->id,
            'cancellation_reason' => self::REASON,
        ]);
    }

    public function test_reason_is_required(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    // ─── Notifications & audit ────────────────────────────────────────────────

    public function test_customer_cancellation_notifies_admins_and_the_customer(): void
    {
        $admin          = User::factory()->create(['role' => 'admin']);
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', ['user_id' => $user->id,  'type' => 'order_cancelled']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'order_cancelled']);
    }

    public function test_cancellation_is_written_to_the_audit_log(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => self::REASON])
            ->assertStatus(200);

        $this->assertDatabaseHas('order_audit_logs', [
            'order_id' => $order->id,
            'user_id'  => $user->id,
            'action'   => 'order_cancelled',
        ]);
    }

    public function test_can_cancel_flag_reflects_the_policy(): void
    {
        [$user, $pending] = $this->customerWithOrder();

        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$pending->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.can_cancel', true);

        $shipped = Order::factory()->create([
            'user_id' => $user->id,
            'status'  => OrderStatus::OnVessel->value,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$shipped->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.can_cancel', false);
    }
}
