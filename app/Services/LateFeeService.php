<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\LateFeeMode;
use App\Models\Invoice;
use App\Models\Setting;

/**
 * Late fee accrual for invoices past their payment deadline (spec 4).
 *
 * Two rules make this safe to run on a scheduler:
 *
 * 1. **Accrual recomputes, it never increments.** `late_fee_amount` is always
 *    `f(days_overdue)` evaluated from scratch, so running the sweep twice in a
 *    minute — or catching up after the scheduler was down for a week — lands on
 *    exactly the same number. An `+= daily_rate` version would double-charge.
 *
 * 2. **The schedule is frozen at issuance.** Mode and rate are copied onto the
 *    invoice when it is raised, so an admin changing the fee schedule in
 *    settings tomorrow cannot re-price invoices already in customers' hands.
 *
 * The fee is billed as its own `late_fee` invoice rather than being folded into
 * the parent: the parent's Paystack transaction was initialised for a fixed
 * amount, and a fee that grows daily has nowhere to go on that link.
 */
class LateFeeService
{
    /**
     * The live fee schedule — admin settings layered over config defaults.
     */
    public function schedule(): array
    {
        $defaults = config('orders.late_fee');

        return [
            'mode'        => (string) Setting::get('late_fee_mode', $defaults['mode']),
            'flat_amount' => (float) Setting::get('late_fee_flat_amount', $defaults['flat_amount']),
            'percent'     => (float) Setting::get('late_fee_percent', $defaults['percent']),
            'grace_hours' => (int) Setting::get('late_fee_grace_hours', $defaults['grace_hours']),
            'max_days'    => (int) Setting::get('late_fee_max_days', $defaults['max_days']),
            'cap_percent' => (float) Setting::get('late_fee_cap_percent', $defaults['cap_percent']),
        ];
    }

    /**
     * The mode and per-day rate to stamp on a freshly raised invoice.
     *
     * @return array{mode: string, rate: float}
     */
    public function termsAtIssuance(): array
    {
        $schedule = $this->schedule();
        $mode     = LateFeeMode::tryFrom($schedule['mode']) ?? LateFeeMode::None;

        return [
            'mode' => $mode->value,
            'rate' => match ($mode) {
                LateFeeMode::Flat    => $schedule['flat_amount'],
                LateFeeMode::Percent => $schedule['percent'],
                LateFeeMode::None    => 0.0,
            },
        ];
    }

    /**
     * Whole days late, counting a part day as a full day.
     *
     * One hour past the deadline is one day's fee — `grace_hours` is the lever
     * for softening that, not a fractional first day.
     */
    public function daysOverdue(Invoice $invoice, ?int $graceHours = null): int
    {
        if ($invoice->payment_due_at === null) {
            return 0;
        }

        $grace   = $graceHours ?? $this->schedule()['grace_hours'];
        $charged = $invoice->payment_due_at->copy()->addHours($grace);

        if (now()->lessThanOrEqualTo($charged)) {
            return 0;
        }

        // Truncate to whole minutes before rounding up. Carbon diffs carry
        // microsecond precision, so a deadline exactly N days old measures as
        // N.000000004 days and `ceil` would charge for N+1 — a full extra day
        // decided by the microseconds between two `now()` calls.
        $minutes = (int) floor($charged->diffInMinutes(now()));

        return (int) ceil($minutes / (24 * 60));
    }

    /**
     * Recompute the fee owed on an invoice. Returns true when the stored figure
     * actually moved, so the caller can decide whether to notify.
     */
    public function accrue(Invoice $invoice): bool
    {
        if ($invoice->status !== 'pending' || $invoice->payment_due_at === null) {
            return false;
        }

        $schedule = $this->schedule();

        // Terms frozen at issuance win; fall back to the live schedule for
        // invoices raised before this feature existed.
        $mode = LateFeeMode::tryFrom((string) $invoice->late_fee_mode)
            ?? LateFeeMode::tryFrom($schedule['mode'])
            ?? LateFeeMode::None;

        $rate = $invoice->late_fee_rate !== null
            ? (float) $invoice->late_fee_rate
            : ($mode === LateFeeMode::Flat ? $schedule['flat_amount'] : $schedule['percent']);

        $days   = min($this->daysOverdue($invoice, $schedule['grace_hours']), $schedule['max_days']);
        $amount = $this->feeFor($mode, $rate, $days, (float) $invoice->amount, $schedule['cap_percent']);

        if ((float) $invoice->late_fee_amount === $amount && $invoice->late_fee_days === $days) {
            return false;
        }

        $invoice->forceFill([
            'late_fee_amount'     => $amount,
            'late_fee_days'       => $days,
            'late_fee_accrued_at' => now(),
        ])->save();

        return true;
    }

    /**
     * The fee for a given number of overdue days, capped at `cap_percent` of
     * the invoice so a forgotten invoice cannot accrue without limit.
     */
    public function feeFor(LateFeeMode $mode, float $rate, int $days, float $invoiceAmount, float $capPercent): float
    {
        if ($days < 1 || $mode === LateFeeMode::None) {
            return 0.0;
        }

        $fee = match ($mode) {
            LateFeeMode::Flat    => $days * $rate,
            LateFeeMode::Percent => $days * ($rate / 100) * $invoiceAmount,
            LateFeeMode::None    => 0.0,
        };

        $cap = $invoiceAmount * ($capPercent / 100);

        return round(min($fee, $cap), 2);
    }

    /**
     * Is there already an unsettled late-fee invoice for this parent? Guards
     * against billing the same accrual twice.
     */
    public function hasOpenLateFeeInvoice(Invoice $parent): bool
    {
        // JSON-path equality rather than `whereJsonContains` — containment on a
        // scalar path behaves differently across SQLite, MySQL and MariaDB.
        return Invoice::where('type', InvoiceType::LateFee)
            ->where('status', 'pending')
            ->where('metadata->late_fee_for_invoice_id', $parent->id)
            ->exists();
    }
}
