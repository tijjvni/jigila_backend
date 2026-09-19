<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeadlineExtensionStatus;
use App\Enums\InvoiceType;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\ReviewDeadlineExtensionRequest;
use App\Http\Requests\Invoice\StoreAdminInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceDeadlineRequest;
use App\Http\Requests\Invoice\UpdateRefundStatusRequest;
use App\Http\Resources\InvoiceDetailResource;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\PaymentDeadlineService;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService,
        private RefundService $refunds,
        private PaymentDeadlineService $deadlines,
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
            deadlineHours: $request->validated('payment_deadline_hours') !== null
                ? (int) $request->validated('payment_deadline_hours')
                : null,
        );

        return $this->createdResponse(new InvoiceResource($invoice->load('order')));
    }

    /**
     * Approve or decline a customer's deadline extension request (spec 4).
     *
     * Approving moves the deadline, re-arms the reminder stages against it and
     * lifts any shipment hold. Late fees already accrued are frozen rather than
     * erased unless `waive_accrued_fees` is passed.
     */
    public function reviewExtension(ReviewDeadlineExtensionRequest $request, Invoice $invoice): JsonResponse
    {
        $updated = $this->deadlines->reviewExtension(
            $invoice,
            DeadlineExtensionStatus::from($request->validated('extension_status')),
            $request->user(),
            $request->validated('granted_hours') !== null ? (int) $request->validated('granted_hours') : null,
            $request->validated('reason'),
            (bool) $request->validated('waive_accrued_fees', false),
        );

        return $this->okResponse(new InvoiceResource($updated));
    }

    /**
     * Set or move a payment deadline directly, outside the request flow.
     */
    public function updateDeadline(UpdateInvoiceDeadlineRequest $request, Invoice $invoice): JsonResponse
    {
        $dueAt = $request->validated('payment_due_at')
            ? Carbon::parse($request->validated('payment_due_at'))
            : now()->addHours((int) $request->validated('deadline_hours'));

        $updated = $this->deadlines->setDeadline($invoice, $dueAt, $request->user());

        return $this->okResponse(new InvoiceResource($updated));
    }

    /**
     * Bill the late fee accrued on an overdue invoice as its own invoice, with
     * its own payment link (spec 4).
     */
    public function issueLateFee(Request $request, Invoice $invoice): JsonResponse
    {
        $lateFeeInvoice = $this->invoiceService->issueLateFee($invoice, $request->user());

        return $this->createdResponse(new InvoiceResource($lateFeeInvoice->load('order')));
    }
}
