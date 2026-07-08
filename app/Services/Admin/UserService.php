<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('adminRoles')->latest();

        if (!empty($filters['type'])) {
            $query->where('role', $filters['type'] === 'admin' ? 'admin' : 'user');
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
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
        $password  = Str::random(12);

        $user = User::create([
            'name'       => trim("{$firstName} {$lastName}"),
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $data['email'],
            'phone'      => $data['phone'],
            'password'   => $password,
        ]);
        $user->forceFill(['role' => $data['role'], 'status' => 'active'])->save();

        $this->notifications->sendCredentials($user, $password);

        return $user;
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

        $user->forceFill($updates)->save();

        return $user->fresh('adminRoles');
    }

    public function archive(User $user): User
    {
        $user->forceFill(['status' => 'archived'])->save();

        return $user;
    }

    public function activate(User $user): User
    {
        $user->forceFill(['status' => 'active'])->save();

        return $user->fresh();
    }

    public function resetPasswordByAdmin(User $user): void
    {
        $password = Str::random(12);
        $user->update(['password' => $password]);
        $this->notifications->sendCredentials($user, $password);
    }

    public function delete(User $user): void
    {
        // Soft delete keeps the row, so free the unique email for re-registration.
        $user->forceFill(['email' => "deleted_{$user->id}_{$user->email}"])->save();

        $user->delete();
    }
}
