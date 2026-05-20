<?php

namespace App\Http\Requests\Order;

use App\Enums\AuctionSource;
use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Enums\VehicleCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'auction_source'    => ['sometimes', Rule::in(AuctionSource::values())],
            'condition'         => ['sometimes', Rule::in(VehicleCondition::values())],
            'already_purchased' => 'sometimes|boolean',
            'bid_price'         => 'nullable|string',
            'vehicle_stock_no'  => 'nullable|string',
            'buyer_no'          => 'nullable|string',
            'buyer_code'        => 'nullable|string',
            'services'          => 'nullable|array',
            'services.*'        => Rule::in(ServiceType::values()),
            'status'            => ['sometimes', Rule::in(OrderStatus::values())],
        ];
    }
}
