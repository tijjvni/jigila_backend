<?php

namespace Database\Factories;

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
            'auction_source'    => fake()->randomElement(['Copart', 'IAAI']),
            'condition'         => fake()->randomElement(['Run and Drive', 'Non-Runner', 'Forklift']),
            'already_purchased' => $purchased,
            'bid_price'         => $purchased ? null : (string) fake()->numberBetween(1000, 20000),
            'vehicle_stock_no'  => $purchased ? fake()->bothify('VS-####') : null,
            'buyer_no'          => $purchased ? fake()->bothify('BN-####') : null,
            'buyer_code'        => $purchased ? fake()->bothify('BC-####') : null,
            'services'          => fake()->randomElements(['trucking', 'shipping'], fake()->numberBetween(0, 2)),
            'status'            => fake()->randomElement(['pending', 'processing', 'in_transit', 'at_port', 'delivered', 'cancelled']),
        ];
    }
}
