<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => (string) $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'role'               => $this->role,
            'email_verified_at'  => $this->email_verified_at,
            'admin_permissions'  => $this->when(
                $this->role === 'admin',
                fn () => $this->loadMissing('adminRoles')->adminRoles
                    ->flatMap(fn ($r) => $r->permissions ?? [])
                    ->unique()->values()->all()
            ),
        ];
    }
}
