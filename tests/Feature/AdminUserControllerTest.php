<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_admin_users_endpoints(): void
    {
        $this->getJson('/api/v1/admin/users')->assertStatus(401);
        $this->postJson('/api/v1/admin/users', [])->assertStatus(401);
    }

    public function test_regular_user_cannot_access_admin_users_endpoints(): void
    {
        $user = $this->regularUser();

        $this->actingAs($user)->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/v1/admin/users', [])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_admin_can_list_all_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);

        // 3 regular users + the admin itself
        $this->assertEquals(4, $response->json('meta.total'));
    }

    public function test_admin_can_filter_users_by_type_admin(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create(['role' => 'user']);
        User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?type=admin');

        // 2 admins total (the fixture admin + the one we created)
        $this->assertEquals(2, $response->json('meta.total'));
    }

    public function test_admin_can_filter_users_by_status_archived(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create(['status' => 'active']);
        User::factory()->create(['status' => 'archived']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?status=archived');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_admin_can_search_users_by_name(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Alice Wonderland']);
        User::factory()->create(['name' => 'Bob Builder']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?search=Alice');

        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertStringContainsString('Alice', $response->json('data.0.name'));
    }

    public function test_admin_can_search_users_by_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'unique-search@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?search=unique-search');

        $this->assertEquals(1, $response->json('meta.total'));
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_admin_can_create_a_user(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'email'      => 'jane.doe@example.com',
            'phone'      => '08012345678',
            'role'       => 'user',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', 'jane.doe@example.com')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('users', ['email' => 'jane.doe@example.com', 'name' => 'Jane Doe']);
    }

    public function test_store_user_requires_all_fields(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'phone', 'role']);
    }

    public function test_store_user_rejects_duplicate_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'first_name' => 'Test',
                'last_name'  => 'User',
                'email'      => 'taken@example.com',
                'phone'      => '08000000000',
                'role'       => 'user',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_user_rejects_invalid_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'first_name' => 'Test',
                'last_name'  => 'User',
                'email'      => 'test@example.com',
                'phone'      => '08000000000',
                'role'       => 'superadmin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_admin_can_view_a_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/users/{$user->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', (string) $user->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'role', 'status', 'admin_roles']]);
    }

    public function test_show_returns_404_for_nonexistent_user(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/users/99999')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_admin_can_update_user_name(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'first_name' => 'New',
                'last_name'  => 'Name',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_admin_can_update_user_role(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", ['role' => 'admin'])
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_update_user_rejects_duplicate_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", ['email' => 'taken@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_user_allows_same_email_for_current_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", ['email' => 'mine@example.com'])
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Archive
    // -------------------------------------------------------------------------

    public function test_admin_can_archive_a_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/archive")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'archived');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'archived']);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_a_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/users/{$user->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'User deleted successfully.']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
