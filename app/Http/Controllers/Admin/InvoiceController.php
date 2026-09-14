<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InvoiceType;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreAdminInvoiceRequest;
use App\Http\Requests\Invoice\UpdateRefundStatusRequest;
use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private RefundService $refunds,
    ) {}

    /**
     * Move a refund along the requested → approved → processed path (BUG-055).
     */
    public function updateRefund(UpdateRefundStatusRequest $request, Invoice $invoice): JsonResponse
    {
        $updated = $this->refunds->updateStatus(
            $invoice,
            RefundStatus::from($request->validated('refund_status')),
            $request->user(),
            $request->validated('amount') !== null ? (float) $request->validated('amount') : null,
            $request->validated('reason'),
        );

        return $this->okResponse(new InvoiceResource($updated));
    }

    public function index(): JsonResponse
    {
        return $this->okResponse(InvoiceResource::collection(
            $this->invoiceService->listAll()
        ));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->okResponse(new InvoiceDetailResource($this->invoiceService->find($invoice)));
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
