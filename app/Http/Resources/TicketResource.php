<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => (string) $this->id,
            'ticket_number' => $this->ticket_number,
            'subject'       => $this->subject,
            'status'        => $this->status,
            'message_count' => $this->messages_count ?? $this->messages->count(),
            'user'          => new UserResource($this->whenLoaded('user')),
            'messages'   => TicketMessageResource::collection($this->whenLoaded('messages')),
            'updated_at' => $this->updated_at,
            'created_at' => $this->created_at,
        ];
    }
}
