<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ride_id' => Ride::factory(),
            'seats_booked' => $this->faker->numberBetween(1, 4),
            'total_price' => $this->faker->randomFloat(2, 1000, 100000),
            'currency' => 'RWF',
            'status' => 'confirmed',
            'pickup_address' => $this->faker->address(),
            'pickup_lat' => $this->faker->latitude(),
            'pickup_lng' => $this->faker->longitude(),
            'dropoff_address' => $this->faker->address(),
            'dropoff_lat' => $this->faker->latitude(),
            'dropoff_lng' => $this->faker->longitude(),
            'special_requests' => null,
            'confirmed_at' => now(),
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }
}
