<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private NotificationService $notifications) {}

    public function register(array $data): void
    {
        $user = User::create($data);
        $this->notifications->sendWelcome($user);
    }

    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return ['token' => $token, 'user' => $user];
    }

    public function logout(\Illuminate\Http\Request $request): void
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }
    }

    public function forgotPassword(string $email): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->upsert(
            ['email' => $email, 'token' => Hash::make($otp), 'created_at' => now()],
            ['email'],
            ['token', 'created_at']
        );

        return $otp;
    }

    public function verifyOtp(string $email, string $otp): void
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! Hash::check($otp, $record->token)) {
            throw ValidationException::withMessages(['otp' => ['Invalid or expired OTP.']]);
        }

        if (Carbon::parse($record->created_at)->addMinutes(15)->isPast()) {
            throw ValidationException::withMessages(['otp' => ['OTP has expired.']]);
        }
    }

    public function resetPassword(string $email, string $otp, string $password): void
    {
        $this->verifyOtp($email, $otp);

        User::where('email', $email)->update(['password' => Hash::make($password)]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
    }
}
