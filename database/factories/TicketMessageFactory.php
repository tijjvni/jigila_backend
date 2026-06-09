<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id'      => Ticket::factory(),
            'user_id'        => User::factory(),
            'body'           => fake()->paragraph(),
            'is_staff_reply' => fake()->boolean(30),
        ];
    }

    public function staffReply(): static
    {
        return $this->state(['is_staff_reply' => true]);
    }
}
