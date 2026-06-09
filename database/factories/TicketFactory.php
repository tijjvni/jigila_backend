<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        static $count = 0;
        $count++;

        return [
            'user_id'       => User::factory(),
            'ticket_number' => 'TKT-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT),
            'subject'       => fake()->sentence(6),
            'status'        => fake()->randomElement(['open', 'in_progress', 'resolved', 'closed']),
        ];
    }

    public function open(): static
    {
        return $this->state(['status' => 'open']);
    }

    public function resolved(): static
    {
        return $this->state(['status' => 'resolved']);
    }
}
