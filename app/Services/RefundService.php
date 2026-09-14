<?php

namespace App\Services;

use App\Enums\RefundStatus;
use App\Models\Invoice;
use App\Models\OrderAuditLog;
use App\Models\User;

/**
 * Refund processing (BUG-055).
 *
 * A refund never rewrites `status`: an invoice that was paid stays paid, and
 * `refund_status` tracks the separate requested → approved → processed path
 * (or → rejected). That keeps the payment ledger honest and lets the customer
 * see both facts at once.
 */
class RefundService
{
    /** Transitions an admin is allowed to make, keyed by the current state. */
    private const ALLOWED = [
        ''                             => [RefundStatus::Requested, RefundStatus::Approved, RefundStatus::Rejected],
        RefundStatus::Requested->value => [RefundStatus::Approved, RefundStatus::Rejected],
        RefundStatus::Approved->value  => [RefundStatus::Processed, RefundStatus::Rejected],
        RefundStatus::Rejected->value  => [RefundStatus::Requested, RefundStatus::Approved],
        RefundStatus::Processed->value => [],
    ];

    public function __construct(private NotificationService $notifications) {}

    /**
     * Customer-initiated refund request. Only a paid invoice can be refunded,
     * and only one open request is allowed at a time.
     */
    public function request(Invoice $invoice, User $customer, string $reason): Invoice
    {
        if ($invoice->status !== 'paid') {
            abort(422, 'Only a paid invoice can be refunded.');
        }

        if ($invoice->refund_status !== null && $invoice->refund_status !== RefundStatus::Rejected) {
            abort(422, 'A refund is already in progress for this invoice.');
        }

        $invoice->forceFill([
            'refund_status'       => RefundStatus::Requested,
            'refund_reason'       => $reason,
            'refund_amount'       => $invoice->amount,
            'refund_requested_at' => now(),
            'refund_processed_at' => null,
            'refund_processed_by' => null,
        ])->save();

        $this->audit($invoice, $customer, null, RefundStatus::Requested);

        $this->notifications->notifyAdmins(
            'refund_requested',
            'Refund Requested',
            "{$customer->name} requested a refund on invoice {$invoice->invoice_number} (\${$invoice->amount}). Reason: {$reason}",
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number],
        );

        return $invoice->fresh(['user', 'order']);
    }

    /**
     * Admin moves the refund along. `amount` lets an admin approve a partial
     * refund; it defaults to whatever is already recorded.
     */
    public function updateStatus(
        Invoice $invoice,
        RefundStatus $status,
        User $actor,
        ?float $amount = null,
        ?string $reason = null,
    ): Invoice {
        $current = $invoice->refund_status?->value ?? '';

        if (!in_array($status, self::ALLOWED[$current] ?? [], true)) {
            abort(422, $current === RefundStatus::Processed->value
                ? 'This refund has already been processed.'
                : 'That refund transition is not allowed.');
        }

        if ($invoice->status !== 'paid') {
            abort(422, 'Only a paid invoice can be refunded.');
        }

        // A partial refund is fine; refunding more than was paid is not.
        if ($amount !== null && $amount > (float) $invoice->amount) {
            abort(422, 'A refund cannot exceed the amount paid on the invoice.');
        }

        $previous = $invoice->refund_status;

        $isProcessed = $status === RefundStatus::Processed;

        $invoice->forceFill([
            'refund_status'       => $status,
            'refund_amount'       => $amount ?? $invoice->refund_amount ?? $invoice->amount,
            'refund_reason'       => $reason ?? $invoice->refund_reason,
            'refund_requested_at' => $invoice->refund_requested_at ?? now(),
            'refund_processed_at' => $isProcessed ? now() : null,
            'refund_processed_by' => $isProcessed ? $actor->id : null,
        ])->save();

        $this->audit($invoice, $actor, $previous, $status);

        $this->notifications->notifyRefundStatus($invoice, $status);

        return $invoice->fresh(['user', 'order']);
    }

    private function audit(Invoice $invoice, User $actor, ?RefundStatus $from, RefundStatus $to): void
    {
        if (!$invoice->order_id) {
            return;
        }

        OrderAuditLog::create([
            'order_id'   => $invoice->order_id,
            'user_id'    => $actor->id,
            'action'     => 'refund_updated',
            'old_values' => ['refund_status' => $from?->value],
            'new_values' => [
                'refund_status'  => $to->value,
                'refund_amount'  => $invoice->refund_amount,
                'invoice_number' => $invoice->invoice_number,
            ],
        ]);
    }
}
