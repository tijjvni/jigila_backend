<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Services\NotificationService;

class OrderService
{
    public function __construct(
        private InvoiceService      $invoiceService,
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
            $depositInvoice = $this->invoiceService->create(
                $user,
                $order,
                InvoiceType::BidDeposit,
                '50% Initial Deposit – Vehicle Auction Bid',
                round((float) $data['bid_price'] * 0.5, 2),
            );

            OrderAuditLog::create([
                'order_id'   => $order->id,
                'user_id'    => $user->id,
                'action'     => 'invoice_generated',
                'old_values' => [],
                'new_values' => [
                    'invoice_number' => $depositInvoice->invoice_number,
                    'type'           => $depositInvoice->type,
                    'amount'         => $depositInvoice->amount,
                    'description'    => $depositInvoice->description,
                ],
            ]);
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
        $order->forceFill($data)->save();

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
