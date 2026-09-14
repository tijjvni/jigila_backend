<?php

namespace App\Http\Resources;

/**
 * Single-invoice representation: everything InvoiceResource returns, plus the
 * `metadata` blob (creation context and the Paystack payment record).
 *
 * Metadata is ~49% of a serialized paid invoice and is never shown in a list,
 * so list endpoints use the lighter InvoiceResource and detail endpoints use
 * this. Access is still admin-only — metadata is internal payment data.
 */
class InvoiceDetailResource extends InvoiceResource
{
    protected function includesMetadata(): bool
    {
        return auth()->user()?->role === 'admin';
    }
}
