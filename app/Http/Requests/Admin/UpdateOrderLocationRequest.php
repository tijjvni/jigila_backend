<?php

namespace App\Http\Requests\Admin;

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
            'departure_port'   => ['nullable', Rule::in([
                'houston_tx', 'baltimore_md', 'newark_nj', 'savannah_ga', 'los_angeles_ca',
            ])],
            'destination_port' => ['nullable', Rule::in([
                'tin_can_lagos', 'lagos_apapa', 'tema_ghana',
            ])],
        ];
    }
}
