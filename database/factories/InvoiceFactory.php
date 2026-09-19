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

            // Every real invoice carries a payment deadline from issuance
            // (spec 4), so the default factory invoice does too.
            'payment_due_at'    => now()->addHours(72),
            'deadline_hours'    => 72,
            'due_date'          => now()->addHours(72)->toDateString(),
            'late_fee_mode'     => 'percent',
            'late_fee_rate'     => 1.5,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }

    /** An invoice whose deadline lands exactly `$hours` from now. */
    public function dueIn(int $hours): static
    {
        return $this->state(fn () => [
            'payment_due_at' => now()->addHours($hours),
            'deadline_hours' => $hours,
            'due_date'       => now()->addHours($hours)->toDateString(),
        ]);
    }

    /**
     * An invoice `$days` past its deadline. `overdue_at` is left null so the
     * sweep still has the transition to make.
     */
    public function overdue(int $days = 1): static
    {
        return $this->state(fn () => [
            'payment_due_at' => now()->subDays($days),
            'due_date'       => now()->subDays($days)->toDateString(),
            'overdue_at'     => null,
        ]);
    }
}
