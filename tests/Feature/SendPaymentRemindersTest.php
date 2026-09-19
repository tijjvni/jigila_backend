<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deadline-anchored reminder stages (spec 4).
 *
 * Reminders fire at 48h, 24h and 6h before the deadline and at expiry, each at
 * most once, tracked in `reminder_stages_sent`.
 */
class SendPaymentRemindersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An invoice raised `$agoHours` ago whose deadline is `$dueInHours` away.
     */
    private function pendingInvoice(int $dueInHours, int $agoHours = 96, array $attributes = []): Invoice
    {
        $user = User::factory()->create(['role' => 'user']);

        return Invoice::factory()->create($attributes + [
            'user_id'        => $user->id,
            'status'         => 'pending',
            'created_at'     => now()->subHours($agoHours),
            'payment_due_at' => now()->addHours($dueInHours),
            'deadline_hours' => $agoHours + $dueInHours,
        ]);
    }

    private function reminderCount(Invoice $invoice): int
    {
        return Notification::where('user_id', $invoice->user_id)
            ->where('type', 'payment_reminder')
            ->count();
    }

    public function test_the_48_hour_stage_fires_once_inside_the_window(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 47);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $invoice->user_id,
            'type'    => 'payment_reminder',
        ]);
        $this->assertSame(['48'], $invoice->fresh()->reminder_stages_sent);
        $this->assertSame(1, $invoice->fresh()->reminder_count);
    }

    public function test_no_reminder_before_the_first_stage_is_reached(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 60);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(0, $this->reminderCount($invoice));
    }

    /**
     * Every stage the deadline has already swept past fires on the first run —
     * one notice each, not one repeated.
     */
    public function test_all_elapsed_stages_fire_together_at_expiry(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 0);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(['48', '24', '6', 'expiry'], $invoice->fresh()->reminder_stages_sent);
        $this->assertSame(4, $this->reminderCount($invoice));
    }

    /**
     * A deadline shorter than an offset must not fire that offset's notice —
     * on a 24-hour deadline, "48 hours left" was never true. The stage is
     * consumed so it is evaluated once and never again.
     */
    public function test_a_stage_that_predates_the_invoice_is_consumed_not_sent(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 23, agoHours: 1);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $fresh = $invoice->fresh();
        // Both the 48h and the 24h notice would have been due at or before
        // issuance on a 24-hour deadline, so both are consumed unsent.
        $this->assertContains('48', $fresh->reminder_stages_sent);
        $this->assertContains('24', $fresh->reminder_stages_sent);
        $this->assertSame(0, $this->reminderCount($invoice));
        $this->assertSame(0, $fresh->reminder_count);
    }

    public function test_a_paid_invoice_is_never_reminded(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 1, attributes: ['status' => 'paid', 'paid_at' => now()]);

        $this->artisan('jigila:send-payment-reminders')->assertExitCode(0);

        $this->assertSame(0, $this->reminderCount($invoice));
    }

    /** Running twice back to back must not double-send. */
    public function test_the_command_is_idempotent(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 47);

        $this->artisan('jigila:send-payment-reminders');
        $this->artisan('jigila:send-payment-reminders');

        $this->assertSame(1, $invoice->fresh()->reminder_count);
        $this->assertSame(1, $this->reminderCount($invoice));
    }

    /** Each stage is its own notice: crossing 24h after 48h sends again. */
    public function test_a_later_stage_fires_on_a_subsequent_run(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 47);

        $this->artisan('jigila:send-payment-reminders');
        $this->assertSame(1, $this->reminderCount($invoice));

        $this->travel(24)->hours();
        $this->artisan('jigila:send-payment-reminders');

        $this->assertSame(['48', '24'], $invoice->fresh()->reminder_stages_sent);
        $this->assertSame(2, $this->reminderCount($invoice));
    }

    public function test_dry_run_sends_nothing(): void
    {
        $invoice = $this->pendingInvoice(dueInHours: 47);

        $this->artisan('jigila:send-payment-reminders', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, $invoice->fresh()->reminder_count);
        $this->assertNull($invoice->fresh()->reminder_stages_sent);
        $this->assertSame(0, $this->reminderCount($invoice));
    }
}
