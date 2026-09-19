<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            // `assignableValues()`, not `values()`: `payment_overdue` is set
            // by the deadline sweep together with the shipment hold and is
            // cleared by payment, an approved extension or an explicit
            // release — never by setting the status directly (spec 4).
            'status' => ['required', Rule::in(OrderStatus::assignableValues())],
        ];
    }
}
