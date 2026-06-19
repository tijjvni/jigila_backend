<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::updateOrCreate(
            ['name' => 'Super Admin'],
            [
                'description' => 'Full access to all admin features.',
                'permissions' => Permission::values(),
            ]
        );

        // Attach the Super Admin role to every existing admin user (idempotent)
        User::where('role', 'admin')->each(function (User $user) use ($role) {
            $user->adminRoles()->syncWithoutDetaching([$role->id]);
        });

        $this->command->info("Super Admin role seeded with " . count(Permission::values()) . " permissions.");
        $this->command->info("Attached to " . User::where('role', 'admin')->count() . " admin user(s).");
    }
}
