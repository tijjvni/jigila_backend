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
    public function __construct(private PaystackService $paystack) {}

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

        try {
            if ($exchangeRate <= 0) {
                throw new \RuntimeException('Exchange rate not configured. Admin must set the NGN/USD rate before invoices can be paid.');
            }

            $callbackUrl = config('services.paystack.callback_url', url('/invoice'));
            $paystackRef = 'jig_' . Str::uuid()->toString();
            // Invoice amount is stored in USD; Paystack bills in NGN kobo.
            $amountKobo = (int) round($amount * $exchangeRate * 100);
            $data = $this->paystack->initializeTransaction(
                $user->email,
                $amountKobo,
                $paystackRef,
                $callbackUrl,
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

        return DB::transaction(function () use ($user, $order, $type, $description, $amount, $metadata, $paymentUrl, $paymentReference) {
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

    public function markPaid(Invoice $invoice, string $reference): void
    {
        $invoice->update([
            'status'             => 'paid',
            'paid_at'            => now(),
            'payment_reference'  => $reference,
        ]);
    }

}
