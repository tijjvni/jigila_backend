<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly TicketMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New Reply on Ticket {$this->ticket->ticket_number}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket-reply');
    }
}
