<?php

namespace Database\Factories;

use App\Models\MobileUser;
use App\Models\User;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        $requestedAt = $this->faker->dateTimeBetween('-7 days', 'now');

        return [
            'passenger_id' => User::factory()->state(['role' => 'PASSENGER']),
            'driver_id' => null,
            'pickup_location' => $this->faker->address(),
            'pickup_lat' => $this->faker->latitude(),
            'pickup_lng' => $this->faker->longitude(),
            'pickup_zone' => $this->faker->word(),
            'dropoff_location' => $this->faker->address(),
            'dropoff_lat' => $this->faker->latitude(),
            'dropoff_lng' => $this->faker->longitude(),
            'dropoff_zone' => $this->faker->word(),
            'fare' => $this->faker->randomFloat(2, 5000, 25000),
            'actual_pickup_lat' => $this->faker->latitude(),
            'actual_pickup_lng' => $this->faker->longitude(),
            'actual_dropoff_lat' => $this->faker->latitude(),
            'actual_dropoff_lng' => $this->faker->longitude(),
            'actual_distance' => $this->faker->randomFloat(2, 1, 50),
            'actual_fare' => $this->faker->randomFloat(2, 5000, 25000),
            'status' => 'completed',
            'requested_at' => $requestedAt,
            'accepted_at' => (clone $requestedAt)->modify('+5 minutes'),
            'rejected_at' => null,
            'rejection_reason' => null,
            'started_at' => (clone $requestedAt)->modify('+10 minutes'),
            'completed_at' => (clone $requestedAt)->modify('+1 hour'),
            'paid_to_driver_at' => (clone $requestedAt)->modify('+2 hours'),
        ];
    }

    /**
     * Indicate that the trip is pending.
     */
    public function pending(): static
    {
        $requestedAt = now();

        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'requested_at' => $requestedAt,
            'accepted_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'paid_to_driver_at' => null,
        ]);
    }

    /**
     * Indicate that the trip is in progress.
     */
    public function inProgress(): static
    {
        $requestedAt = now()->subHours(1);

        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'requested_at' => $requestedAt,
            'accepted_at' => (clone $requestedAt)->modify('+5 minutes'),
            'started_at' => (clone $requestedAt)->modify('+10 minutes'),
            'completed_at' => null,
            'paid_to_driver_at' => null,
        ]);
    }

    /**
     * Indicate that the trip is cancelled.
     */
    public function cancelled(): static
    {
        $requestedAt = now()->subHours(2);

        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'requested_at' => $requestedAt,
            'accepted_at' => null,
            'rejected_at' => (clone $requestedAt)->modify('+5 minutes'),
            'rejection_reason' => 'Driver cancelled',
            'started_at' => null,
            'completed_at' => null,
            'paid_to_driver_at' => null,
        ]);
    }
}
