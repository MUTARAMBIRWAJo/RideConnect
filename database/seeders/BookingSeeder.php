<?php

namespace Database\Seeders;

use App\Models\Booking;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bookings = [
            [
                'user_id' => 5,
                'ride_id' => 1,
                'seats_booked' => 2,
                'total_price' => 50.00,
                'currency' => 'RWF',
                'status' => 'confirmed',
                'pickup_address' => 'Kigali Heights, Kigali',
                'pickup_lat' => -1.9721,
                'pickup_lng' => 30.0578,
                'dropoff_address' => 'Musanze Bus Park',
                'dropoff_lat' => -1.4995,
                'dropoff_lng' => 29.6333,
                'special_requests' => 'We have two suitcases.',
                'confirmed_at' => now()->subDays(1),
            ],
            [
                'user_id' => 6,
                'ride_id' => 2,
                'seats_booked' => 1,
                'total_price' => 35.00,
                'currency' => 'RWF',
                'status' => 'pending',
                'pickup_address' => 'Kigali Marriott Hotel',
                'pickup_lat' => -1.9744,
                'pickup_lng' => 30.0978,
                'dropoff_address' => 'Huye University',
                'dropoff_lat' => -2.5969,
                'dropoff_lng' => 29.5944,
                'special_requests' => null,
            ],
            [
                'user_id' => 7,
                'ride_id' => 3,
                'seats_booked' => 1,
                'total_price' => 20.00,
                'currency' => 'RWF',
                'status' => 'confirmed',
                'pickup_address' => 'Rubavu Market',
                'pickup_lat' => -1.6833,
                'pickup_lng' => 29.2667,
                'dropoff_address' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9692,
                'dropoff_lng' => 30.0878,
                'special_requests' => 'Traveling with a small dog.',
                'confirmed_at' => now(),
            ],
        ];

        foreach ($bookings as $booking) {
            Booking::create($booking);
        }
    }
}
