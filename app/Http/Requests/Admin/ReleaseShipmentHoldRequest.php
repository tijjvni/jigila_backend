<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin lifting a payment hold by hand (spec 4) — a bank transfer that cleared
 * outside Paystack, a goodwill release, or a hold placed in error.
 */
class ReleaseShipmentHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:5|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Record why this hold is being lifted.',
        ];
    }
}
