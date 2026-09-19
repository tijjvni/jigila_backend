<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Deadline reminder and overdue notice (spec 4).
 *
 * `$stage` is `48`, `24`, `6` (hours remaining), `expiry` (the deadline itself)
 * or `overdue` (deadline lapsed, shipment held). One template covers all five —
 * the urgency of the copy and the header colour key off the stage.
 */
class PaymentDeadlineMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $stage,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->stage) {
            'overdue' => "Payment Overdue — {$this->invoice->invoice_number}",
            'expiry'  => "Payment Deadline Reached — {$this->invoice->invoice_number}",
            default   => "Payment Due in {$this->stage} Hours — {$this->invoice->invoice_number}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-deadline',
            with: [
                'isOverdue'  => $this->stage === 'overdue',
                'isCritical' => in_array($this->stage, ['overdue', 'expiry', '6'], true),
            ],
        );
    }
}
