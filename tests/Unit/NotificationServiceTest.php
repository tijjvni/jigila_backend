<?php

namespace Tests\Unit;

use App\Mail\TicketCreatedMail;
use App\Mail\TicketReplyMail;
use App\Mail\WelcomeMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
    }

    // ── sendWelcome ───────────────────────────────────────────────────────────

    public function test_send_welcome_sends_to_user_email(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $this->service->sendWelcome($user);

        Mail::assertSent(WelcomeMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_send_welcome_passes_user_to_mailable(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $this->service->sendWelcome($user);

        Mail::assertSent(WelcomeMail::class, fn ($m) => $m->user->id === $user->id);
    }

    public function test_send_welcome_swallows_mail_exception(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        Log::spy();

        $this->service->sendWelcome($this->createUser());

        // No exception thrown — test passes if we reach here
        $this->assertTrue(true);
    }

    // ── sendTicketCreated ─────────────────────────────────────────────────────

    public function test_send_ticket_created_sends_to_ticket_owner(): void
    {
        Mail::fake();
        $ticket = Ticket::factory()->open()->create();
        $ticket->load('user');

        $this->service->sendTicketCreated($ticket);

        Mail::assertSent(TicketCreatedMail::class, fn ($m) => $m->hasTo($ticket->user->email));
    }

    public function test_send_ticket_created_passes_ticket_to_mailable(): void
    {
        Mail::fake();
        $ticket = Ticket::factory()->open()->create();
        $ticket->load('user');

        $this->service->sendTicketCreated($ticket);

        Mail::assertSent(TicketCreatedMail::class, fn ($m) => $m->ticket->id === $ticket->id);
    }

    public function test_send_ticket_created_swallows_exception(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        Log::spy();

        $ticket = Ticket::factory()->open()->create();
        $ticket->load('user');

        $this->service->sendTicketCreated($ticket);

        $this->assertTrue(true);
    }

    // ── sendTicketReply ───────────────────────────────────────────────────────

    public function test_send_ticket_reply_sends_to_given_recipient(): void
    {
        Mail::fake();
        $ticket    = Ticket::factory()->open()->create();
        $ticket->load('user');
        $recipient = $this->createUser();
        $message   = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $this->createUser()->id,
        ]);
        $message->load('user');

        $this->service->sendTicketReply($ticket, $message, $recipient);

        Mail::assertSent(TicketReplyMail::class, fn ($m) => $m->hasTo($recipient->email));
    }

    public function test_send_ticket_reply_passes_ticket_and_message_to_mailable(): void
    {
        Mail::fake();
        $ticket  = Ticket::factory()->open()->create();
        $ticket->load('user');
        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $this->createUser()->id,
        ]);
        $message->load('user');

        $this->service->sendTicketReply($ticket, $message, $ticket->user);

        Mail::assertSent(TicketReplyMail::class, function ($m) use ($ticket, $message) {
            return $m->ticket->id === $ticket->id && $m->message->id === $message->id;
        });
    }

    public function test_send_ticket_reply_swallows_exception(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        Log::spy();

        $ticket  = Ticket::factory()->open()->create();
        $ticket->load('user');
        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $this->createUser()->id,
        ]);
        $message->load('user');

        $this->service->sendTicketReply($ticket, $message, $ticket->user);

        $this->assertTrue(true);
    }
}
