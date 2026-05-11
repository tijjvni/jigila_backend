<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vin'               => 'sometimes|string|max:17',
            'stock_id'          => 'nullable|string',
            'auction_source'    => 'sometimes|in:Copart,IAAI,Co-parts',
            'condition'         => 'sometimes|in:Runner,Runs and drives,Enhanced vehicle,Stationary',
            'already_purchased' => 'sometimes|boolean',
            'bid_price'         => 'nullable|string',
            'vehicle_stock_no'  => 'nullable|string',
            'buyer_no'          => 'nullable|string',
            'buyer_code'        => 'nullable|string',
            'services'          => 'nullable|array',
            'services.*'        => 'in:trucking,shipping',
            'status'            => 'sometimes|in:pending,processing,in_transit,at_port,delivered,cancelled',
        ];
    }
}
