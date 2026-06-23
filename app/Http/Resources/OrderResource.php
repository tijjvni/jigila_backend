<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => (string) $this->id,
            'vin'               => $this->vin,
            'stock_id'          => $this->stock_id,
            'auction_source'    => $this->auction_source,
            'condition'         => $this->condition,
            'vehicle_type'      => $this->vehicle_type,
            'already_purchased' => $this->already_purchased,
            'bid_price'         => $this->bid_price,
            'vehicle_stock_no'  => $this->vehicle_stock_no,
            'buyer_no'          => $this->buyer_no,
            'buyer_code'        => $this->buyer_code,
            'services'          => $this->services,
            'status'            => $this->status,
            'pickup_location'   => $this->pickup_location,
            'departure_port'    => $this->departure_port,
            'destination_port'  => $this->destination_port,
            'bid_status'        => $this->bid_status,
            'out_bid_price'     => $this->out_bid_price,
            'user'              => new UserResource($this->whenLoaded('user')),
            'invoice'           => new InvoiceResource($this->whenLoaded('invoice')),
            'invoices'          => InvoiceResource::collection($this->whenLoaded('invoices')),
            'vessel_date'       => $this->whenLoaded('auditLogs', fn () =>
                $this->auditLogs
                    ->first(fn ($log) =>
                        $log->action === 'status_changed' &&
                        ($log->new_values['status'] ?? null) === 'on_vessel'
                    )?->created_at
            ),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
