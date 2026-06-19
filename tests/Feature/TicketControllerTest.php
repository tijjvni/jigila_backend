<?php

namespace Tests\Feature;

use App\Mail\TicketCreatedMail;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── Auth guard ────────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_tickets(): void
    {
        $this->getJson('/api/v1/tickets')->assertStatus(401);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_user_can_list_own_tickets(): void
    {
        $user  = $this->createUser();
        $other = $this->createUser();
        Ticket::factory()->count(2)->create(['user_id' => $user->id]);
        Ticket::factory()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── store ─────────────────────────────────────────────────────────────────

    public function test_user_can_create_ticket(): void
    {
        Mail::fake();
        $user = $this->createUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/tickets', [
                'subject' => 'Shipment delay',
                'body'    => 'My car has not moved in 2 weeks.',
            ])
            ->assertStatus(201);

        $response->assertJsonPath('data.subject', 'Shipment delay');
        $this->assertMatchesRegularExpression('/^TKT-\d{5}$/', $response->json('data.ticket_number'));

        $this->assertDatabaseHas('tickets', [
            'user_id' => $user->id,
            'subject' => 'Shipment delay',
        ]);

        Mail::assertQueued(TicketCreatedMail::class, fn ($m) => $m->hasTo($user->email));
    }

    public function test_create_ticket_requires_subject_and_body(): void
    {
        $this->actingAs($this->createUser())
            ->postJson('/api/v1/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'body']);
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function test_user_can_view_own_ticket_with_messages(): void
    {
        $user   = $this->createUser();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);
        TicketMessage::factory()->count(2)->create(['ticket_id' => $ticket->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $ticket->id)
            ->assertJsonStructure(['data' => ['messages']]);
    }

    public function test_user_cannot_view_another_users_ticket(): void
    {
        $other  = $this->createUser();
        $ticket = Ticket::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->createUser())
            ->getJson("/api/v1/tickets/{$ticket->id}")
            ->assertStatus(403);
    }

    // ── reply ─────────────────────────────────────────────────────────────────

    public function test_user_can_reply_to_own_ticket(): void
    {
        $user   = $this->createUser();
        $ticket = Ticket::factory()->open()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/tickets/{$ticket->id}/messages", ['body' => 'Any update?'])
            ->assertStatus(201)
            ->assertJsonPath('data.body', 'Any update?');

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'body'      => 'Any update?',
        ]);
    }

    public function test_user_cannot_reply_to_another_users_ticket(): void
    {
        $other  = $this->createUser();
        $ticket = Ticket::factory()->open()->create(['user_id' => $other->id]);

        $this->actingAs($this->createUser())
            ->postJson("/api/v1/tickets/{$ticket->id}/messages", ['body' => 'Sneaky'])
            ->assertStatus(403);
    }

    public function test_reply_requires_body(): void
    {
        $user   = $this->createUser();
        $ticket = Ticket::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/tickets/{$ticket->id}/messages", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }
}
