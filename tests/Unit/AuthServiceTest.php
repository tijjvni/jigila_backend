<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AuthService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldIgnoreMissing();
        $this->service = new AuthService($notifications);
    }

    public function test_register_creates_user(): void
    {
        $this->service->register([
            'name'     => 'Alice',
            'email'    => 'alice@example.com',
            'phone'    => '080',
            'password' => 'secret123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $result = $this->service->login(['email' => $user->email, 'password' => 'secret123']);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($user->id, $result['user']->id);
    }

    public function test_login_throws_for_wrong_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct')]);

        $this->expectException(ValidationException::class);

        $this->service->login(['email' => $user->email, 'password' => 'wrong']);
    }

    public function test_login_throws_for_nonexistent_email(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->login(['email' => 'ghost@example.com', 'password' => 'anything']);
    }

    public function test_forgot_password_stores_hashed_otp(): void
    {
        $user = User::factory()->create();

        $otp = $this->service->forgotPassword($user->email);

        $this->assertIsString($otp);
        $this->assertSame(6, strlen($otp));

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);
        $this->assertTrue(Hash::check($otp, $record->token));
    }

    public function test_verify_otp_passes_with_correct_otp(): void
    {
        $user = User::factory()->create();
        $otp  = '123456';

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);

        // No exception should be thrown
        $this->service->verifyOtp($user->email, $otp);
        $this->assertTrue(true);
    }

    public function test_verify_otp_throws_for_wrong_otp(): void
    {
        $user = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make('999999'),
            'created_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->service->verifyOtp($user->email, '000000');
    }

    public function test_verify_otp_throws_for_expired_otp(): void
    {
        $user = User::factory()->create();
        $otp  = '111111';

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($otp),
            'created_at' => now()->subMinutes(20),
        ]);

        $this->expectException(ValidationException::class);

        $this->service->verifyOtp($user->email, $otp);
    }

    public function test_reset_password_updates_user_and_clears_token(): void
    {
        $user = User::factory()->create();
        $otp  = '777777';

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);

        $this->service->resetPassword($user->email, $otp, 'brandnewpass');

        $this->assertTrue(Hash::check('brandnewpass', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
