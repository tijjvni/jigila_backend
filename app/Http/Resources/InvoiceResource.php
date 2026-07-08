<?php

namespace App\Http\Resources;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => (string) $this->id,
            'invoice_number' => $this->invoice_number,
            'type'           => $this->type,
            'description'    => $this->description,
            'amount'         => $this->amount,
            'amount_ngn'     => (function () {
                $rate = (float) ($this->metadata['exchange_rate'] ?? Setting::get('exchange_rate', 0));

                return $rate > 0
                    ? number_format((float) $this->amount * $rate, 2, '.', '')
                    : null;
            })(),
            'status'         => $this->status,
            'due_date'       => $this->due_date?->toDateString(),
            'paid_at'        => $this->paid_at,
            'payment_url'    => $this->payment_url,
            'metadata'       => $this->when(auth()->user()?->role === 'admin', $this->metadata),
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
