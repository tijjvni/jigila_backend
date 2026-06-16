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
            ? Order::with(['user', 'invoice'])->latest()->paginate($perPage)
            : $user->orders()->with(['invoice'])->latest()->paginate($perPage);
    }

    public function create(User $user, array $data): Order
    {
        $order = $user->orders()->create($data)->refresh();

        // Auto-generate a 50% deposit invoice when the vehicle has not been purchased yet.
        // The remaining 50% is invoiced automatically when the admin confirms the bid as won.
        if (!$data['already_purchased'] && !empty($data['bid_price'])) {
            $this->invoiceService->create(
                $user,
                $order,
                'bid_deposit',
                '50% Initial Deposit – Vehicle Auction Bid',
                round((float) $data['bid_price'] * 0.5, 2),
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
