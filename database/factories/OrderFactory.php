<?php

namespace Database\Factories;

use App\Enums\AuctionSource;
use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Enums\VehicleCondition;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $purchased = fake()->boolean();

        return [
            'user_id'           => User::factory(),
            'vin'               => strtoupper(fake()->bothify('?????????????????')),
            'stock_id'          => fake()->optional()->bothify('STK-####'),
            'auction_source'    => fake()->randomElement(AuctionSource::values()),
            'condition'         => fake()->randomElement(VehicleCondition::values()),
            'already_purchased' => $purchased,
            'bid_price'         => $purchased ? null : (string) fake()->numberBetween(1000, 20000),
            'vehicle_stock_no'  => $purchased ? fake()->bothify('VS-####') : null,
            'buyer_no'          => $purchased ? fake()->bothify('BN-####') : null,
            'buyer_code'        => $purchased ? fake()->bothify('BC-####') : null,
            'services'          => fake()->randomElements(ServiceType::values(), fake()->numberBetween(0, 2)),
            'status'            => fake()->randomElement(OrderStatus::values()),
            'pickup_location'   => null,
            'departure_port'    => null,
            'destination_port'  => null,
            'bid_status'        => null,
            'out_bid_price'     => null,
        ];
    }
}
