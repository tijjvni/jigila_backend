<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => (string) $this->id,
            'body'           => $this->body,
            'is_staff_reply' => $this->is_staff_reply,
            'user' => $this->whenLoaded('user', fn () => [
                'id'   => (string) $this->user->id,
                'name' => $this->user->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
