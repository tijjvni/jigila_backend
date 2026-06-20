<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(private NotificationService $notifications) {}

    public function list(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Ticket::with(['user'])
            ->withCount('messages')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function listAll(int $perPage = 20): LengthAwarePaginator
    {
        return Ticket::with(['user'])
            ->withCount('messages')
            ->latest()
            ->paginate($perPage);
    }

    public function stats(): array
    {
        $counts = Ticket::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $resolvedToday = Ticket::where('status', 'resolved')
            ->whereDate('updated_at', today())
            ->count();

        return [
            'total'          => $counts->sum(),
            'open'           => (int) ($counts['open'] ?? 0),
            'in_progress'    => (int) ($counts['in_progress'] ?? 0),
            'resolved_today' => $resolvedToday,
        ];
    }

    public function create(User $user, string $subject, string $body): Ticket
    {
        $ticket = DB::transaction(function () use ($user, $subject, $body) {
            $last         = Ticket::lockForUpdate()->max('id') ?? 0;
            $ticketNumber = 'TKT-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);

            $ticket = Ticket::create([
                'user_id'       => $user->id,
                'ticket_number' => $ticketNumber,
                'subject'       => $subject,
                'status'        => 'open',
            ]);

            $ticket->messages()->create([
                'user_id'        => $user->id,
                'body'           => $body,
                'is_staff_reply' => false,
            ]);

            return $ticket;
        });

        $this->notifications->sendTicketCreated($ticket->load('user'));

        $this->notifications->notifyAdmins(
            'new_ticket',
            'New Support Ticket',
            "Customer {$user->name} opened ticket #{$ticket->ticket_number}: {$subject}",
            ['ticket_id' => $ticket->id],
        );

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

        if ($isStaff) {
            $this->notifications->sendTicketReply($ticket, $message->load('user'), $ticket->user);
        } else {
            $loadedMessage = $message->load('user');
            User::where('role', 'admin')->chunkById(100, function ($admins) use ($ticket, $loadedMessage) {
                foreach ($admins as $admin) {
                    $this->notifications->sendTicketReply($ticket, $loadedMessage, $admin);
                }
            });
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
        if ($user->role !== 'admin' && $ticket->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
    }
}
