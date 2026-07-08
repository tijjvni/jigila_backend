<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RoleService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Role::with('users')->withCount('users')->latest()->paginate($perPage);
    }

    public function create(array $data): Role
    {
        $role = Role::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
        ]);

        if (!empty($data['assigned_user_ids'])) {
            $role->users()->sync($data['assigned_user_ids']);
        }

        return $role->load('users');
    }

    public function find(Role $role): Role
    {
        return $role->load('users');
    }

    public function update(Role $role, array $data): Role
    {
        $role->update(array_filter([
            'name'        => $data['name']        ?? null,
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? null,
        ], fn ($v) => !is_null($v)));

        return $role->load('users');
    }

    public function delete(Role $role): void
    {
        $role->users()->detach();
        $role->delete();
    }

    public function addUser(Role $role, User $user): Role
    {
        $role->users()->syncWithoutDetaching([$user->id]);

        return $role->load('users');
    }

    public function removeUser(Role $role, User $user): Role
    {
        $role->users()->detach($user->id);

        return $role->load('users');
    }

    public function assign(int $roleId, array $userIds): void
    {
        $role = Role::findOrFail($roleId);
        $role->users()->syncWithoutDetaching($userIds);
    }
}
