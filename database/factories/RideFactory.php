<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ride>
 */
class RideFactory extends Factory
{
    protected $model = Ride::class;

    public function definition(): array
    {
        $departureTime = $this->faker->dateTimeBetween('+1 day', '+30 days');
        
        return [
            'driver_id' => Driver::factory(),
            'vehicle_id' => Vehicle::factory(),
            'origin_address' => $this->faker->address(),
            'origin_lat' => $this->faker->latitude(),
            'origin_lng' => $this->faker->longitude(),
            'destination_address' => $this->faker->address(),
            'destination_lat' => $this->faker->latitude(),
            'destination_lng' => $this->faker->longitude(),
            'departure_time' => $departureTime,
            'arrival_time_estimated' => (clone $departureTime)->modify('+2 hours'),
            'available_seats' => $this->faker->numberBetween(1, 5),
            'price_per_seat' => $this->faker->randomFloat(2, 5000, 25000),
            'currency' => 'RWF',
            'description' => $this->faker->sentence(),
            'status' => 'scheduled',
            'ride_type' => 'shared',
            'luggage_allowed' => $this->faker->boolean(),
            'pets_allowed' => $this->faker->boolean(25),
            'smoking_allowed' => false,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Indicate that the ride is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'departure_time' => now()->subHours(3),
            'arrival_time_estimated' => now()->subHours(1),
        ]);
    }

    /**
     * Indicate that the ride is cancelled.
     */
    public function cancelled(): static
    {
        $now = now();
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => $now,
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the ride is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'departure_time' => now()->subHours(1),
            'arrival_time_estimated' => now()->addHours(1),
        ]);
    }
}
