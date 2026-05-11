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
            'total_users'      => $this->resource['total_users'],
            'total_orders'     => $this->resource['total_orders'],
            'orders_by_status' => $this->resource['orders_by_status'],
            'recent_orders'    => OrderResource::collection($this->resource['recent_orders']),
        ];
    }
}
