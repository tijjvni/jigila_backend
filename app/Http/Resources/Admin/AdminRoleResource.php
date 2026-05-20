<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => (string) $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions ?? [],
            'users'       => AdminUserResource::collection($this->whenLoaded('users')),
            'user_count'  => $this->when(
                ! $this->relationLoaded('users'),
                fn () => $this->users_count ?? $this->users()->count()
            ),
            'created_at'  => $this->created_at,
        ];
    }
}
