<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-095 — the customer tracking timeline date-stamps each point it has
 * reached. `OrderResource.status_timestamps` derives those dates from the
 * status-change audit trail rather than from new columns.
 */
class OrderStatusTimestampsTest extends TestCase
{
    use RefreshDatabase;

    private function customerAndOrder(): array
    {
        $customer = User::factory()->create(['role' => 'user']);
        $order    = Order::factory()->create([
            'user_id' => $customer->id,
            'status'  => OrderStatus::Pending->value,
        ]);

        return [$customer, $order];
    }

    private function advance(Order $order, OrderStatus $status, string $at): void
    {
        $order->update(['status' => $status->value]);

        $log = OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => null,
            'action'     => 'status_changed',
            'old_values' => [],
            'new_values' => ['status' => $status->value],
        ]);

        // `created_at` is not fillable and Eloquent stamps it on save, so the
        // backdate has to go through the query builder.
        OrderAuditLog::whereKey($log->id)->update(['created_at' => $at]);
    }

    private function timestamps(User $customer, Order $order): array
    {
        return $this->actingAs($customer)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(200)
            ->json('data.status_timestamps');
    }

    public function test_pending_falls_back_to_the_order_creation_date(): void
    {
        [$customer, $order] = $this->customerAndOrder();

        // The opening status is never written as a transition, so there is no
        // audit row to read it from.
        $stamps = $this->timestamps($customer, $order);

        $this->assertArrayHasKey('pending', $stamps);
        $this->assertSame(
            $order->created_at->toIso8601String(),
            $stamps['pending'],
        );
    }

    public function test_each_reached_status_is_dated(): void
    {
        [$customer, $order] = $this->customerAndOrder();

        $this->advance($order, OrderStatus::Processing, '2026-07-01 09:00:00');
        $this->advance($order, OrderStatus::Pickup, '2026-07-04 14:30:00');
        $this->advance($order, OrderStatus::InTransit, '2026-07-06 08:15:00');

        $stamps = $this->timestamps($customer, $order);

        $this->assertSame('2026-07-01T09:00:00+00:00', $stamps['processing']);
        $this->assertSame('2026-07-04T14:30:00+00:00', $stamps['pickup']);
        $this->assertSame('2026-07-06T08:15:00+00:00', $stamps['in_transit']);
    }

    public function test_statuses_not_yet_reached_are_absent(): void
    {
        [$customer, $order] = $this->customerAndOrder();

        $this->advance($order, OrderStatus::Processing, '2026-07-01 09:00:00');

        $stamps = $this->timestamps($customer, $order);

        // A pending point must never carry a date, or the timeline reads as
        // already complete.
        $this->assertArrayNotHasKey('at_port', $stamps);
        $this->assertArrayNotHasKey('on_vessel', $stamps);
        $this->assertArrayNotHasKey('delivered', $stamps);
    }

    public function test_a_re_entered_status_keeps_the_date_it_was_first_reached(): void
    {
        [$customer, $order] = $this->customerAndOrder();

        $this->advance($order, OrderStatus::AtPort, '2026-07-10 10:00:00');
        $this->advance($order, OrderStatus::OnVessel, '2026-07-12 10:00:00');
        // An admin correcting a mis-click sends the order back and forward again.
        $this->advance($order, OrderStatus::AtPort, '2026-07-13 10:00:00');

        $stamps = $this->timestamps($customer, $order);

        $this->assertSame('2026-07-10T10:00:00+00:00', $stamps['at_port']);
    }

    public function test_timestamps_are_derived_in_chronological_order(): void
    {
        [$customer, $order] = $this->customerAndOrder();

        // Written out of order to prove the resource sorts before folding.
        $this->advance($order, OrderStatus::Delivered, '2026-07-20 10:00:00');
        $this->advance($order, OrderStatus::AtPort, '2026-07-10 10:00:00');

        $stamps = $this->timestamps($customer, $order);

        $this->assertSame('2026-07-10T10:00:00+00:00', $stamps['at_port']);
        $this->assertSame('2026-07-20T10:00:00+00:00', $stamps['delivered']);
    }

    public function test_another_customer_cannot_read_the_timeline(): void
    {
        [, $order]  = $this->customerAndOrder();
        $intruder   = User::factory()->create(['role' => 'user']);

        $this->actingAs($intruder)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(403);
    }
}
