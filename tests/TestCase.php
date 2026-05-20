<?php

namespace Tests;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    protected function createAdmin(array $attrs = []): User
    {
        return User::factory()->admin()->create($attrs);
    }

    protected function createUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    protected function createOrder(User $user, array $attrs = []): Order
    {
        return Order::factory()->create(['user_id' => $user->id, ...$attrs]);
    }

    protected function insertOtp(User $user, string $otp = '123456'): void
    {
        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);
    }
}
