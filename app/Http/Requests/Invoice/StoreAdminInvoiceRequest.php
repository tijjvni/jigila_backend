<?php

namespace App\Http\Requests\Invoice;

use App\Enums\DeparturePort;
use App\Enums\DestinationPort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'description'               => 'required|string|max:1000',
            'amount'                    => 'required|numeric|min:0.01',
            'metadata'                  => 'sometimes|array',
            'metadata.departure_port'   => ['sometimes', Rule::in(DeparturePort::values())],
            'metadata.destination_port' => ['sometimes', Rule::in(DestinationPort::values())],
            'metadata.notes'            => 'sometimes|string|max:2000',
        ];
    }
}
