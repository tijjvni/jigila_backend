<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendPaymentRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function pendingInvoice(array $attributes = []): Invoice
    {
        $user = User::factory()->create(['role' => 'user']);

        return Invoice::factory()->create($attributes + [
            'user_id'    => $user->id,
            'status'     => 'pending',
            'created_at' => now()->subDays(3),
        ]);
    }

    public function test_a_stale_unpaid_invoice_is_reminded(): void
    {
        $invoice = $this->pendingInvoice();

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $invoice->user_id,
            'type'    => 'payment_reminder',
        ]);
        $this->assertSame(1, $invoice->fresh()->reminder_count);
    }

    public function test_a_freshly_raised_invoice_is_not_reminded(): void
    {
        $invoice = $this->pendingInvoice(['created_at' => now()->subMinutes(5)]);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $invoice->user_id,
            'type'    => 'payment_reminder',
        ]);
    }

    public function test_a_paid_invoice_is_never_reminded(): void
    {
        $invoice = $this->pendingInvoice(['status' => 'paid', 'paid_at' => now()]);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $invoice->user_id,
            'type'    => 'payment_reminder',
        ]);
    }

    /** Running twice back to back must not double-send. */
    public function test_the_command_is_idempotent_within_one_interval(): void
    {
        $invoice = $this->pendingInvoice();

        $this->artisan('jigila:send-payment-reminders');
        $this->artisan('jigila:send-payment-reminders');

        $this->assertSame(1, $invoice->fresh()->reminder_count);
        $this->assertSame(1, Notification::where('user_id', $invoice->user_id)
            ->where('type', 'payment_reminder')
            ->count());
    }

    public function test_reminders_stop_at_the_configured_cap(): void
    {
        config(['orders.payment_reminder_max' => 2]);
        $invoice = $this->pendingInvoice(['reminder_count' => 2, 'last_reminded_at' => now()->subDays(5)]);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(2, $invoice->fresh()->reminder_count);
    }

    public function test_dry_run_sends_nothing(): void
    {
        $invoice = $this->pendingInvoice();

        $this->artisan('jigila:send-payment-reminders', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, $invoice->fresh()->reminder_count);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $invoice->user_id,
            'type'    => 'payment_reminder',
        ]);
    }
}
