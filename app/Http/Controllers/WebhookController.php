<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private InvoiceService  $invoiceService,
    ) {}

    public function paystack(Request $request): JsonResponse
    {
        $signature = $request->header('X-Paystack-Signature', '');
        $payload   = $request->getContent();

        if (!$this->paystack->validateWebhookSignature($payload, $signature)) {
            Log::warning('Paystack webhook: invalid signature rejected');
            return $this->messageResponse('OK');
        }

        try {
            $event = $request->input('event');
            $data  = $request->input('data', []);

            if ($event === 'charge.success') {
                $reference = $data['reference'] ?? null;
                if ($reference) {
                    $invoice = Invoice::where('payment_reference', $reference)
                        ->where('status', 'pending')
                        ->first();

                    if ($invoice) {
                        $this->invoiceService->markPaid($invoice, $reference);
                    } else {
                        Log::warning('Paystack webhook: no pending invoice found for reference', ['reference' => $reference]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('Paystack webhook processing failed', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $this->messageResponse('OK');
    }
}
