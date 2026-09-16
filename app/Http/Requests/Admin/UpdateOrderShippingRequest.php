<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingLine;
use App\Enums\ShippingType;
use App\Enums\VehicleCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'vessel_name'              => 'nullable|string|max:255',
            'container_number'         => 'nullable|string|max:64',
            'shipping_tracking_number' => 'nullable|string|max:64',
            'shipping_line'            => ['nullable', Rule::in(ShippingLine::values())],
            'shipping_type'            => ['nullable', Rule::in(ShippingType::values())],
            'current_vessel_location'  => 'nullable|string|max:255',
            'port_received_at'         => 'nullable|date',
            // An arrival window reads better than a raw day count (BUG-057),
            // so the admin sets a start and an end date.
            'eta_start'                => 'nullable|date',
            'eta_end'                  => 'nullable|date|after_or_equal:eta_start',

            // Condition confirmed by the export port authority. A vehicle sold
            // as a runner is often downgraded here (no fuel, flat tyres), so
            // this is recorded separately from the booked `condition`.
            'port_condition'              => ['nullable', Rule::in(VehicleCondition::values())],
            'port_condition_confirmed_at' => 'nullable|date',
            'port_condition_note'         => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'eta_end.after_or_equal' => 'The end of the arrival window must fall on or after the start.',
        ];
    }
}
