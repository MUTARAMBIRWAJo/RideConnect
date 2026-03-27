<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bookingIds = \App\Models\Booking::orderBy('id')->pluck('id')->toArray();
        $userIds = \App\Models\User::orderBy('id')->pluck('id')->toArray();
        $driverIds = \App\Models\Driver::orderBy('id')->pluck('id')->toArray();
        $rideIds = \App\Models\Ride::orderBy('id')->pluck('id')->toArray();

        $reviews = [];
        if (!empty($bookingIds) && !empty($userIds) && !empty($driverIds) && !empty($rideIds)) {
            $reviews[] = [
                'booking_id' => $bookingIds[0],
                'user_id' => $userIds[0],
                'driver_id' => $driverIds[0],
                'ride_id' => $rideIds[0],
                'rating' => 5,
                'comment' => 'Excellent driver! Very professional and friendly. The ride was smooth and on time.',
                'safety_rating' => 5,
                'punctuality_rating' => 5,
                'communication_rating' => 5,
                'vehicle_condition_rating' => 5,
                'reviewer_type' => 'passenger',
                'is_public' => true,
            ];
            if (isset($bookingIds[1], $userIds[1], $driverIds[1], $rideIds[1])) {
                $reviews[] = [
                    'booking_id' => $bookingIds[1],
                    'user_id' => $userIds[1],
                    'driver_id' => $driverIds[1],
                    'ride_id' => $rideIds[1],
                    'rating' => 4,
                    'comment' => 'Good ride overall. Driver was friendly and the vehicle was comfortable.',
                    'safety_rating' => 4,
                    'punctuality_rating' => 4,
                    'communication_rating' => 5,
                    'vehicle_condition_rating' => 4,
                    'reviewer_type' => 'passenger',
                    'is_public' => true,
                ];
            }
        }

        foreach ($reviews as $review) {
            Review::create($review);
        }
    }
}
