<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description'               => 'required|string|max:1000',
            'amount'                    => 'required|numeric|min:0.01',
            'metadata'                  => 'sometimes|array',
            'metadata.departure_port'   => 'sometimes|string|max:255',
            'metadata.destination_port' => 'sometimes|string|max:255',
            'metadata.notes'            => 'sometimes|string|max:2000',
        ];
    }
}
