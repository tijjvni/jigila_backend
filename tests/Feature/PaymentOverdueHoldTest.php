<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deadline expiry, the shipment hold it places, and getting back out of it
 * (spec 4).
 */
class PaymentOverdueHoldTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: Invoice}
     */
    private function overdueOrder(string $stage = 'processing', int $daysLate = 1): array
    {
        $user  = $this->createUser();
        $order = $this->createOrder($user, ['status' => $stage]);

        $invoice = Invoice::factory()->overdue($daysLate)->create([
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'status'   => 'pending',
            'amount'   => 1000,
        ]);

        return [$order, $invoice];
    }

    // ─── Expiry ──────────────────────────────────────────────────────────────

    public function test_a_lapsed_deadline_marks_the_invoice_overdue_and_holds_the_shipment(): void
    {
        [$order, $invoice] = $this->overdueOrder();

        $this->artisan('jigila:process-payment-deadlines')->assertExitCode(0);

        $this->assertNotNull($invoice->fresh()->overdue_at);

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentOverdue, $order->status);
        $this->assertTrue($order->shipment_hold);
        $this->assertSame('processing', $order->status_before_hold);
    }

    /**
     * The status must survive a round trip through the database — SQLite keeps
     * the enum as a CHECK constraint, so a missed migration fails here rather
     * than silently in production on MySQL.
     */
    public function test_payment_overdue_persists_through_the_database(): void
    {
        [$order] = $this->overdueOrder();

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertDatabaseHas('orders', [
            'id'            => $order->id,
            'status'        => 'payment_overdue',
            'shipment_hold' => true,
        ]);
        $this->assertSame(OrderStatus::PaymentOverdue, Order::find($order->id)->status);
    }

    public function test_an_invoice_inside_its_deadline_is_untouched(): void
    {
        $user    = $this->createUser();
        $order   = $this->createOrder($user, ['status' => 'processing']);
        $invoice = Invoice::factory()->dueIn(10)->create([
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'status'   => 'pending',
        ]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertNull($invoice->fresh()->overdue_at);
        $this->assertSame(OrderStatus::Processing, $order->fresh()->status);
    }

    public function test_the_sweep_is_idempotent(): void
    {
        [$order, $invoice] = $this->overdueOrder();

        $this->artisan('jigila:process-payment-deadlines');
        $firstStamp = $invoice->fresh()->overdue_at;

        $this->travel(2)->hours();
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertEquals($firstStamp, $invoice->fresh()->overdue_at);
        $this->assertSame('processing', $order->fresh()->status_before_hold);
    }

    /**
     * A second lapsed invoice on the same order must not overwrite the stashed
     * stage with `payment_overdue` — that would lose the real stage for good.
     */
    public function test_a_second_overdue_invoice_does_not_clobber_the_stashed_stage(): void
    {
        [$order, $first] = $this->overdueOrder('at_port');

        Invoice::factory()->overdue(1)->create([
            'user_id'  => $order->user_id,
            'order_id' => $order->id,
            'status'   => 'pending',
        ]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('at_port', $order->fresh()->status_before_hold);
    }

    public function test_a_cancelled_order_is_not_put_on_hold(): void
    {
        [$order] = $this->overdueOrder('cancelled');

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertFalse($order->fresh()->shipment_hold);
    }

    public function test_a_delivered_order_is_not_put_on_hold(): void
    {
        [$order] = $this->overdueOrder('delivered');

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertFalse($order->fresh()->shipment_hold);
    }

    public function test_the_customer_and_admins_are_notified(): void
    {
        [$order, $invoice] = $this->overdueOrder();
        $admin = $this->createAdmin();

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $order->user_id,
            'type'    => 'payment_overdue',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type'    => 'payment_overdue',
        ]);
    }

    // ─── Release ─────────────────────────────────────────────────────────────

    public function test_payment_lifts_the_hold_and_restores_the_stage(): void
    {
        [$order, $invoice] = $this->overdueOrder('at_port');
        $this->artisan('jigila:process-payment-deadlines');

        app(InvoiceService::class)->markPaid($invoice->fresh(), 'jig_ref_1');

        $order->refresh();
        $this->assertSame(OrderStatus::AtPort, $order->status);
        $this->assertFalse($order->shipment_hold);
        $this->assertNull($order->status_before_hold);
    }

    /**
     * Paying one of two lapsed invoices must not release a shipment that is
     * still short of money on the other.
     */
    public function test_the_hold_stays_while_another_invoice_is_still_overdue(): void
    {
        [$order, $first] = $this->overdueOrder('at_port');

        Invoice::factory()->overdue(1)->create([
            'user_id'  => $order->user_id,
            'order_id' => $order->id,
            'status'   => 'pending',
        ]);

        $this->artisan('jigila:process-payment-deadlines');

        app(InvoiceService::class)->markPaid($first->fresh(), 'jig_ref_2');

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentOverdue, $order->status);
        $this->assertTrue($order->shipment_hold);
    }

    public function test_admin_can_release_a_hold_by_hand(): void
    {
        [$order] = $this->overdueOrder('in_transit');
        $this->artisan('jigila:process-payment-deadlines');

        $this->actingAs($this->createAdmin())
            ->postJson("/api/v1/admin/orders/{$order->id}/release-hold", [
                'reason' => 'Bank transfer cleared outside Paystack.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', OrderStatus::InTransit->value)
            ->assertJsonPath('data.shipment_hold', false);
    }

    public function test_releasing_an_order_that_is_not_on_hold_is_rejected(): void
    {
        [$order] = $this->overdueOrder();

        $this->actingAs($this->createAdmin())
            ->postJson("/api/v1/admin/orders/{$order->id}/release-hold", ['reason' => 'No reason at all.'])
            ->assertStatus(422);
    }

    public function test_a_customer_cannot_release_a_hold(): void
    {
        [$order] = $this->overdueOrder();
        $this->artisan('jigila:process-payment-deadlines');

        $this->actingAs($order->user)
            ->postJson("/api/v1/admin/orders/{$order->id}/release-hold", ['reason' => 'Let me go please.'])
            ->assertStatus(403);
    }

    // ─── Invariants the rest of the app depends on ───────────────────────────

    /**
     * The Paystack webhook selects `where('status', 'pending')`. If going
     * overdue ever moved the invoice off `pending`, every payment landing after
     * a deadline would silently fail to register.
     */
    public function test_an_overdue_invoice_stays_pending_so_the_webhook_still_matches(): void
    {
        [$order, $invoice] = $this->overdueOrder();

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('pending', $invoice->fresh()->status);
        $this->assertTrue(
            Invoice::where('payment_reference', $invoice->payment_reference)
                ->where('status', 'pending')
                ->exists()
                || $invoice->payment_reference === null,
        );
    }

    /**
     * `payment_overdue` is system-owned — an admin setting it by hand would
     * create a hold with nothing recorded to restore.
     */
    public function test_an_admin_cannot_set_payment_overdue_directly(): void
    {
        [$order] = $this->overdueOrder();

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'payment_overdue'])
            ->assertStatus(422);
    }

    /**
     * A hold must not silently change what the customer is allowed to do: an
     * order held while `processing` is still cancellable.
     */
    public function test_cancellation_policy_reads_through_the_hold(): void
    {
        [$order] = $this->overdueOrder('processing');
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(OrderStatus::Processing, $order->fresh()->effectiveStatus());
        $this->assertFalse($order->fresh()->passedOperationalMilestone());

        $this->actingAs($order->user)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.effective_status', OrderStatus::Processing->value);
    }
}
