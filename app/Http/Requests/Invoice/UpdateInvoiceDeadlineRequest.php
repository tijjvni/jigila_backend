<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin setting a payment deadline directly (spec 4) — either an absolute
 * timestamp or a number of hours from now.
 */
class UpdateInvoiceDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'payment_due_at' => 'required_without:deadline_hours|nullable|date',
            'deadline_hours' => 'required_without:payment_due_at|nullable|integer|min:1|max:8760',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_due_at.required_without' => 'Provide either a deadline timestamp or a number of hours.',
            'deadline_hours.required_without' => 'Provide either a deadline timestamp or a number of hours.',
        ];
    }
}
