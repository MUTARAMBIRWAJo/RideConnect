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
        // Fetch real user and ride IDs
        $userIds = \App\Models\User::orderBy('id')->pluck('id')->toArray();
        $rideIds = \App\Models\Ride::orderBy('id')->pluck('id')->toArray();

        // Fetch a RURA tariff for a real route (e.g., Nyabugogo-Remera)
        $ruraTariff = \App\Models\RuraTariff::where('origin_stop', 'NYABUGOGO BUS PARK')
            ->where('destination_stop', 'REMERA BUS PARK')
            ->orWhere(function($q) {
                $q->where('origin_stop', 'REMERA BUS PARK')->where('destination_stop', 'NYABUGOGO BUS PARK');
            })
            ->first();

        $bookings = [];
        if (!empty($userIds) && !empty($rideIds)) {
            // Booking 1: Nyabugogo-Remera (if available)
            $bookings[] = [
                'user_id' => $userIds[0],
                'ride_id' => $rideIds[0],
                'seats_booked' => 2,
                'total_price' => $ruraTariff ? $ruraTariff->fare_rwf * 2 : 500,
                'currency' => 'RWF',
                'status' => 'confirmed',
                'pickup_address' => $ruraTariff ? $ruraTariff->origin_stop : 'REMERA BUS PARK',
                'pickup_lat' => -1.9441,
                'pickup_lng' => 30.0619,
                'dropoff_address' => $ruraTariff ? $ruraTariff->destination_stop : 'NYABUGOGO BUS PARK',
                'dropoff_lat' => -1.9398,
                'dropoff_lng' => 30.0444,
                'special_requests' => 'We have two suitcases.',
                'confirmed_at' => now()->subDays(1),
            ];
            // Booking 2: Use next user/ride if available
            if (isset($userIds[1], $rideIds[1])) {
                $bookings[] = [
                    'user_id' => $userIds[1],
                    'ride_id' => $rideIds[1],
                    'seats_booked' => 1,
                    'total_price' => 3500,
                    'currency' => 'RWF',
                    'status' => 'pending',
                    'pickup_address' => 'Kigali Marriott Hotel',
                    'pickup_lat' => -1.9744,
                    'pickup_lng' => 30.0978,
                    'dropoff_address' => 'Huye University',
                    'dropoff_lat' => -2.5969,
                    'dropoff_lng' => 29.5944,
                    'special_requests' => null,
                ];
            }
            // Booking 3: Use next user/ride if available
            if (isset($userIds[2], $rideIds[2])) {
                $bookings[] = [
                    'user_id' => $userIds[2],
                    'ride_id' => $rideIds[2],
                    'seats_booked' => 1,
                    'total_price' => 2000,
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
                ];
            }
        }

        foreach ($bookings as $booking) {
            Booking::create($booking);
        }
    }
}
