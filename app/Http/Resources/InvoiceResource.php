<?php

namespace App\Http\Resources;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Whether this representation includes the heavy `metadata` blob.
     *
     * False here (list representation); InvoiceDetailResource overrides it.
     * Still admin-gated either way — metadata is internal payment data.
     */
    protected function includesMetadata(): bool
    {
        return false;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'             => (string) $this->id,
            'invoice_number' => $this->invoice_number,
            'type'           => $this->type,
            'description'    => $this->description,
            'amount'         => $this->amount,
            'amount_ngn'     => (function () {
                $rate = (float) ($this->metadata['exchange_rate'] ?? Setting::get('exchange_rate', 1));

                return $rate > 0
                    ? number_format((float) $this->amount * $rate, 2, '.', '')
                    : null;
            })(),
            'status'         => $this->status,
            'due_date'       => $this->due_date?->toDateString(),
            'paid_at'        => $this->paid_at,

            // Refund processing (BUG-055). `status` stays `paid` throughout —
            // a refund is a separate axis so payment history is never rewritten.
            'refund_status'       => $this->refund_status,
            'refund_amount'       => $this->refund_amount,
            'refund_reason'       => $this->refund_reason,
            'refund_requested_at' => $this->refund_requested_at,
            'refund_processed_at' => $this->refund_processed_at,

            'payment_url'    => $this->payment_url,

            // `metadata` carries the creation context plus the full Paystack
            // payment blob — roughly half the serialized size of a paid invoice,
            // and never rendered in a list. It is returned by
            // InvoiceDetailResource only; see that class.
            'metadata'       => $this->when($this->includesMetadata(), fn () => $this->metadata),

            'order_id'       => $this->order_id,
            'order_vin'      => $this->whenLoaded('order', fn () => $this->order->vin),
            'order'          => $this->whenLoaded('order', fn () => [
                'id'             => (string) $this->order->id,
                'vin'            => $this->order->vin,
                'stock_id'       => $this->order->stock_id,
                'auction_source' => $this->order->auction_source,
                'condition'      => $this->order->condition,
            ]),
            'user'           => new UserResource($this->whenLoaded('user')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
