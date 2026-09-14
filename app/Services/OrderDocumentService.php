<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderDocumentService
{
    /**
     * Private disk — shipping paperwork carries ownership and personal data and
     * must never be reachable by guessing a public URL.
     */
    private const DISK = 'local';

    public function __construct(private NotificationService $notifications) {}

    public function upload(Order $order, User $actor, DocumentType $type, UploadedFile $file, ?string $note = null): OrderDocument
    {
        $path = $file->store("order-documents/{$order->id}", self::DISK);

        $document = $order->documents()->create([
            'uploaded_by'   => $actor->id,
            'type'          => $type->value,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'note'          => $note,
        ]);

        $order->loadMissing('user');

        $this->notifications->notifyDocumentUploaded($order, $document);

        return $document->load('uploader');
    }

    public function download(OrderDocument $document): StreamedResponse
    {
        if (!Storage::disk(self::DISK)->exists($document->path)) {
            abort(404, 'The document file is no longer available.');
        }

        return Storage::disk(self::DISK)->download($document->path, $document->original_name);
    }

    public function delete(OrderDocument $document): void
    {
        // Soft-delete the row first so the audit trail survives; the blob goes
        // immediately because it is the part that carries the personal data.
        $document->delete();

        try {
            Storage::disk(self::DISK)->delete($document->path);
        } catch (\Throwable $e) {
            Log::error('Failed to delete order document blob', [
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    public function authorize(User $user, Order $order): void
    {
        if ($user->role !== 'admin' && $order->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
    }
}
