<?php

namespace Database\Factories;

use App\Models\Manager;
use App\Models\Ticket;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'issued_by' => Manager::factory(),
            'reason' => $this->faker->randomElement(['speeding', 'reckless_driving', 'hazardous_parking', 'unruly_behavior', 'vehicle_condition']),
            'amount' => $this->faker->randomFloat(2, 5000, 50000),
            'status' => 'open',
            'issued_at' => now(),
        ];
    }

    /**
     * Indicate that the ticket is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    /**
     * Indicate that the ticket is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }
}
