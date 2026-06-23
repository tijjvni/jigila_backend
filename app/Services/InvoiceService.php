<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        private PaystackService     $paystack,
        private NotificationService $notifications,
    ) {}

    public function create(
        User        $user,
        ?Order      $order,
        InvoiceType $type,
        string  $description,
        float   $amount,
        array   $metadata = []
    ): Invoice {
        $paymentUrl       = null;
        $paymentReference = null;
        $exchangeRate     = (float) Setting::get('exchange_rate', 1);

        $amountNgn  = round($amount * $exchangeRate, 2);
        $amountKobo = (int) round($amount * $exchangeRate * 100);

        // Persist rich creation context for analytics — stored before Paystack call
        // so data is captured even if payment initialisation fails
        $metadata = array_merge($metadata, array_filter([
            'exchange_rate'  => $exchangeRate > 0 ? $exchangeRate : null,
            'amount_usd'     => $amount,
            'amount_ngn'     => $amountNgn,
            'amount_kobo'    => $amountKobo,
            'invoice_type'   => $type->value,
            'user_id'        => $user->id,
            'user_email'     => $user->email,
            'order_id'       => $order?->id,
            'order_vin'      => $order?->vin,
            'auction_source' => $order?->auction_source,
            'condition'      => $order?->condition,
        ], fn ($v) => $v !== null));

        try {
            if ($exchangeRate <= 0) {
                throw new \RuntimeException('Exchange rate not configured. Admin must set the NGN/USD rate before invoices can be paid.');
            }

            $callbackUrl = config('services.paystack.callback_url', url('/invoice'));
            $paystackRef = 'jig_' . Str::uuid()->toString();
            $data = $this->paystack->initializeTransaction(
                $user->email,
                $amountKobo,
                $paystackRef,
                $callbackUrl,
                [
                    'invoice_type'   => $type->value,
                    'order_id'       => $order?->id,
                    'order_vin'      => $order?->vin,
                    'user_id'        => $user->id,
                ],
            );
            $paymentUrl       = $data['authorization_url'];
            $paymentReference = $data['reference'];
        } catch (\Throwable $e) {
            Log::error('Paystack initialization failed', [
                'user_id'       => $user->id,
                'amount_usd'    => $amount,
                'exchange_rate' => $exchangeRate ?? 0,
                'error'         => $e->getMessage(),
            ]);
        }

        $invoice = DB::transaction(function () use ($user, $order, $type, $description, $amount, $metadata, $paymentUrl, $paymentReference) {
            $last          = Invoice::lockForUpdate()->max('id') ?? 0;
            $invoiceNumber = 'INV-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);

            return Invoice::create([
                'user_id'            => $user->id,
                'order_id'           => $order?->id,
                'invoice_number'     => $invoiceNumber,
                'type'               => $type,
                'description'        => $description,
                'amount'             => $amount,
                'status'             => 'pending',
                'payment_reference'  => $paymentReference,
                'payment_url'        => $paymentUrl,
                'metadata'           => !empty($metadata) ? $metadata : null,
            ]);
        });

        $this->notifications->sendInvoiceCreated($invoice->setRelation('user', $user));

        return $invoice;
    }

    public function list(User $user): Collection
    {
        return Invoice::where('user_id', $user->id)->with('order')->latest()->get();
    }

    public function listAll(): Collection
    {
        return Invoice::with(['user', 'order'])->latest()->get();
    }

    public function find(Invoice $invoice): Invoice
    {
        return $invoice->load(['user', 'order']);
    }

    public function authorize(User $user, Invoice $invoice): void
    {
        if ($user->role !== 'admin' && $invoice->user_id !== $user->id) {
            abort(403, 'Unauthorized.');
        }
    }

    public function markPaid(Invoice $invoice, string $reference, array $paystackData = []): void
    {
        $paymentMeta = array_filter([
            'transaction_id'   => $paystackData['id']                            ?? null,
            'domain'           => $paystackData['domain']                        ?? null,
            'channel'          => $paystackData['channel']                       ?? null,
            'currency'         => $paystackData['currency']                      ?? null,
            'amount_paid_kobo' => $paystackData['amount']                        ?? null,
            'fees_kobo'        => $paystackData['fees']                          ?? null,
            'ip_address'       => $paystackData['ip_address']                    ?? null,
            'gateway_response' => $paystackData['gateway_response']              ?? null,
            'paystack_paid_at' => $paystackData['paid_at']                       ?? null,
            'auth_bank'        => $paystackData['authorization']['bank']         ?? null,
            'auth_last4'       => $paystackData['authorization']['last4']        ?? null,
            'auth_card_type'   => $paystackData['authorization']['card_type']    ?? null,
            'auth_brand'       => $paystackData['authorization']['brand']        ?? null,
            'auth_country'     => $paystackData['authorization']['country_code'] ?? null,
            'customer_code'    => $paystackData['customer']['customer_code']     ?? null,
        ], fn ($v) => $v !== null);

        $invoice->update([
            'status'            => 'paid',
            'paid_at'           => now(),
            'payment_reference' => $reference,
            'metadata'          => array_merge($invoice->metadata ?? [], ['payment' => $paymentMeta]),
        ]);
    }

}
