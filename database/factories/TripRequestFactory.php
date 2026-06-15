<?php

namespace Database\Factories;

use App\Models\TripRequest;
use App\Models\User;
use App\Models\TransportCorridor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TripRequest>
 */
class TripRequestFactory extends Factory
{
    protected $model = TripRequest::class;

    public function definition(): array
    {
        return [
            'passenger_id' => User::factory(),
            'corridor_id' => TransportCorridor::factory(),
            'pickup_location' => $this->faker->address(),
            'pickup_lat' => $this->faker->latitude(),
            'pickup_lng' => $this->faker->longitude(),
            'dropoff_location' => $this->faker->address(),
            'dropoff_lat' => $this->faker->latitude(),
            'dropoff_lng' => $this->faker->longitude(),
            'status' => 'PENDING_MATCH',
            'currency' => 'RWF',
            'estimated_fare' => $this->faker->randomFloat(2, 500, 2000),
        ];
    }
}
