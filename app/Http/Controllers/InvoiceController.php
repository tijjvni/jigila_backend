<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\RequestDeadlineExtensionRequest;
use App\Http\Requests\Invoice\RequestRefundRequest;
use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PaymentDeadlineService;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private RefundService $refunds,
        private PaymentDeadlineService $deadlines,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->okResponse(InvoiceResource::collection(
            $this->invoiceService->list($request->user())
        ));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->authorize($request->user(), $invoice);

        return $this->okResponse(new InvoiceDetailResource($this->invoiceService->find($invoice)));
    }

    /**
     * Customer-initiated refund request (BUG-055).
     */
    public function requestRefund(RequestRefundRequest $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->authorize($request->user(), $invoice);

        $updated = $this->refunds->request($invoice, $request->user(), $request->validated('reason'));

        return $this->okResponse(new InvoiceResource($updated));
    }

    /**
     * Customer asks for a one-time payment deadline extension (spec 4). It does
     * not move the deadline — an admin has to approve it first.
     */
    public function requestExtension(RequestDeadlineExtensionRequest $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->authorize($request->user(), $invoice);

        $updated = $this->deadlines->requestExtension(
            $invoice,
            $request->user(),
            (int) $request->validated('requested_hours'),
            $request->validated('reason'),
        );

        return $this->okResponse(new InvoiceResource($updated));
    }
}
