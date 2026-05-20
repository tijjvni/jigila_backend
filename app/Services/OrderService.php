<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->role === 'admin'
            ? Order::with('user')->latest()->paginate($perPage)
            : $user->orders()->latest()->paginate($perPage);
    }

    public function create(User $user, array $data): Order
    {
        $order = $user->orders()->create($data)->refresh();

        // Auto-generate a bid invoice when the vehicle has not been purchased yet
        if (!$data['already_purchased'] && !empty($data['bid_price'])) {
            $this->invoiceService->create(
                $user,
                $order,
                'bid',
                'Vehicle auction bid price',
                (float) $data['bid_price'],
            );
        }

        return $order;
    }

    public function find(Order $order): Order
    {
        return $order->load(['user', 'invoice']);
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
