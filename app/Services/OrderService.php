<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OrderService
{
    public function list(User $user): Collection
    {
        return $user->role === 'admin'
            ? Order::with('user')->latest()->get()
            : $user->orders()->latest()->get();
    }

    public function create(User $user, array $data): Order
    {
        return $user->orders()->create($data)->refresh();
    }

    public function find(Order $order): Order
    {
        return $order->load('user');
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order;
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }

    public function authorize(User $user, Order $order): void
    {
        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
    }
}
