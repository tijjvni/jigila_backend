<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreAdminInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(): JsonResponse
    {
        return $this->okResponse(InvoiceResource::collection(
            $this->invoiceService->listAll()
        ));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->okResponse(new InvoiceResource($this->invoiceService->find($invoice)));
    }

    public function store(StoreAdminInvoiceRequest $request, Order $order): JsonResponse
    {
        $invoice = $this->invoiceService->create(
            user: $order->user,
            order: $order,
            type: InvoiceType::Service,
            description: $request->validated('description'),
            amount: (float) $request->validated('amount'),
            metadata: $request->validated('metadata', []),
            actor: $request->user(),
        );

        return $this->createdResponse(new InvoiceResource($invoice->load('order')));
    }
}
