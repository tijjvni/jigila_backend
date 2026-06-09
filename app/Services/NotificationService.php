<?php

namespace App\Services;

use App\Mail\TicketCreatedMail;
use App\Mail\TicketReplyMail;
use App\Mail\WelcomeMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function sendWelcome(User $user): void
    {
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    public function sendTicketCreated(Ticket $ticket): void
    {
        try {
            $ticket->loadMissing('user');
            Mail::to($ticket->user->email)->send(new TicketCreatedMail($ticket));
        } catch (\Throwable $e) {
            Log::error('Failed to send ticket created email', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }

    public function sendTicketReply(Ticket $ticket, TicketMessage $message, User $recipient): void
    {
        try {
            $message->loadMissing('user');
            Mail::to($recipient->email)->send(new TicketReplyMail($ticket, $message));
        } catch (\Throwable $e) {
            Log::error('Failed to send ticket reply email', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }
}
