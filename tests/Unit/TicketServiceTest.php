<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Services\NotificationService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(?NotificationService $notifications = null): TicketService
    {
        $notifications ??= Mockery::mock(NotificationService::class)->shouldIgnoreMissing();

        return new TicketService($notifications);
    }

    // ── create() ─────────────────────────────────────────────────────────────

    public function test_create_persists_ticket_with_correct_fields(): void
    {
        $user    = $this->createUser();
        $service = $this->makeService();

        $service->create($user, 'My Subject', 'My body text');

        $this->assertDatabaseHas('tickets', [
            'user_id' => $user->id,
            'subject' => 'My Subject',
            'status'  => 'open',
        ]);
    }

    public function test_create_generates_tkt_number(): void
    {
        $user   = $this->createUser();
        $ticket = $this->makeService()->create($user, 'Sub', 'Body');

        $this->assertMatchesRegularExpression('/^TKT-\d{5}$/', $ticket->ticket_number);
    }

    public function test_create_adds_first_message_as_non_staff(): void
    {
        $user = $this->createUser();
        $this->makeService()->create($user, 'Sub', 'Hello support');

        $this->assertDatabaseHas('ticket_messages', [
            'body'           => 'Hello support',
            'is_staff_reply' => 0,
            'user_id'        => $user->id,
        ]);
    }

    public function test_create_calls_send_ticket_created_notification(): void
    {
        $user          = $this->createUser();
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('sendTicketCreated')->once();
        $notifications->shouldReceive('notifyAdmins')->once();
        $service = new TicketService($notifications);

        $service->create($user, 'Sub', 'Body');
    }

    // ── reply() ───────────────────────────────────────────────────────────────

    public function test_reply_persists_message(): void
    {
        $user   = $this->createUser();
        $ticket = Ticket::factory()->open()->create(['user_id' => $user->id]);

        $this->makeService()->reply($ticket, $user, 'My reply');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body'      => 'My reply',
        ]);
    }

    public function test_admin_reply_transitions_open_ticket_to_in_progress(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->open()->create();

        $this->makeService()->reply($ticket, $admin, 'Hello');

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_admin_reply_does_not_change_already_in_progress_ticket(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->create(['status' => 'in_progress']);

        $this->makeService()->reply($ticket, $admin, 'Follow up');

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_user_reply_does_not_change_ticket_status(): void
    {
        $user   = $this->createUser();
        $ticket = Ticket::factory()->open()->create(['user_id' => $user->id]);

        $this->makeService()->reply($ticket, $user, 'User reply');

        $this->assertSame('open', $ticket->fresh()->status);
    }

    public function test_reply_calls_send_ticket_reply_notification(): void
    {
        $admin         = $this->createAdmin();
        $ticket        = Ticket::factory()->open()->create();
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('sendTicketReply')->once();
        $service = new TicketService($notifications);

        $service->reply($ticket, $admin, 'Hello');
    }

    // ── updateStatus() ────────────────────────────────────────────────────────

    public function test_update_status_changes_ticket_status(): void
    {
        $ticket = Ticket::factory()->open()->create();

        $this->makeService()->updateStatus($ticket, 'resolved');

        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    // ── list() ────────────────────────────────────────────────────────────────

    public function test_list_returns_only_own_tickets(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        Ticket::factory()->count(3)->create(['user_id' => $userA->id]);
        Ticket::factory()->count(2)->create(['user_id' => $userB->id]);

        $result = $this->makeService()->list($userA);

        $this->assertCount(3, $result);
        $result->each(fn ($t) => $this->assertSame($userA->id, $t->user_id));
    }

    // ── listAll() ─────────────────────────────────────────────────────────────

    public function test_list_all_returns_every_ticket(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        Ticket::factory()->count(2)->create(['user_id' => $userA->id]);
        Ticket::factory()->count(3)->create(['user_id' => $userB->id]);

        $result = $this->makeService()->listAll();

        $this->assertCount(5, $result);
    }

    // ── authorize() ───────────────────────────────────────────────────────────

    public function test_authorize_allows_ticket_owner(): void
    {
        $user   = $this->createUser();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->makeService()->authorize($user, $ticket);

        $this->assertTrue(true); // no exception
    }

    public function test_authorize_allows_admin(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->create();

        $this->makeService()->authorize($admin, $ticket);

        $this->assertTrue(true);
    }

    public function test_authorize_throws_403_for_non_owner(): void
    {
        $this->expectException(HttpException::class);

        $other  = $this->createUser();
        $ticket = Ticket::factory()->create();

        $this->makeService()->authorize($other, $ticket);
    }

    // ── stats() ───────────────────────────────────────────────────────────────

    public function test_stats_returns_correct_counts(): void
    {
        Ticket::factory()->count(2)->create(['status' => 'open']);
        Ticket::factory()->count(1)->create(['status' => 'in_progress']);
        Ticket::factory()->count(1)->create(['status' => 'resolved', 'updated_at' => now()]);
        Ticket::factory()->count(1)->create(['status' => 'resolved', 'updated_at' => now()->subDays(2)]);

        $stats = $this->makeService()->stats();

        $this->assertSame(5, $stats['total']);
        $this->assertSame(2, $stats['open']);
        $this->assertSame(1, $stats['in_progress']);
        $this->assertSame(1, $stats['resolved_today']);
    }
}
