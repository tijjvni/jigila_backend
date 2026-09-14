<?php

namespace App\Services\Admin;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private NotificationService $notifications,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['user', 'invoice', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')])
            ->latest()
            ->paginate($perPage);
    }

    public function find(Order $order): Order
    {
        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    public function updateStatus(Order $order, string $status, User $actor): Order
    {
        $old = $order->status;

        $order->forceFill(['status' => $status])->save();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor->id,
            'action'     => 'status_changed',
            'old_values' => ['status' => $old],
            'new_values' => ['status' => $order->status],
        ]);

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    public function updateBid(Order $order, array $data, ?User $actor = null): Order
    {
        $old = ['bid_status' => $order->bid_status, 'out_bid_price' => $order->out_bid_price];

        $order->forceFill([
            'bid_status'    => $data['bid_status'],
            'out_bid_price' => $data['out_bid_price'] ?? null,
        ])->save();

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor?->id,
            'action'     => 'bid_updated',
            'old_values' => $old,
            'new_values' => ['bid_status' => $order->bid_status, 'out_bid_price' => $order->out_bid_price],
        ]);

        // When the admin confirms the bid is won, auto-generate the remaining 50% balance invoice.
        // Guard against duplicate: only create if no bid_balance invoice exists yet.
        if ($data['bid_status'] === 'won' && !empty($order->bid_price)) {
            $alreadyIssued = Invoice::where('order_id', $order->id)
                ->where('type', InvoiceType::BidBalance)
                ->exists();

            if (!$alreadyIssued) {
                $this->invoiceService->create(
                    $order->user,
                    $order,
                    InvoiceType::BidBalance,
                    '50% Balance Payment – Auction Bid Confirmed Won',
                    round((float) $order->bid_price * 0.5, 2),
                    actor: $actor,
                );
            }
        }

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    /**
     * Vessel, container, tracking number, live position and arrival window
     * (BUG-034 / BUG-054 / BUG-057).
     *
     * Only keys whose value actually changed are written, so refreshing just
     * `current_vessel_location` neither wipes the rest of the record nor spams
     * the customer with a "shipping updated" notification for a no-op save.
     */
    public function updateShipping(Order $order, array $data, User $actor): Order
    {
        $old     = $order->only(array_keys($data));
        $changed = array_filter(
            $data,
            function ($value, $key) use ($old) {
                $current = $old[$key] ?? null;

                if ($current instanceof \BackedEnum) {
                    $current = $current->value;
                } elseif ($current instanceof \DateTimeInterface) {
                    $current = $current->format('Y-m-d');
                }

                return (string) $current !== (string) $value;
            },
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed === []) {
            return $this->find($order);
        }

        $order->update($changed);

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor->id,
            'action'     => 'shipping_updated',
            'old_values' => array_intersect_key($old, $changed),
            'new_values' => $changed,
        ]);

        $this->notifications->notifyShippingUpdated($order);

        return $this->find($order);
    }

    public function updateLocation(Order $order, array $data, User $actor): Order
    {
        $locationFields = ['pickup_location', 'departure_port', 'destination_port'];
        $old            = $order->only($locationFields);
        $incoming       = array_filter($data, fn ($v) => $v !== null);

        $order->update($data);

        OrderAuditLog::create([
            'order_id'   => $order->id,
            'user_id'    => $actor->id,
            'action'     => 'location_updated',
            'old_values' => array_intersect_key($old, $incoming),
            'new_values' => $incoming,
        ]);

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }
}
