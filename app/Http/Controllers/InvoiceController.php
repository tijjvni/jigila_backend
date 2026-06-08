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
        return InvoiceResource::collection(
            $this->invoiceService->list($request->user())
        );
    }

    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        $this->invoiceService->authorize($request->user(), $invoice);

        return new InvoiceResource($this->invoiceService->find($invoice));
    }
}
