<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        static $counter = 0;

        return [
            'user_id'           => User::factory(),
            'order_id'          => null,
            'invoice_number'    => 'INV-' . str_pad(++$counter, 6, '0', STR_PAD_LEFT),
            'type'              => fake()->randomElement(['bid', 'service', 'bid_deposit', 'bid_balance']),
            'description'       => fake()->sentence(),
            'amount'            => fake()->randomFloat(2, 100, 5000),
            'status'            => 'pending',
            'payment_reference' => null,
            'payment_url'       => null,
            'metadata'          => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }
}
