<?php

namespace Tests\Feature;

use App\Mail\TicketReplyMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminTicketControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── Auth / role guards ────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_admin_tickets(): void
    {
        $this->getJson('/api/admin/tickets')->assertStatus(401);
    }

    public function test_non_admin_cannot_access_admin_tickets(): void
    {
        $this->actingAs($this->createUser())
            ->getJson('/api/admin/tickets')
            ->assertStatus(403);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_list_all_tickets_with_stats(): void
    {
        $admin = $this->createAdmin();
        Ticket::factory()->count(2)->create(['status' => 'open']);
        Ticket::factory()->count(1)->create(['status' => 'in_progress']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/tickets')
            ->assertOk();

        $response->assertJsonStructure([
            'data'  => [['id', 'ticket_number', 'subject', 'status']],
            'stats' => ['total', 'open', 'in_progress', 'resolved_today'],
        ]);

        $this->assertSame(3, $response->json('stats.total'));
        $this->assertSame(2, $response->json('stats.open'));
        $this->assertSame(1, $response->json('stats.in_progress'));
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_view_any_ticket_with_messages(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->create();
        TicketMessage::factory()->count(2)->create(['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id]);

        $this->actingAs($admin)
            ->getJson("/api/admin/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $ticket->id)
            ->assertJsonStructure(['data' => ['messages']]);
    }

    // ── reply ─────────────────────────────────────────────────────────────────

    public function test_admin_can_reply_to_any_users_ticket(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->open()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/tickets/{$ticket->id}/messages", ['body' => 'We are looking into it.'])
            ->assertStatus(201)
            ->assertJsonPath('data.is_staff_reply', true);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id'      => $ticket->id,
            'body'           => 'We are looking into it.',
            'is_staff_reply' => 1,
        ]);
    }

    public function test_admin_reply_transitions_open_ticket_to_in_progress(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->open()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/tickets/{$ticket->id}/messages", ['body' => 'Handling now']);

        $this->assertSame('in_progress', $ticket->fresh()->status);
    }

    public function test_admin_reply_sends_ticket_reply_mail(): void
    {
        Mail::fake();
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->open()->create();
        $ticket->load('user');

        $this->actingAs($admin)
            ->postJson("/api/admin/tickets/{$ticket->id}/messages", ['body' => 'Hi there']);

        Mail::assertSent(TicketReplyMail::class, fn ($m) => $m->hasTo($ticket->user->email));
    }

    // ── updateStatus ──────────────────────────────────────────────────────────

    public function test_admin_can_resolve_ticket(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->open()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $this->assertSame('resolved', $ticket->fresh()->status);
    }

    public function test_update_status_rejects_invalid_values(): void
    {
        $admin  = $this->createAdmin();
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin)
            ->patchJson("/api/admin/tickets/{$ticket->id}/status", ['status' => 'not_a_real_status'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
