<?php

namespace App\Services\Admin;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['user', 'invoice'])->latest()->paginate($perPage);
    }

    public function find(Order $order): Order
    {
        return $order->load(['user', 'invoices']);
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $order->update(['status' => $status]);

        return $order->load(['user', 'invoices']);
    }

    public function updateBid(Order $order, array $data): Order
    {
        $order->update([
            'bid_status'    => $data['bid_status'],
            'out_bid_price' => $data['out_bid_price'] ?? null,
        ]);

        // When the admin confirms the bid is won, auto-generate the remaining 50% balance invoice.
        // Guard against duplicate: only create if no bid_balance invoice exists yet.
        if ($data['bid_status'] === 'won' && !empty($order->bid_price)) {
            $alreadyIssued = Invoice::where('order_id', $order->id)
                ->where('type', 'bid_balance')
                ->exists();

            if (!$alreadyIssued) {
                $this->invoiceService->create(
                    $order->user,
                    $order,
                    'bid_balance',
                    '50% Balance Payment – Auction Bid Confirmed Won',
                    round((float) $order->bid_price * 0.5, 2),
                );
            }
        }

        return $order->load(['user', 'invoices']);
    }

    public function updateLocation(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->load(['user', 'invoices']);
    }
}
