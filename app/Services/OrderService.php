<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private NotificationService $notifications,
    ) {}

    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $auditLoad = ['auditLogs' => fn ($q) => $q->where('action', 'status_changed')];

        return $user->role === 'admin'
            ? Order::with(['user', 'invoice', ...$auditLoad])->latest()->paginate($perPage)
            : $user->orders()->with(['invoice', ...$auditLoad])->latest()->paginate($perPage);
    }

    public function create(User $user, array $data): Order
    {
        $order = $user->orders()->create($data)->refresh();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $user->id,
            'action'     => 'order_created',
            'old_values' => [],
            'new_values' => [
                'vin'               => $order->vin,
                'auction_source'    => $order->auction_source,
                'already_purchased' => $order->already_purchased,
                'services'          => $order->services ?? [],
                'status'            => $order->status,
            ],
        ]);

        // Auto-generate a 50% deposit invoice when the vehicle has not been purchased yet.
        // The remaining 50% is invoiced automatically when the admin confirms the bid as won.
        if (!$data['already_purchased'] && !empty($data['bid_price'])) {
            $this->invoiceService->create(
                $user,
                $order,
                InvoiceType::BidDeposit,
                '50% Initial Deposit – Vehicle Auction Bid',
                round((float) $data['bid_price'] * 0.5, 2),
            );
        }

        $this->notifications->notifyAdmins(
            'new_order',
            'New Order Placed',
            "Customer {$user->name} placed a new order for VIN {$order->vin}.",
            ['order_id' => $order->id],
        );

        return $order;
    }

    public function find(Order $order): Order
    {
        return $order->load(['user', 'invoice', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    public function update(Order $order, array $data): Order
    {
        $order->fill($data)->save();

        return $order;
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }

    /**
     * Cancel an order (BUG-032 / BUG-064).
     *
     * Customers may cancel only while the order is still pending or processing;
     * once it passes an operational milestone (pickup onwards) Jigila has
     * already committed money and the customer has to go through support.
     * Admins can cancel at any stage. Cancelling twice is a no-op error rather
     * than a silent overwrite, so the original audit entry survives.
     */
    public function cancel(Order $order, User $actor, string $reason): Order
    {
        if ($order->status === OrderStatus::Cancelled) {
            abort(422, 'This order has already been cancelled.');
        }

        if ($order->status === OrderStatus::Delivered) {
            abort(422, 'A delivered order can no longer be cancelled.');
        }

        $isAdmin = $actor->role === 'admin';

        if (!$isAdmin && $order->passedOperationalMilestone()) {
            abort(422, 'This order has passed an operational milestone and can no longer be cancelled online. Please contact support.');
        }

        $old = $order->status;

        $order->forceFill([
            'status'              => OrderStatus::Cancelled,
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
            'cancelled_by'        => $actor->id,
        ])->save();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor->id,
            'action'     => 'order_cancelled',
            'old_values' => ['status' => $old],
            'new_values' => [
                'status' => OrderStatus::Cancelled->value,
                'reason' => $reason,
                'free'   => $order->withinFreeCancellationWindow(),
            ],
        ]);

        $this->notifications->notifyOrderCancelled($order, $actor);

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    public function authorize(User $user, Order $order): void
    {
        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
    }
}
