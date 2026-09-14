<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'reason.required' => 'Please tell us why you are cancelling this order.',
        ];
    }
}
