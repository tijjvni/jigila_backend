<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'invoice_number' => $this->invoice_number,
            'type'           => $this->type,
            'description'    => $this->description,
            'amount'         => $this->amount,
            'status'         => $this->status,
            'due_date'       => $this->due_date?->toDateString(),
            'paid_at'        => $this->paid_at?->toISOString(),
            'payment_url'    => $this->payment_url,
            'metadata'       => $this->metadata,
            'order_id'       => $this->order_id,
            'order_vin'      => $this->whenLoaded('order', fn () => $this->order->vin),
            'user'           => new UserResource($this->whenLoaded('user')),
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
