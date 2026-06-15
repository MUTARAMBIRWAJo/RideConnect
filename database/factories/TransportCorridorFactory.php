<?php

namespace Database\Factories;

use App\Models\TransportCorridor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransportCorridor>
 */
class TransportCorridorFactory extends Factory
{
    protected $model = TransportCorridor::class;

    public function definition(): array
    {
        return [
            'corridor_code' => $this->faker->unique()->bothify('CC-###'),
            'corridor_name' => $this->faker->streetName() . ' Corridor',
            'start_stop_id' => null,
            'end_stop_id' => null,
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => $this->faker->numberBetween(15, 120),
        ];
    }
}
