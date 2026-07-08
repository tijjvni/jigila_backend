<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/profile')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'phone', 'role']])
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_user_can_update_name_and_phone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', ['name' => 'Updated Name', 'phone' => '09011111111'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '09011111111');
    }

    public function test_user_can_change_email_with_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'email'            => 'new@example.com',
                'current_password' => 'password',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_email_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', ['email' => 'new@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_email_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'email'            => 'new@example.com',
                'current_password' => 'wrong-password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_email_change_resets_verification(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'email'            => 'new@example.com',
                'current_password' => 'password',
            ])
            ->assertStatus(200);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_must_be_unique_across_users(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);
        $user     = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'email'            => 'taken@example.com',
                'current_password' => 'password',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_update_own_email_without_uniqueness_error(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'email'            => 'mine@example.com',
                'current_password' => 'password',
            ])
            ->assertStatus(200);
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/v1/profile', [
                'password'              => 'newpassword',
                'password_confirmation' => 'mismatch',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
