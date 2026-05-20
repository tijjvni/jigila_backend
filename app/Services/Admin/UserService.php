<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('adminRoles')->latest();

        if (! empty($filters['type'])) {
            $query->where('role', $filters['type'] === 'admin' ? 'admin' : 'user');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function create(array $data): User
    {
        $firstName = $data['first_name'];
        $lastName  = $data['last_name'];

        return User::create([
            'name'       => trim("{$firstName} {$lastName}"),
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $data['email'],
            'phone'      => $data['phone'],
            'role'       => $data['role'],
            'password'   => Hash::make(Str::random(12)),
            'status'     => 'active',
        ]);
    }

    public function update(User $user, array $data): User
    {
        $updates = [];

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? ($user->first_name ?? explode(' ', $user->name, 2)[0]);
            $last  = $data['last_name']  ?? ($user->last_name  ?? (explode(' ', $user->name, 2)[1] ?? ''));
            $updates['first_name'] = $first;
            $updates['last_name']  = $last;
            $updates['name']       = trim("{$first} {$last}");
        }

        foreach (['email', 'phone', 'role'] as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }

        $user->update($updates);

        return $user->fresh('adminRoles');
    }

    public function archive(User $user): User
    {
        $user->update(['status' => 'archived']);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
