<?php

namespace App\Http\Resources\Admin;

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
            'role'       => $this->role,   // 'user' | 'admin' — access level
            'type'       => $this->role === 'admin' ? 'admin' : 'customer',
            'status'     => $this->status ?? 'active',
            'admin_roles' => AdminRoleResource::collection($this->whenLoaded('adminRoles')),
            'created_at' => $this->created_at,
        ];
    }
}
