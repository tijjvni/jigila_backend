<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ─────────────────────────────────────────────────────────────

    public function test_user_can_list_own_notifications(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Notification::factory()->count(3)->create(['user_id' => $user->id]);
        Notification::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_notifications_do_not_include_other_users(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Notification::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_guest_cannot_list_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_notification_list_returns_expected_fields(): void
    {
        $user = User::factory()->create();
        Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'type', 'title', 'body', 'read', 'read_at', 'created_at']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'unread_count'],
            ]);
    }

    public function test_unread_count_reflects_unread_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->read()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.unread_count', 2);
    }

    // ─── markRead ──────────────────────────────────────────────────────────

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user         = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id, 'read_at' => null]);

        $response = $this->actingAs($user)->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonFragment(['read' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_already_read_notification_is_idempotent(): void
    {
        $user         = User::factory()->create();
        $notification = Notification::factory()->read()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJsonFragment(['read' => true]);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user         = User::factory()->create();
        $other        = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(403);
    }

    public function test_guest_cannot_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create();

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")->assertStatus(401);
    }

    // ─── markAllRead ───────────────────────────────────────────────────────

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'read_at' => null]);

        $response = $this->actingAs($user)->patchJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'All notifications marked as read.']);

        $this->assertEquals(0, $user->notifications()->whereNull('read_at')->count());
    }

    public function test_mark_all_read_only_affects_current_user(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Notification::factory()->create(['user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->create(['user_id' => $other->id, 'read_at' => null]);

        $this->actingAs($user)->patchJson('/api/v1/notifications/read-all')->assertStatus(200);

        $this->assertEquals(0, $user->notifications()->whereNull('read_at')->count());
        $this->assertEquals(1, $other->notifications()->whereNull('read_at')->count());
    }

    public function test_mark_all_read_with_no_notifications_succeeds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/v1/notifications/read-all')
            ->assertStatus(200);
    }

    public function test_guest_cannot_mark_all_as_read(): void
    {
        $this->patchJson('/api/v1/notifications/read-all')->assertStatus(401);
    }
}
