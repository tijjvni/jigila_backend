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
    private function createInApp(User $user, string $type, string $title, string $body, array $data = []): void
    {
        $user->notifications()->create([
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
            'data'  => $data ?: null,
        ]);
    }

    public function sendWelcome(User $user): void
    {
        try {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        $this->createInApp(
            $user,
            'welcome',
            'Welcome to Jigila!',
            'Your account has been created successfully. Start tracking your vehicle imports today.',
        );
    }

    public function sendTicketCreated(Ticket $ticket): void
    {
        try {
            $ticket->loadMissing('user');
            Mail::to($ticket->user->email)->queue(new TicketCreatedMail($ticket));
        } catch (\Throwable $e) {
            Log::error('Failed to send ticket created email', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }

    public function notifyAdmins(string $type, string $title, string $body, array $data = []): void
    {
        User::where('role', 'admin')->get()->each(
            fn (User $admin) => $this->createInApp($admin, $type, $title, $body, $data)
        );
    }

    public function sendTicketReply(Ticket $ticket, TicketMessage $message, User $recipient): void
    {
        try {
            $message->loadMissing('user');
            Mail::to($recipient->email)->queue(new TicketReplyMail($ticket, $message));
        } catch (\Throwable $e) {
            Log::error('Failed to send ticket reply email', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }
}
