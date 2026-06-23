<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => (string) $this->id,
            'body'           => $this->body,
            'is_staff_reply' => $this->is_staff_reply,
            'user' => new UserResource($this->whenLoaded('user')),
            'attachments' => collect($this->attachments ?? [])->map(fn ($path) => Storage::url($path))->values()->all(),
            'created_at'  => $this->created_at,
        ];
    }
}
