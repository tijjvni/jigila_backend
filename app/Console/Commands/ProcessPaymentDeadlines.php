<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\LateFeeService;
use App\Services\PaymentDeadlineService;
use Illuminate\Console\Command;

/**
 * Expire lapsed payment deadlines and accrue late fees (spec 4).
 *
 * Runs in two passes. The first flips newly lapsed invoices to overdue and
 * places the shipment hold; the second recomputes the fee on everything already
 * past its deadline.
 *
 * Both passes are idempotent. `markOverdue()` is a no-op once `overdue_at` is
 * set, and accrual recomputes `f(days_overdue)` from scratch rather than adding
 * to a running total — so a double run, or a catch-up after the scheduler was
 * down, lands on exactly the same numbers.
 */
class ProcessPaymentDeadlines extends Command
{
    protected $signature = 'jigila:process-payment-deadlines {--dry-run : Report what would change without writing}';

    protected $description = 'Expire lapsed payment deadlines, hold shipments and accrue late fees';

    public function handle(PaymentDeadlineService $deadlines, LateFeeService $lateFees): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $expired = 0;
        $accrued = 0;

        Invoice::dueForOverdue()
            ->with(['user', 'order'])
            ->chunkById(100, function ($invoices) use ($deadlines, $dryRun, &$expired) {
                foreach ($invoices as $invoice) {
                    if ($dryRun) {
                        $this->line("Would mark {$invoice->invoice_number} overdue and hold order #{$invoice->order_id}");
                        $expired++;

                        continue;
                    }

                    if ($deadlines->markOverdue($invoice)) {
                        $expired++;
                    }
                }
            });

        Invoice::accruingLateFee()
            ->chunkById(100, function ($invoices) use ($lateFees, $dryRun, &$accrued) {
                foreach ($invoices as $invoice) {
                    if ($dryRun) {
                        $days = $lateFees->daysOverdue($invoice);
                        $this->line("Would accrue {$days} day(s) of late fee on {$invoice->invoice_number}");
                        $accrued++;

                        continue;
                    }

                    if ($lateFees->accrue($invoice)) {
                        $accrued++;
                    }
                }
            });

        $verb = $dryRun ? 'would be' : 'were';
        $this->info("{$expired} invoice(s) {$verb} marked overdue; {$accrued} late fee(s) {$verb} updated.");

        return self::SUCCESS;
    }
}
