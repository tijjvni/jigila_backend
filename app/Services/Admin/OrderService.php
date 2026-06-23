<?php

namespace App\Services\Admin;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(private InvoiceService $invoiceService) {}

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

    public function updateStatus(Order $order, string $status): Order
    {
        $order->forceFill(['status' => $status])->save();

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    public function updateBid(Order $order, array $data, ?User $actor = null): Order
    {
        $order->forceFill([
            'bid_status'    => $data['bid_status'],
            'out_bid_price' => $data['out_bid_price'] ?? null,
        ])->save();

        // When the admin confirms the bid is won, auto-generate the remaining 50% balance invoice.
        // Guard against duplicate: only create if no bid_balance invoice exists yet.
        if ($data['bid_status'] === 'won' && !empty($order->bid_price)) {
            $alreadyIssued = Invoice::where('order_id', $order->id)
                ->where('type', InvoiceType::BidBalance)
                ->exists();

            if (!$alreadyIssued) {
                $balanceInvoice = $this->invoiceService->create(
                    $order->user,
                    $order,
                    InvoiceType::BidBalance,
                    '50% Balance Payment – Auction Bid Confirmed Won',
                    round((float) $order->bid_price * 0.5, 2),
                );

                OrderAuditLog::create([
                    'order_id'   => $order->id,
                    'user_id'    => $actor?->id,
                    'action'     => 'invoice_generated',
                    'old_values' => [],
                    'new_values' => [
                        'invoice_number' => $balanceInvoice->invoice_number,
                        'type'           => $balanceInvoice->type,
                        'amount'         => $balanceInvoice->amount,
                        'description'    => $balanceInvoice->description,
                    ],
                ]);
            }
        }

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }

    public function updateLocation(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->load(['user', 'invoices', 'auditLogs' => fn ($q) => $q->where('action', 'status_changed')]);
    }
}
