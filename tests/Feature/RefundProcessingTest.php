<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundProcessingTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Bid was unsuccessful and I no longer need the service.';

    private function paidInvoice(User $user, float $amount = 1000): Invoice
    {
        $order = Order::factory()->create(['user_id' => $user->id]);

        return Invoice::factory()->create([
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'amount'   => $amount,
            'status'   => 'paid',
            'paid_at'  => now(),
        ]);
    }

    // ─── Customer request ─────────────────────────────────────────────────────

    public function test_customer_can_request_a_refund_on_a_paid_invoice(): void
    {
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON])
            ->assertStatus(200)
            ->assertJsonPath('data.refund_status', RefundStatus::Requested->value)
            // The payment itself is untouched — a refund is a separate axis.
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_refund_cannot_be_requested_on_an_unpaid_invoice(): void
    {
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON])
            ->assertStatus(422);
    }

    public function test_customer_cannot_request_a_refund_on_someone_elses_invoice(): void
    {
        $owner    = User::factory()->create(['role' => 'user']);
        $intruder = User::factory()->create(['role' => 'user']);
        $invoice  = $this->paidInvoice($owner);

        $this->actingAs($intruder)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON])
            ->assertStatus(403);
    }

    public function test_duplicate_refund_request_is_rejected(): void
    {
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON])
            ->assertStatus(200);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => 'Asking again.'])
            ->assertStatus(422);
    }

    public function test_request_notifies_admins(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type'    => 'refund_requested',
        ]);
    }

    // ─── Admin transitions ────────────────────────────────────────────────────

    public function test_admin_can_walk_a_refund_from_requested_to_processed(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Approved->value,
            ])->assertStatus(200)->assertJsonPath('data.refund_status', RefundStatus::Approved->value);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Processed->value,
            ])->assertStatus(200)
            ->assertJsonPath('data.refund_status', RefundStatus::Processed->value);

        $this->assertNotNull($invoice->fresh()->refund_processed_at);
        $this->assertSame($admin->id, $invoice->fresh()->refund_processed_by);
    }

    public function test_illegal_transition_is_rejected(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->postJson("/api/v1/invoices/{$invoice->id}/refund-request", ['reason' => self::REASON]);

        // requested → processed skips approval
        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Processed->value,
            ])->assertStatus(422);
    }

    public function test_processed_refund_is_terminal(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", ['refund_status' => RefundStatus::Approved->value])
            ->assertStatus(200);
        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", ['refund_status' => RefundStatus::Processed->value])
            ->assertStatus(200);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", ['refund_status' => RefundStatus::Rejected->value])
            ->assertStatus(422);
    }

    public function test_partial_refund_amount_is_accepted(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user, 1000);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Approved->value,
                'amount'        => 400,
            ])->assertStatus(200)
            ->assertJsonPath('data.refund_amount', '400.00');
    }

    public function test_refund_cannot_exceed_the_invoice_amount(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user, 1000);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Approved->value,
                'amount'        => 1500,
            ])->assertStatus(422);
    }

    public function test_customer_cannot_use_the_admin_refund_route(): void
    {
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Processed->value,
            ])->assertStatus(403);
    }

    public function test_status_change_notifies_the_customer(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/refund", [
                'refund_status' => RefundStatus::Approved->value,
            ])->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'refund_approved',
        ]);
    }
}
