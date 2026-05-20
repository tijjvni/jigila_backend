<?php

namespace App\Http\Requests\Admin;

use App\Enums\BidStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bid_status'    => ['required', Rule::in(BidStatus::values())],
            'out_bid_price' => 'nullable|numeric|min:0',
        ];
    }
}
