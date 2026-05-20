<?php

namespace App\Services\Admin;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['user', 'invoice'])->latest()->paginate($perPage);
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $order->update(['status' => $status]);

        return $order->load(['user', 'invoice']);
    }

    public function updateBid(Order $order, array $data): Order
    {
        $order->update([
            'bid_status'    => $data['bid_status'],
            'out_bid_price' => $data['out_bid_price'] ?? null,
        ]);

        return $order->load(['user', 'invoice']);
    }

    public function updateLocation(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->load(['user', 'invoice']);
    }
}
