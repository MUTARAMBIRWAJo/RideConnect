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
        $reviews = [
            [
                'booking_id' => 1,
                'user_id' => 5,
                'driver_id' => 1,
                'ride_id' => 1,
                'rating' => 5,
                'comment' => 'Excellent driver! Very professional and friendly. The ride was smooth and on time.',
                'safety_rating' => 5,
                'punctuality_rating' => 5,
                'communication_rating' => 5,
                'vehicle_condition_rating' => 5,
                'reviewer_type' => 'passenger',
                'is_public' => true,
            ],
            [
                'booking_id' => 3,
                'user_id' => 7,
                'driver_id' => 3,
                'ride_id' => 3,
                'rating' => 4,
                'comment' => 'Good ride overall. Driver was friendly and the vehicle was comfortable.',
                'safety_rating' => 4,
                'punctuality_rating' => 4,
                'communication_rating' => 5,
                'vehicle_condition_rating' => 4,
                'reviewer_type' => 'passenger',
                'is_public' => true,
            ],
        ];

        foreach ($reviews as $review) {
            Review::create($review);
        }
    }
}
