<?php

namespace App\Http\Requests\Invoice;

use App\Enums\RefundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRefundStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'refund_status' => ['required', Rule::in(RefundStatus::values())],
            // Lets an admin settle on a partial refund; defaults to the full
            // invoice amount when omitted.
            'amount'        => 'nullable|numeric|min:0.01',
            'reason'        => 'nullable|string|max:500',
        ];
    }
}
