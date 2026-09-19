<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Payment deadline set at invoice issuance (spec 4).
 */
class PaymentDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('exchange_rate', 1500);

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data'   => [
                    'authorization_url' => 'https://paystack.com/pay/test',
                    'reference'         => 'jig_test-ref',
                ],
            ]),
        ]);
    }

    private function raise(User $user, ?Order $order = null, ?int $deadlineHours = null): Invoice
    {
        return app(InvoiceService::class)->create(
            user: $user,
            order: $order,
            type: InvoiceType::Service,
            description: 'Freight',
            amount: 1000,
            deadlineHours: $deadlineHours,
        );
    }

    public function test_an_invoice_gets_the_default_72_hour_deadline(): void
    {
        $invoice = $this->raise($this->createUser());

        $this->assertSame(72, $invoice->deadline_hours);
        $this->assertEqualsWithDelta(
            now()->addHours(72)->timestamp,
            $invoice->payment_due_at->timestamp,
            5,
        );
    }

    /** `due_date` is the date part of the same deadline — the email reads it. */
    public function test_the_legacy_due_date_column_tracks_the_deadline(): void
    {
        $invoice = $this->raise($this->createUser());

        $this->assertSame(
            $invoice->payment_due_at->toDateString(),
            $invoice->due_date->toDateString(),
        );
    }

    public function test_the_deadline_is_configurable_per_order(): void
    {
        $invoice = $this->raise($this->createUser(), deadlineHours: 24);

        $this->assertSame(24, $invoice->deadline_hours);
        $this->assertEqualsWithDelta(
            now()->addHours(24)->timestamp,
            $invoice->payment_due_at->timestamp,
            5,
        );
    }

    public function test_an_admin_setting_overrides_the_config_default(): void
    {
        Setting::set('payment_deadline_hours', 12);

        $this->assertSame(12, $this->raise($this->createUser())->deadline_hours);
    }

    /** The fee schedule is frozen at issuance, not read at accrual time. */
    public function test_late_fee_terms_are_stamped_onto_the_invoice(): void
    {
        Setting::set('late_fee_mode', 'flat');
        Setting::set('late_fee_flat_amount', 30);

        $invoice = $this->raise($this->createUser());

        $this->assertSame('flat', $invoice->late_fee_mode);
        $this->assertSame('30.00', $invoice->late_fee_rate);
    }

    public function test_admin_can_set_the_deadline_when_raising_an_invoice(): void
    {
        $admin = $this->createAdmin();
        $user  = $this->createUser();
        $order = $this->createOrder($user);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/invoices", [
                'description'            => 'Freight charges',
                'amount'                 => 1200,
                'payment_deadline_hours' => 48,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.deadline_hours', 48);
    }

    public function test_admin_can_move_a_deadline_directly(): void
    {
        $admin   = $this->createAdmin();
        $user    = $this->createUser();
        $invoice = Invoice::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/deadline", ['deadline_hours' => 96])
            ->assertStatus(200);

        $this->assertEqualsWithDelta(
            now()->addHours(96)->timestamp,
            $invoice->fresh()->payment_due_at->timestamp,
            5,
        );
    }

    public function test_moving_a_deadline_rearms_the_reminder_stages(): void
    {
        $admin   = $this->createAdmin();
        $invoice = Invoice::factory()->create([
            'user_id'              => $this->createUser()->id,
            'status'               => 'pending',
            'reminder_stages_sent' => ['48', '24'],
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/deadline", ['deadline_hours' => 96])
            ->assertStatus(200);

        $this->assertNull($invoice->fresh()->reminder_stages_sent);
    }

    public function test_a_paid_invoice_deadline_cannot_be_moved(): void
    {
        $admin   = $this->createAdmin();
        $invoice = Invoice::factory()->paid()->create(['user_id' => $this->createUser()->id]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/deadline", ['deadline_hours' => 96])
            ->assertStatus(422);
    }

    public function test_the_deadline_endpoint_requires_one_of_the_two_inputs(): void
    {
        $admin   = $this->createAdmin();
        $invoice = Invoice::factory()->create(['user_id' => $this->createUser()->id]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/deadline", [])
            ->assertStatus(422);
    }

    public function test_a_customer_cannot_move_their_own_deadline(): void
    {
        $user    = $this->createUser();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/admin/invoices/{$invoice->id}/deadline", ['deadline_hours' => 999])
            ->assertStatus(403);
    }

    public function test_the_invoice_resource_exposes_the_countdown(): void
    {
        $user    = $this->createUser();
        $invoice = Invoice::factory()->dueIn(10)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_overdue', false)
            ->assertJsonPath('data.can_request_extension', true)
            ->assertJsonStructure(['data' => ['payment_due_at', 'hours_until_due', 'total_due']]);
    }
}
