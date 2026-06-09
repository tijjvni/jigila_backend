<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;

class TicketService
{
    public function __construct(private NotificationService $notifications) {}

    public function list(User $user): Collection
    {
        return Ticket::with(['user'])
            ->withCount('messages')
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    public function listAll(): Collection
    {
        return Ticket::with(['user'])
            ->withCount('messages')
            ->latest()
            ->get();
    }

    public function stats(): array
    {
        return [
            'total'          => Ticket::count(),
            'open'           => Ticket::where('status', 'open')->count(),
            'in_progress'    => Ticket::where('status', 'in_progress')->count(),
            'resolved_today' => Ticket::where('status', 'resolved')
                ->whereDate('updated_at', today())
                ->count(),
        ];
    }

    public function create(User $user, string $subject, string $body): Ticket
    {
        $ticket = Ticket::create([
            'user_id'       => $user->id,
            'ticket_number' => $this->generateTicketNumber(),
            'subject'       => $subject,
            'status'        => 'open',
        ]);

        $ticket->messages()->create([
            'user_id'        => $user->id,
            'body'           => $body,
            'is_staff_reply' => false,
        ]);

        $this->notifications->sendTicketCreated($ticket->load('user'));

        return $ticket;
    }

    public function find(Ticket $ticket): Ticket
    {
        return $ticket->load(['messages.user', 'user']);
    }

    public function reply(Ticket $ticket, User $sender, string $body): TicketMessage
    {
        $isStaff = $sender->role === 'admin';

        $message = $ticket->messages()->create([
            'user_id'        => $sender->id,
            'body'           => $body,
            'is_staff_reply' => $isStaff,
        ]);

        if ($isStaff && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        $ticket->loadMissing('user');
        $recipient = $isStaff ? $ticket->user : $this->findAdminRecipient();

        if ($recipient) {
            $this->notifications->sendTicketReply($ticket, $message->load('user'), $recipient);
        }

        return $message;
    }

    public function updateStatus(Ticket $ticket, string $status): Ticket
    {
        $ticket->update(['status' => $status]);

        return $ticket->fresh();
    }

    public function authorize(User $user, Ticket $ticket): void
    {
        if ($user->role === 'admin') {
            return;
        }

        if ($ticket->user_id !== $user->id) {
            throw new HttpResponseException(
                response()->json(['message' => 'Forbidden.'], 403)
            );
        }
    }

    private function generateTicketNumber(): string
    {
        $count = Ticket::count() + 1;

        return 'TKT-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    private function findAdminRecipient(): ?User
    {
        return User::where('role', 'admin')->first();
    }
}
