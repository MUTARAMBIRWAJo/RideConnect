<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'license_number' => $this->faker->unique()->numerify('DL-##########'),
            'license_plate' => $this->faker->unique()->bothify('RW-###-??'),
            'status' => 'approved',
            'availability_status' => 'online',
            'current_latitude' => $this->faker->latitude(),
            'current_longitude' => $this->faker->longitude(),
            'last_online_at' => now(),
            'total_rides' => $this->faker->numberBetween(0, 100),
            'rating' => $this->faker->randomFloat(2, 3.5, 5.0),
            'rating_count' => $this->faker->numberBetween(0, 50),
            'balance' => $this->faker->randomFloat(2, 0, 50000),
            'approved_at' => now()->subDays($this->faker->numberBetween(1, 30)),
        ];
    }

    /**
     * Indicate that the driver is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability_status' => 'offline',
        ]);
    }

    /**
     * Indicate that the driver is pending approval.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
        ]);
    }
}
