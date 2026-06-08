<?php

namespace App\Http\Requests\Admin;

use App\Enums\DeparturePort;
use App\Enums\DestinationPort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pickup_location'  => 'nullable|string|max:255',
            'departure_port'   => ['nullable', Rule::in(DeparturePort::values())],
            'destination_port' => ['nullable', Rule::in(DestinationPort::values())],
        ];
    }
}
