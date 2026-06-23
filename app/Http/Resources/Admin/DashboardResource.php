<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Stat cards
            'total_users'           => $this->resource['total_users'],
            'total_orders'          => $this->resource['total_orders'],
            'active_shipments'      => $this->resource['active_shipments'],
            'total_revenue'         => $this->resource['total_revenue'],

            // Charts
            'orders_by_status'         => $this->resource['orders_by_status'],
            'orders_by_month'          => $this->resource['orders_by_month'],
            'revenue_by_service'       => $this->resource['revenue_by_service'],
            'orders_by_auction_source' => $this->resource['orders_by_auction_source'] ?? [],

            // Metrics
            'order_completion_rate' => $this->resource['order_completion_rate'],
            'average_order_value'   => $this->resource['average_order_value'],

            // Recent activity table
            'recent_orders'         => OrderResource::collection($this->resource['recent_orders']),
        ];
    }
}
