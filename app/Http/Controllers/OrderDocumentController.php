<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\Admin\StoreOrderDocumentRequest;
use App\Http\Resources\OrderDocumentResource;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Services\OrderDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shipping paperwork on an order (BUG-035).
 *
 * Listing and downloading are open to the order's owner and to admins;
 * uploading and deleting are admin-only and are gated at the route level.
 */
class OrderDocumentController extends Controller
{
    public function __construct(private OrderDocumentService $documents) {}

    public function index(Request $request, Order $order): JsonResponse
    {
        $this->documents->authorize($request->user(), $order);

        return $this->okResponse(
            OrderDocumentResource::collection($order->documents()->with('uploader')->get())
        );
    }

    public function store(StoreOrderDocumentRequest $request, Order $order): JsonResponse
    {
        $document = $this->documents->upload(
            $order,
            $request->user(),
            DocumentType::from($request->validated('type')),
            $request->file('file'),
            $request->validated('note'),
        );

        return $this->createdResponse(new OrderDocumentResource($document));
    }

    public function download(Request $request, Order $order, OrderDocument $document): StreamedResponse
    {
        $this->documents->authorize($request->user(), $order);
        $this->assertBelongsToOrder($order, $document);

        return $this->documents->download($document);
    }

    public function destroy(Order $order, OrderDocument $document): JsonResponse
    {
        $this->assertBelongsToOrder($order, $document);

        $this->documents->delete($document);

        return $this->messageResponse('Document deleted.');
    }

    /**
     * Both ids come from the URL, so a mismatched pair would otherwise leak a
     * document from someone else's order through an authorised order.
     */
    private function assertBelongsToOrder(Order $order, OrderDocument $document): void
    {
        abort_unless($document->order_id === $order->id, 404, 'Document not found on this order.');
    }
}
