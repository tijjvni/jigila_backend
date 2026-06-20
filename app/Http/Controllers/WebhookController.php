<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\OrderAuditLog;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; 
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaystackService     $paystack,
        private InvoiceService      $invoiceService,
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
                    $invoice = Invoice::where('payment_reference', $reference)
                        ->where('status', 'pending')
                        ->with('order.user')
                        ->first();

                    if ($invoice) {
                        $this->invoiceService->markPaid($invoice, $reference);

                        if ($invoice->order_id) {
                            OrderAuditLog::create([
                                'order_id'   => $invoice->order_id,
                                'user_id'    => null,
                                'action'     => 'payment_received',
                                'old_values' => ['status' => 'pending'],
                                'new_values' => [
                                    'status'         => 'paid',
                                    'reference'      => $reference,
                                    'invoice_number' => $invoice->invoice_number,
                                    'amount'         => $invoice->amount,
                                    'type'           => $invoice->type,
                                ],
                            ]);
                        }

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
            Log::error('Paystack webhook processing failed', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return $this->messageResponse('OK');
    }
}
