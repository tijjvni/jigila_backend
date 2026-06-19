<?php

namespace App\Http\Requests\Order;

use App\Enums\AuctionSource;
use App\Enums\DeparturePort;
use App\Enums\DestinationPort;
use App\Enums\ServiceType;
use App\Enums\VehicleCondition;
use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'auction_source'    => ['required', Rule::in(AuctionSource::values())],
            'condition'         => ['required', Rule::in(VehicleCondition::values())],
            'already_purchased' => 'required|boolean',
            'bid_price'         => 'required_if:already_purchased,false|nullable|string',
            'vehicle_stock_no'  => 'required_if:already_purchased,true|nullable|string',
            'buyer_no'          => 'required_if:already_purchased,true|nullable|string',
            'buyer_code'        => 'required_if:already_purchased,true|nullable|string',
            'services'          => 'nullable|array',
            'services.*'        => Rule::in(ServiceType::values()),
            'vehicle_type'      => ['nullable', Rule::in(VehicleType::values())],
            'pickup_location'   => 'nullable|string|max:2',
            'departure_port'    => ['nullable', Rule::in(DeparturePort::values())],
            'destination_port'  => ['nullable', Rule::in(DestinationPort::values())],
        ];
    }
}
