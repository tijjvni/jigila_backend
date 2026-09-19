<?php

namespace Tests\Feature;

use App\Enums\DeadlineExtensionStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * One-time customer-requested payment deadline extension (spec 4).
 */
class DeadlineExtensionTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'My bank transfer is still clearing, I need another two days.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    /**
     * @return array{0: User, 1: Invoice}
     */
    private function pendingInvoice(int $dueInHours = 10, array $attributes = []): array
    {
        $user  = $this->createUser();
        $order = $this->createOrder($user, ['status' => 'processing']);

        $invoice = Invoice::factory()->dueIn($dueInHours)->create($attributes + [
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'status'   => 'pending',
            'amount'   => 1000,
        ]);

        return [$user, $invoice];
    }

    private function request(User $user, Invoice $invoice, int $hours = 24): TestResponse
    {
        return $this->actingAs($user)->postJson(
            "/api/v1/invoices/{$invoice->id}/extension-request",
            ['requested_hours' => $hours, 'reason' => self::REASON],
        );
    }

    // ─── Requesting ──────────────────────────────────────────────────────────

    public function test_a_customer_can_request_an_extension(): void
    {
        [$user, $invoice] = $this->pendingInvoice();

        $this->request($user, $invoice)
            ->assertStatus(200)
            ->assertJsonPath('data.extension_status', DeadlineExtensionStatus::Requested->value)
            ->assertJsonPath('data.extension_requested_hours', 24);
    }

    /** Requesting does not move the deadline — an admin has to approve it. */
    public function test_requesting_does_not_move_the_deadline(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $before = $invoice->payment_due_at;

        $this->request($user, $invoice);

        $this->assertEquals($before, $invoice->fresh()->payment_due_at);
    }

    public function test_admins_are_notified_of_a_request(): void
    {
        $admin = $this->createAdmin();
        [$user, $invoice] = $this->pendingInvoice();

        $this->request($user, $invoice);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type'    => 'deadline_extension_requested',
        ]);
    }

    public function test_a_second_request_while_one_is_pending_is_rejected(): void
    {
        [$user, $invoice] = $this->pendingInvoice();

        $this->request($user, $invoice)->assertStatus(200);
        $this->request($user, $invoice)->assertStatus(422);
    }

    public function test_a_paid_invoice_cannot_be_extended(): void
    {
        [$user, $invoice] = $this->pendingInvoice(attributes: ['status' => 'paid', 'paid_at' => now()]);

        $this->request($user, $invoice)->assertStatus(422);
    }

    public function test_a_request_beyond_the_maximum_is_rejected(): void
    {
        [$user, $invoice] = $this->pendingInvoice();

        $this->request($user, $invoice, 500)->assertStatus(422);
    }

    public function test_a_customer_cannot_request_on_someone_elses_invoice(): void
    {
        [, $invoice] = $this->pendingInvoice();
        $intruder = $this->createUser();

        $this->request($intruder, $invoice)->assertStatus(403);
    }

    // ─── Reviewing ───────────────────────────────────────────────────────────

    public function test_an_admin_can_approve_and_the_deadline_moves(): void
    {
        [$user, $invoice] = $this->pendingInvoice(10);
        $this->request($user, $invoice, 24);
        $original = $invoice->fresh()->payment_due_at;

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.extension_status', DeadlineExtensionStatus::Approved->value)
            ->assertJsonPath('data.extension_granted_hours', 24);

        $fresh = $invoice->fresh();
        // `copy()` — Carbon is mutable, and `addHours` on the shared instance
        // would shift the value the next assertion compares against.
        $this->assertEquals($original->copy()->addHours(24), $fresh->payment_due_at);
        $this->assertEquals($original, $fresh->original_payment_due_at);
    }

    public function test_an_admin_can_grant_fewer_hours_than_requested(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice, 48);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
                'granted_hours'    => 12,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.extension_granted_hours', 12);
    }

    public function test_approving_rearms_the_reminder_stages(): void
    {
        [$user, $invoice] = $this->pendingInvoice(attributes: ['reminder_stages_sent' => ['48', '24']]);
        $this->request($user, $invoice);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ]);

        $this->assertNull($invoice->fresh()->reminder_stages_sent);
    }

    public function test_an_admin_can_decline(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice);
        $before = $invoice->fresh()->payment_due_at;

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Rejected->value,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.extension_status', DeadlineExtensionStatus::Rejected->value);

        $this->assertEquals($before, $invoice->fresh()->payment_due_at);
    }

    /** A declined request may be replaced; an approved one is final. */
    public function test_a_customer_may_re_request_after_a_decline(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Rejected->value,
            ]);

        $this->request($user, $invoice)->assertStatus(200);
    }

    public function test_the_extension_is_one_time_only(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ])->assertStatus(200);

        // Neither the customer nor a second approval can extend again.
        $this->request($user, $invoice)->assertStatus(422);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ])->assertStatus(422);
    }

    public function test_a_customer_cannot_approve_their_own_extension(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice);

        $this->actingAs($user)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ])
            ->assertStatus(403);
    }

    public function test_the_customer_is_notified_of_the_outcome(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'deadline_extension_approved',
        ]);
    }

    // ─── Interaction with expiry, holds and fees ─────────────────────────────

    /**
     * Granted after expiry, the customer gets the full hours from now rather
     * than an extension that lands in the past.
     */
    public function test_an_extension_granted_after_expiry_runs_from_now(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $invoice->forceFill(['payment_due_at' => now()->subDays(2)])->save();

        $this->request($user, $invoice, 24);
        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ]);

        // Compared on seconds: a frozen `now()` still carries microseconds,
        // which the timestamp column does not store.
        $this->assertSame(
            now()->addHours(24)->toDateTimeString(),
            $invoice->fresh()->payment_due_at->toDateTimeString(),
        );
    }

    public function test_approving_lifts_the_shipment_hold(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $invoice->forceFill(['payment_due_at' => now()->subDay()])->save();
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(OrderStatus::PaymentOverdue, Order::find($invoice->order_id)->status);

        $this->request($user, $invoice, 48);
        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ])->assertStatus(200);

        $order = Order::find($invoice->order_id);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertFalse($order->shipment_hold);
        $this->assertNull($invoice->fresh()->overdue_at);
    }

    /**
     * The default: an extension forgives further accrual, not the days the
     * customer was already late.
     */
    public function test_fees_already_accrued_are_frozen_not_erased(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $invoice->forceFill(['payment_due_at' => now()->subDays(2)])->save();
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('30.00', $invoice->fresh()->late_fee_amount);

        $this->request($user, $invoice, 48);
        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ]);

        $this->assertSame('30.00', $invoice->fresh()->late_fee_amount);
    }

    /** No further accrual while the new deadline is in the future. */
    public function test_no_further_fee_accrues_during_the_extension(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $invoice->forceFill(['payment_due_at' => now()->subDays(2)])->save();
        $this->artisan('jigila:process-payment-deadlines');

        $this->request($user, $invoice, 48);
        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
            ]);

        $this->travel(24)->hours();
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('30.00', $invoice->fresh()->late_fee_amount);
    }

    /** An admin can clear the accrued fee when the delay was Jigila's fault. */
    public function test_an_admin_can_waive_the_accrued_fee(): void
    {
        [$user, $invoice] = $this->pendingInvoice();
        $invoice->forceFill(['payment_due_at' => now()->subDays(2)])->save();
        $this->artisan('jigila:process-payment-deadlines');

        $this->request($user, $invoice, 48);
        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status'   => DeadlineExtensionStatus::Approved->value,
                'waive_accrued_fees' => true,
            ])->assertStatus(200);

        $fresh = $invoice->fresh();
        $this->assertSame('0.00', $fresh->late_fee_amount);
        $this->assertSame(0, $fresh->late_fee_days);
    }

    public function test_the_max_extension_is_configurable(): void
    {
        Setting::set('deadline_extension_max_hours', 6);
        [$user, $invoice] = $this->pendingInvoice();
        $this->request($user, $invoice, 6)->assertStatus(200);

        $this->actingAs($this->createAdmin())
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/extension", [
                'extension_status' => DeadlineExtensionStatus::Approved->value,
                'granted_hours'    => 48,
            ])
            ->assertStatus(422);
    }
}
