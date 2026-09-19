<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
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
            // While a payment hold is on, `status` reads `payment_overdue`.
            // `effective_status` is the pipeline stage underneath it, so the
            // tracking timeline keeps showing how far the shipment actually
            // got rather than collapsing to the hold (spec 4).
            'effective_status'  => $this->effectiveStatus(),
            'shipment_hold'        => (bool) $this->shipment_hold,
            'shipment_hold_reason' => $this->shipment_hold_reason,
            'shipment_held_at'     => $this->shipment_held_at,
            'pickup_location'   => $this->pickup_location,
            'departure_port'    => $this->departure_port,
            'destination_port'  => $this->destination_port,
            'bid_status'        => $this->bid_status,
            'out_bid_price'     => $this->out_bid_price,

            // Shipping details (BUG-034 / BUG-054 / BUG-057)
            'vessel_name'              => $this->vessel_name,
            'container_number'         => $this->container_number,
            'shipping_tracking_number' => $this->shipping_tracking_number,
            'shipping_line'            => $this->shipping_line,
            'shipping_type'            => $this->shipping_type,
            'current_vessel_location'  => $this->current_vessel_location,
            'port_received_at'         => $this->port_received_at?->toDateString(),
            'eta_start'                => $this->eta_start?->toDateString(),
            'eta_end'                  => $this->eta_end?->toDateString(),

            // Condition the export port authority confirmed on arrival. Kept
            // apart from `condition` so a downgrade (runner sold with no fuel
            // or flat tyres) is visible against what was originally booked.
            'port_condition'              => $this->port_condition,
            'port_condition_confirmed_at' => $this->port_condition_confirmed_at?->toDateString(),
            'port_condition_note'         => $this->port_condition_note,

            // Cancellation (BUG-032 / BUG-064)
            'cancelled_at'        => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            // Lets the client show or hide the Cancel button without
            // re-deriving the policy rules in two places.
            'can_cancel'          => $this->status !== OrderStatus::Cancelled
                && $this->status !== OrderStatus::Delivered
                && !$this->passedOperationalMilestone(),

            'documents'         => OrderDocumentResource::collection($this->whenLoaded('documents')),
            'user'              => new UserResource($this->whenLoaded('user')),
            'invoice'           => new InvoiceResource($this->whenLoaded('invoice')),
            'invoices'          => InvoiceResource::collection($this->whenLoaded('invoices')),
            'vessel_date'       => $this->whenLoaded('auditLogs', fn () => $this->auditLogs
                ->first(fn ($log) => $log->action === 'status_changed' &&
                    ($log->new_values['status'] ?? null) === 'on_vessel'
                )?->created_at
            ),

            // When each status was actually reached, so the customer tracking
            // timeline can date-stamp every completed point (BUG-095).
            // Keyed by status; `pending` falls back to order creation because
            // the opening status is never written as a transition.
            'status_timestamps' => $this->whenLoaded('auditLogs', function () {
                $stamps = ['pending' => $this->created_at?->toIso8601String()];

                foreach ($this->auditLogs->sortBy('created_at') as $log) {
                    if ($log->action !== 'status_changed') {
                        continue;
                    }
                    $status = $log->new_values['status'] ?? null;
                    // First arrival wins — a status re-entered after a
                    // correction keeps the date the customer first saw.
                    if ($status && !isset($stamps[$status])) {
                        $stamps[$status] = $log->created_at?->toIso8601String();
                    }
                }

                return $stamps;
            }),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
