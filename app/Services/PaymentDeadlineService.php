<?php

namespace App\Services;

use App\Enums\DeadlineExtensionStatus;
use App\Enums\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Payment deadlines, shipment holds and one-time deadline extensions (spec 4).
 *
 * Three things are worth knowing before changing anything here.
 *
 * **The invoice never leaves `pending` when it goes overdue.** Overdue lives on
 * `invoices.overdue_at` and on the *order*, never on `invoices.status`. The
 * Paystack webhook matches on `where('status', 'pending')`, so introducing an
 * `overdue` invoice status would make every payment that lands after a deadline
 * silently fail to register.
 *
 * **The order's real stage is stashed, not lost.** `status` is overwritten with
 * `payment_overdue` so the customer sees the hold wherever a status is
 * rendered, while `status_before_hold` keeps the pipeline stage so paying puts
 * the order back exactly where it was.
 *
 * **Stage reminders are consumed, not timed.** Each of the "48h / 24h / 6h / at
 * expiry" notices is recorded in `reminder_stages_sent` as it goes out, so the
 * sweep is safe at any frequency and a scheduler outage causes a late notice,
 * never a duplicate one.
 */
class PaymentDeadlineService
{
    /** Transitions an admin may make on an extension, keyed by current state. */
    private const ALLOWED = [
        // An admin may grant an extension nobody asked for.
        ''                                      => [DeadlineExtensionStatus::Approved, DeadlineExtensionStatus::Rejected],
        DeadlineExtensionStatus::Requested->value => [DeadlineExtensionStatus::Approved, DeadlineExtensionStatus::Rejected],
        // One-time: an approved extension is the end of the road.
        DeadlineExtensionStatus::Approved->value  => [],
        DeadlineExtensionStatus::Rejected->value  => [DeadlineExtensionStatus::Approved],
    ];

    public function __construct(
        private NotificationService $notifications,
        private LateFeeService $lateFees,
    ) {}

    /**
     * Default deadline length: admin setting over config default.
     */
    public function defaultDeadlineHours(): int
    {
        return (int) Setting::get('payment_deadline_hours', config('orders.payment_deadline_hours', 72));
    }

    public function maxExtensionHours(): int
    {
        return (int) Setting::get('deadline_extension_max_hours', config('orders.deadline_extension.max_hours', 72));
    }

    // ── Reminder stages ──────────────────────────────────────────────────────

    /**
     * Stage keys whose trigger time has arrived and which have not gone out yet.
     *
     * A stage whose trigger fell before the invoice even existed is *consumed*
     * rather than fired: on a 24-hour deadline the "48 hours left" notice would
     * otherwise land seconds after the invoice email.
     *
     * @return array{due: string[], stale: string[]}
     */
    public function pendingStages(Invoice $invoice): array
    {
        $sent  = $invoice->reminder_stages_sent ?? [];
        $due   = [];
        $stale = [];

        if ($invoice->payment_due_at === null || $invoice->status !== 'pending') {
            return ['due' => $due, 'stale' => $stale];
        }

        foreach (config('orders.payment_reminder_offsets_hours', [48, 24, 6, 0]) as $offset) {
            $key = $this->stageKey((int) $offset);

            if (in_array($key, $sent, true)) {
                continue;
            }

            $triggersAt = $invoice->payment_due_at->copy()->subHours((int) $offset);

            // `<=`, not `<`: on a deadline exactly one offset long the stage
            // triggers at the moment of issuance, which would put "payment due
            // in 24 hours" in the customer's inbox alongside the invoice itself.
            if ($triggersAt->lessThanOrEqualTo($invoice->created_at)) {
                $stale[] = $key;

                continue;
            }

            if ($triggersAt->lessThanOrEqualTo(now())) {
                $due[] = $key;
            }
        }

        return ['due' => $due, 'stale' => $stale];
    }

    /**
     * Send whatever stage notices are due. Returns the stage keys actually sent.
     *
     * @return string[]
     */
    public function sendDueReminders(Invoice $invoice): array
    {
        ['due' => $due, 'stale' => $stale] = $this->pendingStages($invoice);

        if ($due === [] && $stale === []) {
            return [];
        }

        foreach ($due as $stage) {
            $this->notifications->sendPaymentDeadlineReminder($invoice, $stage);
        }

        // Stale stages are recorded alongside sent ones so they are evaluated
        // once and never looked at again.
        $invoice->forceFill([
            'reminder_stages_sent' => array_values(array_unique(
                array_merge($invoice->reminder_stages_sent ?? [], $due, $stale)
            )),
            'last_reminded_at' => $due !== [] ? now() : $invoice->last_reminded_at,
            'reminder_count'   => $invoice->reminder_count + count($due),
        ])->save();

        return $due;
    }

    /**
     * `48` / `24` / `6` hours before, or `expiry` for the deadline itself.
     */
    public function stageKey(int $offsetHours): string
    {
        return $offsetHours === 0 ? 'expiry' : (string) $offsetHours;
    }

    // ── Expiry and the shipment hold ─────────────────────────────────────────

    /**
     * Deadline lapsed: stamp the invoice, hold the shipment, tell everyone.
     */
    public function markOverdue(Invoice $invoice): bool
    {
        if ($invoice->status !== 'pending' || $invoice->overdue_at !== null) {
            return false;
        }

        if ($invoice->payment_due_at === null || $invoice->payment_due_at->isFuture()) {
            return false;
        }

        $invoice->forceFill(['overdue_at' => now()])->save();

        // Accrue before notifying so the overdue notice can quote the fee the
        // customer has already picked up rather than a figure that appears an
        // hour later out of nowhere.
        $this->lateFees->accrue($invoice);

        $invoice->loadMissing('order');
        $order = $invoice->order;

        if ($order && $this->holdable($order)) {
            $this->placeHold($order, $invoice);
        }

        $this->notifications->notifyPaymentOverdue($invoice);

        return true;
    }

    /**
     * A cancelled or delivered order is past the point where holding a shipment
     * means anything, and an order already on hold must not have
     * `status_before_hold` overwritten with `payment_overdue` by a second
     * overdue invoice.
     */
    private function holdable(Order $order): bool
    {
        return !in_array($order->status, [
            OrderStatus::Cancelled,
            OrderStatus::Delivered,
            OrderStatus::PaymentOverdue,
        ], true);
    }

    private function placeHold(Order $order, Invoice $invoice): void
    {
        $previous = $order->status;

        $order->forceFill([
            'status'               => OrderStatus::PaymentOverdue,
            'status_before_hold'   => $previous->value,
            'shipment_hold'        => true,
            'shipment_hold_reason' => "Payment deadline lapsed on invoice {$invoice->invoice_number}.",
            'shipment_held_at'     => now(),
        ])->save();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => null,
            'action'     => 'status_changed',
            'old_values' => ['status' => $previous->value],
            'new_values' => [
                'status'         => OrderStatus::PaymentOverdue->value,
                'shipment_hold'  => true,
                'reason'         => 'payment_deadline_lapsed',
                'invoice_number' => $invoice->invoice_number,
            ],
        ]);
    }

    /**
     * Lift the hold and put the order back where it was.
     *
     * The hold belongs to the *order*, not to one invoice, so it only lifts
     * once no other unpaid invoice on that order is still overdue — otherwise
     * paying one of two lapsed invoices would release a shipment that is still
     * short of money.
     */
    public function releaseHold(Invoice $invoice, string $reason, ?User $actor = null): bool
    {
        $invoice->loadMissing('order');
        $order = $invoice->order;

        if (!$order || !$order->shipment_hold) {
            return false;
        }

        $stillOverdue = Invoice::where('order_id', $order->id)
            ->where('id', '!=', $invoice->id)
            ->where('status', 'pending')
            ->whereNotNull('overdue_at')
            ->exists();

        if ($stillOverdue) {
            return false;
        }

        $restored = $order->status_before_hold
            ? (OrderStatus::tryFrom($order->status_before_hold) ?? OrderStatus::Processing)
            : OrderStatus::Processing;

        $order->forceFill([
            'status'               => $restored,
            'status_before_hold'   => null,
            'shipment_hold'        => false,
            'shipment_hold_reason' => null,
            'shipment_held_at'     => null,
        ])->save();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor?->id,
            'action'     => 'status_changed',
            'old_values' => ['status' => OrderStatus::PaymentOverdue->value, 'shipment_hold' => true],
            'new_values' => [
                'status'        => $restored->value,
                'shipment_hold' => false,
                'reason'        => $reason,
            ],
        ]);

        $this->notifications->notifyShipmentHoldReleased($order, $reason);

        return true;
    }

    /**
     * Admin lifts a hold by hand — a cleared bank transfer that never reached
     * Paystack, a goodwill release, a mistake.
     */
    public function releaseHoldForOrder(Order $order, User $actor, string $reason): Order
    {
        if (!$order->shipment_hold) {
            abort(422, 'This order is not on hold.');
        }

        $restored = $order->status_before_hold
            ? (OrderStatus::tryFrom($order->status_before_hold) ?? OrderStatus::Processing)
            : OrderStatus::Processing;

        $order->forceFill([
            'status'               => $restored,
            'status_before_hold'   => null,
            'shipment_hold'        => false,
            'shipment_hold_reason' => null,
            'shipment_held_at'     => null,
        ])->save();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor->id,
            'action'     => 'status_changed',
            'old_values' => ['status' => OrderStatus::PaymentOverdue->value, 'shipment_hold' => true],
            'new_values' => ['status' => $restored->value, 'shipment_hold' => false, 'reason' => $reason],
        ]);

        $this->notifications->notifyShipmentHoldReleased($order, $reason);

        return $order->load(['user', 'invoices']);
    }

    // ── Deadline extensions ──────────────────────────────────────────────────

    /**
     * Customer asks for more time. One live request per invoice; a declined
     * request may be replaced, an approved one may not.
     */
    public function requestExtension(Invoice $invoice, User $customer, int $hours, string $reason): Invoice
    {
        if ($invoice->status !== 'pending') {
            abort(422, 'Only an unpaid invoice can have its deadline extended.');
        }

        if ($invoice->payment_due_at === null) {
            abort(422, 'This invoice has no payment deadline to extend.');
        }

        if ($invoice->extension_status === DeadlineExtensionStatus::Approved) {
            abort(422, 'This invoice has already had its one deadline extension.');
        }

        if ($invoice->extension_status === DeadlineExtensionStatus::Requested) {
            abort(422, 'A deadline extension is already awaiting review on this invoice.');
        }

        $max = $this->maxExtensionHours();

        if ($hours > $max) {
            abort(422, "A deadline extension cannot exceed {$max} hours.");
        }

        $invoice->forceFill([
            'extension_status'          => DeadlineExtensionStatus::Requested,
            'extension_requested_hours' => $hours,
            'extension_reason'          => $reason,
            'extension_requested_at'    => now(),
            'extension_reviewed_at'     => null,
            'extension_reviewed_by'     => null,
            'extension_granted_hours'   => null,
        ])->save();

        $this->notifications->notifyAdmins(
            'deadline_extension_requested',
            'Deadline Extension Requested',
            "{$customer->name} asked for {$hours} more hours to pay invoice {$invoice->invoice_number}. Reason: {$reason}",
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number],
        );

        return $invoice->fresh(['user', 'order']);
    }

    /**
     * Admin approves or declines.
     *
     * Approving moves `payment_due_at` forward from whichever is later — the
     * old deadline or now — so an extension granted after expiry still buys the
     * customer the full hours rather than landing in the past. It re-arms every
     * reminder stage against the new deadline and lifts any hold.
     *
     * Late fees already accrued are **frozen, not erased**: the extension
     * forgives further accrual, not the days the customer was already late. An
     * admin who judges the delay to be Jigila's fault passes `$waiveAccrued`.
     */
    public function reviewExtension(
        Invoice $invoice,
        DeadlineExtensionStatus $status,
        User $actor,
        ?int $grantedHours = null,
        ?string $note = null,
        bool $waiveAccrued = false,
    ): Invoice {
        $current = $invoice->extension_status?->value ?? '';

        if (!in_array($status, self::ALLOWED[$current] ?? [], true)) {
            abort(422, $current === DeadlineExtensionStatus::Approved->value
                ? 'This invoice has already had its one deadline extension.'
                : 'That extension transition is not allowed.');
        }

        if ($invoice->status !== 'pending') {
            abort(422, 'Only an unpaid invoice can have its deadline extended.');
        }

        if ($status === DeadlineExtensionStatus::Rejected) {
            $invoice->forceFill([
                'extension_status'      => $status,
                'extension_reason'      => $note ?? $invoice->extension_reason,
                'extension_reviewed_at' => now(),
                'extension_reviewed_by' => $actor->id,
            ])->save();

            $this->notifications->notifyExtensionReviewed($invoice, $status);

            return $invoice->fresh(['user', 'order']);
        }

        $hours = $grantedHours ?? $invoice->extension_requested_hours ?? $this->defaultDeadlineHours();
        $max   = $this->maxExtensionHours();

        if ($hours < 1 || $hours > $max) {
            abort(422, "A deadline extension must be between 1 and {$max} hours.");
        }

        $from   = $invoice->payment_due_at ?? now();
        $newDue = ($from->isPast() ? now() : $from->copy())->addHours($hours);

        $invoice->forceFill([
            'extension_status'        => $status,
            'extension_granted_hours' => $hours,
            'extension_reason'        => $note ?? $invoice->extension_reason,
            'extension_reviewed_at'   => now(),
            'extension_reviewed_by'   => $actor->id,
            'original_payment_due_at' => $invoice->original_payment_due_at ?? $invoice->payment_due_at,
            'payment_due_at'          => $newDue,
            'due_date'                => $newDue->toDateString(),
            // The new deadline gets a fresh set of reminders.
            'reminder_stages_sent'    => null,
            'overdue_at'              => null,
            'late_fee_amount'         => $waiveAccrued ? 0 : $invoice->late_fee_amount,
            'late_fee_days'           => $waiveAccrued ? 0 : $invoice->late_fee_days,
        ])->save();

        $this->auditExtension($invoice, $actor, $hours, $waiveAccrued);

        $this->releaseHold($invoice, 'deadline_extended', $actor);

        $this->notifications->notifyExtensionReviewed($invoice, $status);

        return $invoice->fresh(['user', 'order']);
    }

    /**
     * Admin sets or moves a deadline directly, outside the request flow.
     */
    public function setDeadline(Invoice $invoice, Carbon $dueAt, User $actor): Invoice
    {
        if ($invoice->status !== 'pending') {
            abort(422, 'Only an unpaid invoice has a payment deadline.');
        }

        $previous = $invoice->payment_due_at;

        $invoice->forceFill([
            'payment_due_at'          => $dueAt,
            'due_date'                => $dueAt->toDateString(),
            'deadline_hours'          => (int) ceil($invoice->created_at->diffInHours($dueAt, false)),
            'original_payment_due_at' => $invoice->original_payment_due_at ?? $previous,
            'reminder_stages_sent'    => null,
            'overdue_at'              => $dueAt->isPast() ? $invoice->overdue_at : null,
        ])->save();

        if ($invoice->order_id) {
            OrderAuditLog::create([
                'order_id'   => $invoice->order_id,
                'user_id'    => $actor->id,
                'action'     => 'payment_deadline_updated',
                'old_values' => ['payment_due_at' => $previous?->toIso8601String()],
                'new_values' => [
                    'payment_due_at' => $dueAt->toIso8601String(),
                    'invoice_number' => $invoice->invoice_number,
                ],
            ]);
        }

        if (!$dueAt->isPast()) {
            $this->releaseHold($invoice, 'deadline_moved', $actor);
        }

        $this->notifications->notifyDeadlineChanged($invoice);

        return $invoice->fresh(['user', 'order']);
    }

    private function auditExtension(Invoice $invoice, User $actor, int $hours, bool $waived): void
    {
        if (!$invoice->order_id) {
            return;
        }

        OrderAuditLog::create([
            'order_id'   => $invoice->order_id,
            'user_id'    => $actor->id,
            'action'     => 'deadline_extended',
            'old_values' => ['payment_due_at' => $invoice->original_payment_due_at?->toIso8601String()],
            'new_values' => [
                'payment_due_at'     => $invoice->payment_due_at?->toIso8601String(),
                'granted_hours'      => $hours,
                'late_fees_waived'   => $waived,
                'invoice_number'     => $invoice->invoice_number,
            ],
        ]);
    }
}
