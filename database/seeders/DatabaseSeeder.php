<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user@jigila.com'],
            [
                'name'     => 'Jigila User',
                'password' => Hash::make('password'),
                'role'     => 'user',
                'status'   => 'active',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@jigila.com'],
            [
                'name'     => 'Jigila Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'status'   => 'active',
            ]
        );
    }
}
