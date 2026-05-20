<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with('order')
            ->latest()
            ->get();

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->invoiceService->authorize($request->user(), $invoice);

        return new InvoiceResource($this->invoiceService->find($invoice));
    }
}
