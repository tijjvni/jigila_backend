<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Hour-based payment reminders for outstanding invoices (BUG-033).
 *
 * Safe to run as often as the scheduler likes: `dueForReminder()` only returns
 * invoices whose last reminder is at least one full interval old, and the
 * counter is bumped in the same pass, so a double run never double-sends.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'jigila:send-payment-reminders {--dry-run : List what would be sent without sending}';

    protected $description = 'Send reminders for invoices that are still unpaid';

    public function handle(NotificationService $notifications): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent   = 0;

        Invoice::dueForReminder()
            ->with('user')
            ->chunkById(100, function ($invoices) use ($notifications, $dryRun, &$sent) {
                foreach ($invoices as $invoice) {
                    if (!$invoice->user) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Would remind {$invoice->user->email} about {$invoice->invoice_number}");
                        $sent++;

                        continue;
                    }

                    $notifications->sendPaymentReminder($invoice);

                    $invoice->forceFill([
                        'last_reminded_at' => now(),
                        'reminder_count'   => $invoice->reminder_count + 1,
                    ])->save();

                    $sent++;
                }
            });

        $this->info($dryRun ? "{$sent} reminder(s) would be sent." : "{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
