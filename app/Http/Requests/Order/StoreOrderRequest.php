<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vin'               => 'required|string|max:17',
            'stock_id'          => 'nullable|string',
            'auction_source'    => 'required|in:Copart,IAAI,Co-parts',
            'condition'         => 'required|in:Runner,Runs and drives,Enhanced vehicle,Stationary',
            'already_purchased' => 'required|boolean',
            'bid_price'         => 'required_if:already_purchased,false|nullable|string',
            'vehicle_stock_no'  => 'required_if:already_purchased,true|nullable|string',
            'buyer_no'          => 'required_if:already_purchased,true|nullable|string',
            'buyer_code'        => 'required_if:already_purchased,true|nullable|string',
            'services'          => 'nullable|array',
            'services.*'        => 'in:trucking,shipping',
        ];
    }
}
