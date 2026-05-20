<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createRole(array $attributes = []): Role
    {
        return Role::create([
            'name'        => 'Test Role ' . uniqid(),
            'description' => 'A test role',
            'permissions' => ['dashboard'],
            ...$attributes,
        ]);
    }

    // -------------------------------------------------------------------------
    // Access control
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_admin_roles_endpoints(): void
    {
        $this->getJson('/api/admin/roles')->assertStatus(401);
        $this->postJson('/api/admin/roles', [])->assertStatus(401);
    }

    public function test_regular_user_cannot_access_admin_roles_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/admin/roles')->assertStatus(403);
        $this->actingAs($user)->postJson('/api/admin/roles', [])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Index
    // -------------------------------------------------------------------------

    public function test_admin_can_list_all_roles(): void
    {
        $admin = $this->admin();
        $this->createRole(['name' => 'Role A']);
        $this->createRole(['name' => 'Role B']);

        $response = $this->actingAs($admin)->getJson('/api/admin/roles');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_roles_list_includes_users_relation(): void
    {
        $admin = $this->admin();
        $this->createRole(['name' => 'Managers']);

        $response = $this->actingAs($admin)->getJson('/api/admin/roles');

        // List always eager-loads users, so 'users' is present (not user_count)
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'permissions', 'users']]]);
    }

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    public function test_admin_can_create_a_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->postJson('/api/admin/roles', [
            'name'        => 'Finance',
            'description' => 'Finance team role',
            'permissions' => ['dashboard', 'budgetReports'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Finance')
            ->assertJsonPath('data.description', 'Finance team role');

        $this->assertDatabaseHas('roles', ['name' => 'Finance']);
    }

    public function test_store_role_requires_name(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/roles', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_role_rejects_duplicate_name(): void
    {
        $admin = $this->admin();
        $this->createRole(['name' => 'Duplicate']);

        $this->actingAs($admin)
            ->postJson('/api/admin/roles', ['name' => 'Duplicate'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_role_rejects_invalid_permission(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/roles', [
                'name'        => 'BadRole',
                'permissions' => ['nonexistent_permission'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }

    public function test_admin_can_assign_users_when_creating_a_role(): void
    {
        $admin = $this->admin();
        $users = User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/roles', [
            'name'              => 'Operations',
            'assigned_user_ids' => $users->pluck('id')->all(),
        ]);

        $response->assertStatus(201);

        $role = Role::where('name', 'Operations')->first();
        $this->assertCount(2, $role->users);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    public function test_admin_can_view_a_role(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Support']);

        $this->actingAs($admin)
            ->getJson("/api/admin/roles/{$role->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Support')
            ->assertJsonStructure(['data' => ['id', 'name', 'description', 'permissions', 'users']]);
    }

    public function test_show_returns_404_for_nonexistent_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/api/admin/roles/99999')
            ->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    public function test_admin_can_update_role_name(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Old Name']);

        $this->actingAs($admin)
            ->putJson("/api/admin/roles/{$role->id}", ['name' => 'New Name'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Name']);
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['permissions' => ['dashboard']]);

        $this->actingAs($admin)
            ->putJson("/api/admin/roles/{$role->id}", ['permissions' => ['kpiTracking', 'userManagement']])
            ->assertStatus(200)
            ->assertJsonPath('data.permissions', ['kpiTracking', 'userManagement']);
    }

    public function test_update_role_rejects_duplicate_name(): void
    {
        $admin  = $this->admin();
        $this->createRole(['name' => 'Taken']);
        $role   = $this->createRole(['name' => 'Mine']);

        $this->actingAs($admin)
            ->putJson("/api/admin/roles/{$role->id}", ['name' => 'Taken'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_role_allows_same_name_for_current_role(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Unchanged']);

        $this->actingAs($admin)
            ->putJson("/api/admin/roles/{$role->id}", ['name' => 'Unchanged'])
            ->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Destroy
    // -------------------------------------------------------------------------

    public function test_admin_can_delete_a_role(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'ToDelete']);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/roles/{$role->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Role deleted successfully.']);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_detaches_its_users(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Detach Me']);
        $user  = User::factory()->create();
        $role->users()->attach($user->id);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/roles/{$role->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('role_user', ['role_id' => $role->id]);
    }

    // -------------------------------------------------------------------------
    // Add / Remove user
    // -------------------------------------------------------------------------

    public function test_admin_can_add_user_to_role(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Editors']);
        $user  = User::factory()->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/roles/{$role->id}/users/{$user->id}")
            ->assertStatus(200);

        $this->assertTrue($role->fresh()->users->contains($user->id));
    }

    public function test_admin_can_remove_user_from_role(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Editors']);
        $user  = User::factory()->create();
        $role->users()->attach($user->id);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/roles/{$role->id}/users/{$user->id}")
            ->assertStatus(200);

        $this->assertFalse($role->fresh()->users->contains($user->id));
    }

    // -------------------------------------------------------------------------
    // Assign (bulk)
    // -------------------------------------------------------------------------

    public function test_admin_can_bulk_assign_role_to_users(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Bulk']);
        $users = User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->postJson('/api/admin/roles/assign', [
                'role_id'  => $role->id,
                'user_ids' => $users->pluck('id')->all(),
            ])
            ->assertStatus(200)
            ->assertJson(['message' => 'Role assigned successfully.']);

        $this->assertCount(3, $role->fresh()->users);
    }

    public function test_assign_requires_role_id_and_user_ids(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/roles/assign', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id', 'user_ids']);
    }

    public function test_assign_rejects_nonexistent_role(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/admin/roles/assign', [
                'role_id'  => 99999,
                'user_ids' => [$user->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_assign_rejects_nonexistent_user_id(): void
    {
        $admin = $this->admin();
        $role  = $this->createRole(['name' => 'Check']);

        $this->actingAs($admin)
            ->postJson('/api/admin/roles/assign', [
                'role_id'  => $role->id,
                'user_ids' => [99999],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0']);
    }
}
