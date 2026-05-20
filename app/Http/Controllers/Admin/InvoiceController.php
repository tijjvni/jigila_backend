<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\StoreAdminInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(): AnonymousResourceCollection
    {
        $invoices = Invoice::with(['user', 'order'])->latest()->get();

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->find($invoice));
    }

    public function store(StoreAdminInvoiceRequest $request, Order $order): InvoiceResource
    {
        $invoice = $this->invoiceService->create(
            user:        $order->user,
            order:       $order,
            type:        'service',
            description: $request->validated('description'),
            amount:      (float) $request->validated('amount'),
            metadata:    $request->validated('metadata', []),
        );

        return new InvoiceResource($invoice->load('order'));
    }
}
