<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private InvoiceService $invoiceService,
        private NotificationService $notifications,
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
                    $invoice = DB::transaction(function () use ($reference, $data) {
                        $inv = Invoice::where('payment_reference', $reference)
                            ->where('status', 'pending')
                            ->lockForUpdate()
                            ->with('order.user')
                            ->first();

                        if ($inv) {
                            $this->invoiceService->markPaid($inv, $reference, $data);
                        }

                        return $inv;
                    });

                    if ($invoice) {
                        $customerName = $invoice->order?->user?->name ?? 'Customer';
                        $this->notifications->notifyAdmins(
                            'payment_received',
                            'Payment Received',
                            "{$customerName} paid invoice #{$invoice->id} of \${$invoice->amount}.",
                            ['invoice_id' => $invoice->id, 'order_id' => $invoice->order_id],
                        );
                    } else {
                        Log::warning('Paystack webhook: no pending invoice found for reference', ['reference' => $reference]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log identifying fields only — the raw payload contains cardholder PII.
            Log::error('Paystack webhook processing failed', [
                'error'     => $e->getMessage(),
                'event'     => $request->input('event'),
                'reference' => $request->input('data.reference'),
            ]);
        }

        return $this->messageResponse('OK');
    }
}
