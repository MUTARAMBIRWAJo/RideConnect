<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'make' => $this->faker->randomElement(['Toyota', 'Honda', 'Ford', 'BMW', 'Mercedes', 'Audi', 'Nissan', 'Hyundai']),
            'model' => $this->faker->randomElement(['Camry', 'Civic', 'F-150', 'X3', 'C-Class', 'A4', 'Altima', 'Sonata']),
            'year' => $this->faker->year(),
            'color' => $this->faker->colorName(),
            'vehicle_type' => $this->faker->randomElement(['sedan', 'suv', 'hatchback', 'van', 'motorcycle', 'compact']),
            'seats' => $this->faker->randomElement([4, 5, 7, 9, 14]),
            'air_conditioning' => $this->faker->boolean(70),
            'is_active' => true,
            'photo_url' => null,
            'verified_at' => now(),
        ];
    }

    /**
     * Indicate that the vehicle is not verified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the vehicle is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
