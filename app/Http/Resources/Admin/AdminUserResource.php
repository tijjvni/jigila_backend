<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\InvoiceResource;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => (string) $this->id,
            'first_name' => $this->first_name ?? '',
            'last_name'  => $this->last_name  ?? '',
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'role'       => $this->role,
            'type'       => $this->role === 'admin' ? 'admin' : 'customer',
            'status'     => $this->status ?? 'active',
            'admin_roles' => AdminRoleResource::collection($this->whenLoaded('adminRoles')),
            'created_at' => $this->created_at,

            // Detail fields — only included when the relationships are eagerly loaded
            $this->mergeWhen($this->relationLoaded('orders'), [
                'total_orders' => $this->orders->count(),
                'orders'       => OrderResource::collection($this->orders),
            ]),
            $this->mergeWhen($this->relationLoaded('invoices'), [
                'total_paid'   => (float) $this->invoices->where('status', 'paid')->sum('amount'),
                'outstanding'  => (float) $this->invoices->where('status', 'pending')->sum('amount'),
                'invoices'     => InvoiceResource::collection($this->invoices),
            ]),
        ];
    }
}
