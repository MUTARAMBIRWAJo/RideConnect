<?php

namespace Database\Seeders;

use App\Models\Ride;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RideSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rides = [
            [
                'driver_id' => 1,
                'vehicle_id' => 1,
                'origin_address' => 'Kigali City Center, Rwanda',
                'origin_lat' => -1.9706,
                'origin_lng' => 30.0444,
                'destination_address' => 'Musanze, Rwanda',
                'destination_lat' => -1.4995,
                'destination_lng' => 29.6333,
                'departure_time' => now()->addDays(2)->setHour(8)->setMinute(0),
                'arrival_time_estimated' => now()->addDays(2)->setHour(11)->setMinute(30),
                'available_seats' => 3,
                'price_per_seat' => 25.00,
                'currency' => 'RWF',
                'description' => 'Comfortable ride to Musanze. Stops at Nyabihu junction.',
                'status' => 'scheduled',
                'ride_type' => 'one-way',
                'luggage_allowed' => true,
                'pets_allowed' => false,
                'smoking_allowed' => false,
            ],
            [
                'driver_id' => 2,
                'vehicle_id' => 2,
                'origin_address' => 'Kigali International Airport, Rwanda',
                'origin_lat' => -1.9686,
                'origin_lng' => 30.1394,
                'destination_address' => 'Huye, Rwanda',
                'destination_lat' => -2.5969,
                'destination_lng' => 29.5944,
                'departure_time' => now()->addDays(3)->setHour(14)->setMinute(0),
                'arrival_time_estimated' => now()->addDays(3)->setHour(18)->setMinute(30),
                'available_seats' => 2,
                'price_per_seat' => 35.00,
                'currency' => 'RWF',
                'description' => 'Airport transfer to Huye. Quick and safe journey.',
                'status' => 'scheduled',
                'ride_type' => 'one-way',
                'luggage_allowed' => true,
                'pets_allowed' => false,
                'smoking_allowed' => false,
            ],
            [
                'driver_id' => 3,
                'vehicle_id' => 3,
                'origin_address' => 'Rubavu, Rwanda',
                'origin_lat' => -1.6833,
                'origin_lng' => 29.2667,
                'destination_address' => 'Kigali City Center, Rwanda',
                'destination_lat' => -1.9706,
                'destination_lng' => 30.0444,
                'departure_time' => now()->addDays(1)->setHour(7)->setMinute(0),
                'arrival_time_estimated' => now()->addDays(1)->setHour(11)->setMinute(0),
                'available_seats' => 3,
                'price_per_seat' => 20.00,
                'currency' => 'RWF',
                'description' => 'Morning ride from Rubavu to Kigali. Lake Kivu view on the way.',
                'status' => 'scheduled',
                'ride_type' => 'one-way',
                'luggage_allowed' => true,
                'pets_allowed' => true,
                'smoking_allowed' => false,
            ],
        ];

        foreach ($rides as $ride) {
            Ride::create($ride);
        }
    }
}
