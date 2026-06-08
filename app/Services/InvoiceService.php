<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(private PaystackService $paystack) {}

    public function create(
        User    $user,
        ?Order  $order,
        string  $type,
        string  $description,
        float   $amount,
        array   $metadata = []
    ): Invoice {
        $invoiceNumber = $this->generateNumber();

        $paymentUrl       = null;
        $paymentReference = null;

        try {
            $callbackUrl      = config('services.paystack.callback_url', url('/invoice'));
            $paystackRef      = 'jig_' . Str::uuid()->toString();
            $data = $this->paystack->initializeTransaction(
                $user->email,
                (int) round($amount * 100),
                $paystackRef,
                $callbackUrl,
            );
            $paymentUrl       = $data['authorization_url'];
            $paymentReference = $data['reference'];
        } catch (\Throwable) {
            // Paystack is optional — invoice is still created without a payment link
        }

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
    }

    public function list(User $user): Collection
    {
        return $user->role === 'admin'
            ? Invoice::with(['user', 'order'])->latest()->get()
            : Invoice::where('user_id', $user->id)->with('order')->latest()->get();
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

    private function generateNumber(): string
    {
        $last = Invoice::max('id') ?? 0;

        return 'INV-' . str_pad($last + 1, 6, '0', STR_PAD_LEFT);
    }
}
