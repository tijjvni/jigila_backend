<?php

namespace App\Http\Requests\Admin;

use App\Enums\LateFeeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exchange_rate' => ['nullable', 'numeric', 'min:0.01'],

            // Payment deadline and late fee schedule (spec 4). Each key here
            // overrides the matching `config/orders.php` default at runtime;
            // invoices already raised keep the terms frozen at issuance.
            'payment_deadline_hours'       => ['nullable', 'integer', 'min:1', 'max:8760'],
            'deadline_extension_max_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'late_fee_mode'                => ['nullable', Rule::in(LateFeeMode::values())],
            'late_fee_flat_amount'         => ['nullable', 'numeric', 'min:0'],
            'late_fee_percent'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'late_fee_grace_hours'         => ['nullable', 'integer', 'min:0', 'max:720'],
            'late_fee_max_days'            => ['nullable', 'integer', 'min:1', 'max:365'],
            'late_fee_cap_percent'         => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
