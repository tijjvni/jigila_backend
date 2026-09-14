<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => (string) $this->id,
            'order_id'      => (string) $this->order_id,
            'type'          => $this->type,
            'original_name' => $this->original_name,
            'mime_type'     => $this->mime_type,
            'size'          => $this->size,
            'note'          => $this->note,
            // Documents are private; the client always fetches through this
            // authorised endpoint rather than a public storage URL.
            'download_url'  => route('orders.documents.download', [
                'order'    => $this->order_id,
                'document' => $this->id,
            ]),
            'uploaded_by'   => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'created_at'    => $this->created_at,
        ];
    }
}
