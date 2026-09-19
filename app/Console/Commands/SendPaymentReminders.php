<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\PaymentDeadlineService;
use Illuminate\Console\Command;

/**
 * Deadline-anchored payment reminders (spec 4).
 *
 * Reminders fire at fixed points relative to the invoice's own deadline —
 * 48 hours before, 24 before, 6 before and at expiry — rather than on a rolling
 * interval. Each stage is recorded in `reminder_stages_sent` as it goes out, so
 * the command is safe to run at any frequency: running it twice in a minute
 * sends nothing the second time, and a scheduler outage produces a late notice
 * rather than a duplicate one.
 */
class SendPaymentReminders extends Command
{
    protected $signature = 'jigila:send-payment-reminders {--dry-run : List what would be sent without sending}';

    protected $description = 'Send deadline reminders for invoices that are still unpaid';

    public function handle(PaymentDeadlineService $deadlines): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent   = 0;

        Invoice::dueForStageReminder()
            ->with('user')
            ->chunkById(100, function ($invoices) use ($deadlines, $dryRun, &$sent) {
                foreach ($invoices as $invoice) {
                    if (!$invoice->user) {
                        continue;
                    }

                    if ($dryRun) {
                        foreach ($deadlines->pendingStages($invoice)['due'] as $stage) {
                            $this->line("Would remind {$invoice->user->email} about {$invoice->invoice_number} (stage: {$stage})");
                            $sent++;
                        }

                        continue;
                    }

                    $sent += count($deadlines->sendDueReminders($invoice));
                }
            });

        $this->info($dryRun ? "{$sent} reminder(s) would be sent." : "{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
