<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `metadata` is ~49% of a serialized paid invoice and is never rendered in a
 * list, so it is returned by the detail endpoints only — and to admins only.
 */
class InvoiceMetadataExposureTest extends TestCase
{
    use RefreshDatabase;

    private const META = [
        'exchange_rate' => 1600,
        'payment'       => ['auth_last4' => '4081', 'gateway_response' => 'Successful'],
    ];

    private function paidInvoice(User $owner): Invoice
    {
        $order = Order::factory()->create(['user_id' => $owner->id]);

        return Invoice::factory()->create([
            'user_id'  => $owner->id,
            'order_id' => $order->id,
            'status'   => 'paid',
            'paid_at'  => now(),
            'metadata' => self::META,
        ]);
    }

    public function test_admin_list_omits_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->paidInvoice(User::factory()->create(['role' => 'user']));

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/invoices')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.0.metadata');
    }

    public function test_admin_detail_includes_metadata(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $invoice = $this->paidInvoice(User::factory()->create(['role' => 'user']));

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.metadata.exchange_rate', 1600)
            ->assertJsonPath('data.metadata.payment.auth_last4', '4081');
    }

    /** Metadata is internal payment data — never exposed to the customer. */
    public function test_customer_detail_never_includes_metadata(): void
    {
        $user    = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($user);

        $this->actingAs($user)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('data.metadata');
    }

    public function test_customer_list_never_includes_metadata(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->paidInvoice($user);

        $this->actingAs($user)
            ->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.0.metadata');
    }

    /** The fields the list view actually renders must all survive the trim. */
    public function test_list_still_carries_everything_the_table_renders(): void
    {
        $admin   = User::factory()->create(['role' => 'admin']);
        $owner   = User::factory()->create(['role' => 'user']);
        $invoice = $this->paidInvoice($owner);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/invoices')
            ->assertStatus(200)
            ->assertJsonPath('data.0.invoice_number', $invoice->invoice_number)
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.user.name', $owner->name)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'description', 'amount', 'amount_ngn',
                    'refund_status', 'order_id', 'order_vin', 'created_at']],
            ]);
    }
}
