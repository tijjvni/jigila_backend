<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->okResponse(InvoiceResource::collection(
            $this->invoiceService->list($request->user())
        ));
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->invoiceService->authorize($request->user(), $invoice);

        return $this->okResponse(new InvoiceResource($this->invoiceService->find($invoice)));
    }
}
