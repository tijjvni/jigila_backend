<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'departure_port'   => 'nullable|string|max:255',
            'destination_port' => 'nullable|string|max:255',
        ];
    }
}
