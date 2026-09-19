<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\LateFeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Late fee accrual (spec 4).
 *
 * The invoice under test is $1,000 with the default percent schedule
 * (1.5%/day = $15/day), unless a test says otherwise.
 */
class LateFeeAccrualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These assertions sit on exact day boundaries; a frozen clock keeps
        // them deterministic. `travel()` still works against frozen time.
        $this->freezeTime();

        Setting::set('exchange_rate', 1500);

        Http::fake([
            '*/transaction/initialize' => Http::response([
                'status' => true,
                'data'   => [
                    'authorization_url' => 'https://paystack.com/pay/test',
                    'reference'         => 'jig_late_fee_ref',
                ],
            ]),
        ]);
    }

    private function overdueInvoice(int $days, array $attributes = []): Invoice
    {
        $user  = $this->createUser();
        $order = $this->createOrder($user, ['status' => 'processing']);

        return Invoice::factory()->overdue($days)->create($attributes + [
            'user_id'  => $user->id,
            'order_id' => $order->id,
            'status'   => 'pending',
            'amount'   => 1000,
        ]);
    }

    // ─── The arithmetic ──────────────────────────────────────────────────────

    public function test_percent_mode_charges_a_percent_of_the_invoice_per_day(): void
    {
        $invoice = $this->overdueInvoice(3);

        $this->artisan('jigila:process-payment-deadlines');

        $fresh = $invoice->fresh();
        $this->assertSame(3, $fresh->late_fee_days);
        $this->assertSame('45.00', $fresh->late_fee_amount);
    }

    public function test_flat_mode_charges_a_fixed_amount_per_day(): void
    {
        $invoice = $this->overdueInvoice(4, ['late_fee_mode' => 'flat', 'late_fee_rate' => 25]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('100.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_no_fee_accrues_in_none_mode(): void
    {
        $invoice = $this->overdueInvoice(5, ['late_fee_mode' => 'none', 'late_fee_rate' => 0]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('0.00', $invoice->fresh()->late_fee_amount);
    }

    /** A day or part thereof: one hour late is one day's fee. */
    public function test_a_part_day_counts_as_a_full_day(): void
    {
        $invoice = $this->overdueInvoice(0, ['payment_due_at' => now()->subHour()]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(1, $invoice->fresh()->late_fee_days);
        $this->assertSame('15.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_grace_hours_hold_the_fee_off(): void
    {
        Setting::set('late_fee_grace_hours', 6);
        $invoice = $this->overdueInvoice(0, ['payment_due_at' => now()->subHours(3)]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(0, $invoice->fresh()->late_fee_days);
        $this->assertSame('0.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_the_fee_is_capped_at_a_percent_of_the_invoice(): void
    {
        // 1.5%/day unchecked would be $600 after 40 days; the 25% cap is $250.
        $invoice = $this->overdueInvoice(40);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('250.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_accrual_stops_after_max_days(): void
    {
        Setting::set('late_fee_max_days', 5);
        Setting::set('late_fee_cap_percent', 100);
        $invoice = $this->overdueInvoice(20);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame(5, $invoice->fresh()->late_fee_days);
        $this->assertSame('75.00', $invoice->fresh()->late_fee_amount);
    }

    // ─── Safety properties ───────────────────────────────────────────────────

    /**
     * Accrual recomputes `f(days)` rather than adding to a running total, so a
     * double run — or a catch-up after the scheduler was down — cannot
     * double-charge.
     */
    public function test_running_the_sweep_twice_does_not_double_charge(): void
    {
        $invoice = $this->overdueInvoice(3);

        $this->artisan('jigila:process-payment-deadlines');
        $this->artisan('jigila:process-payment-deadlines');
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('45.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_the_fee_grows_as_days_pass(): void
    {
        $invoice = $this->overdueInvoice(1);

        $this->artisan('jigila:process-payment-deadlines');
        $this->assertSame('15.00', $invoice->fresh()->late_fee_amount);

        $this->travel(2)->days();
        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('45.00', $invoice->fresh()->late_fee_amount);
    }

    /** Changing the schedule must not re-price invoices already issued. */
    public function test_terms_frozen_at_issuance_survive_a_settings_change(): void
    {
        $invoice = $this->overdueInvoice(2, ['late_fee_mode' => 'flat', 'late_fee_rate' => 50]);

        Setting::set('late_fee_mode', 'percent');
        Setting::set('late_fee_percent', 10);

        $this->artisan('jigila:process-payment-deadlines');

        // Still 2 × $50 flat, not 2 × 10% of $1,000.
        $this->assertSame('100.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_a_paid_invoice_never_accrues(): void
    {
        $invoice = $this->overdueInvoice(5, ['status' => 'paid', 'paid_at' => now()]);

        $this->artisan('jigila:process-payment-deadlines');

        $this->assertSame('0.00', $invoice->fresh()->late_fee_amount);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $invoice = $this->overdueInvoice(3);

        $this->artisan('jigila:process-payment-deadlines', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame('0.00', $invoice->fresh()->late_fee_amount);
        $this->assertNull($invoice->fresh()->overdue_at);
    }

    public function test_days_overdue_is_zero_before_the_deadline(): void
    {
        $invoice = Invoice::factory()->dueIn(10)->create(['user_id' => $this->createUser()->id]);

        $this->assertSame(0, app(LateFeeService::class)->daysOverdue($invoice));
    }

    // ─── Billing the fee ─────────────────────────────────────────────────────

    /**
     * The parent's Paystack transaction was initialised for a fixed amount, so
     * a late fee is billed as its own invoice with its own payment link.
     */
    public function test_admin_can_bill_the_accrued_fee_as_its_own_invoice(): void
    {
        $invoice = $this->overdueInvoice(3);
        $this->artisan('jigila:process-payment-deadlines');

        $this->actingAs($this->createAdmin())
            ->postJson("/api/v1/admin/invoices/{$invoice->id}/late-fee-invoice")
            ->assertStatus(201)
            ->assertJsonPath('data.type', InvoiceType::LateFee->value)
            ->assertJsonPath('data.amount', '45.00');

        $this->assertNotNull($invoice->fresh()->late_fee_invoiced_at);
    }

    public function test_the_late_fee_invoice_points_back_at_its_parent(): void
    {
        $invoice = $this->overdueInvoice(3);
        $this->artisan('jigila:process-payment-deadlines');

        $this->actingAs($this->createAdmin())
            ->postJson("/api/v1/admin/invoices/{$invoice->id}/late-fee-invoice");

        $lateFee = Invoice::where('type', InvoiceType::LateFee)->firstOrFail();
        $this->assertSame($invoice->id, $lateFee->metadata['late_fee_for_invoice_id']);
        $this->assertSame($invoice->order_id, $lateFee->order_id);
    }

    public function test_the_same_fee_cannot_be_billed_twice(): void
    {
        $invoice = $this->overdueInvoice(3);
        $this->artisan('jigila:process-payment-deadlines');
        $admin = $this->createAdmin();

        $this->actingAs($admin)->postJson("/api/v1/admin/invoices/{$invoice->id}/late-fee-invoice")
            ->assertStatus(201);
        $this->actingAs($admin)->postJson("/api/v1/admin/invoices/{$invoice->id}/late-fee-invoice")
            ->assertStatus(422);
    }

    public function test_billing_a_fee_that_has_not_accrued_is_rejected(): void
    {
        $invoice = $this->overdueInvoice(3);

        $this->actingAs($this->createAdmin())
            ->postJson("/api/v1/admin/invoices/{$invoice->id}/late-fee-invoice")
            ->assertStatus(422);
    }

    public function test_total_due_reflects_the_accrued_fee(): void
    {
        $invoice = $this->overdueInvoice(2);
        $this->artisan('jigila:process-payment-deadlines');

        $this->actingAs($invoice->user)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.late_fee_amount', '30.00')
            ->assertJsonPath('data.total_due', 1030)
            ->assertJsonPath('data.is_overdue', true);
    }
}
