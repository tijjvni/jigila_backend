<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'phone'                 => '08012345678',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)->assertJson(['message' => 'Registration successful.']);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }

    public function test_register_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'phone', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jane',
            'email'                 => 'taken@example.com',
            'phone'                 => '080',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_password_mismatch(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Jane',
            'email'                 => 'jane@example.com',
            'phone'                 => '080',
            'password'              => 'secret123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct')]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/auth/logout');

        $response->assertStatus(200)->assertJson(['message' => 'Logged out successfully.']);
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Forgot Password
    // -------------------------------------------------------------------------

    public function test_forgot_password_returns_otp_for_existing_email(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'otp'])
            ->assertJsonFragment(['message' => 'OTP sent to your email.']);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_forgot_password_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // -------------------------------------------------------------------------
    // Verify OTP
    // -------------------------------------------------------------------------

    public function test_valid_otp_is_accepted(): void
    {
        $user = User::factory()->create();

        $otp = '123456';
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'otp'   => $otp,
        ]);

        $response->assertStatus(200)->assertJson(['message' => 'OTP verified.']);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $user = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make('999999'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-otp', [
            'email' => $user->email,
            'otp'   => '000000',
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Reset Password
    // -------------------------------------------------------------------------

    public function test_password_can_be_reset_with_valid_otp(): void
    {
        $user = User::factory()->create();
        $otp  = '654321';

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'otp'                   => $otp,
            'password'              => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(200)->assertJson(['message' => 'Password reset successfully.']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_password_reset_fails_with_wrong_otp(): void
    {
        $user = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make('correct'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'otp'                   => 'wrong1',
            'password'              => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(422);
    }
}
