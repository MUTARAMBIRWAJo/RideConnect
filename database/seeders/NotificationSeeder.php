<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $notifications = [
            [
                'user_id' => 5,
                'type' => 'booking_confirmed',
                'title' => 'Booking Confirmed',
                'message' => 'Your booking for Kigali to Musanze has been confirmed.',
                'data' => ['booking_id' => 1, 'ride_id' => 1],
                'is_read' => true,
                'read_at' => now()->subHours(12),
            ],
            [
                'user_id' => 5,
                'type' => 'payment_received',
                'title' => 'Payment Successful',
                'message' => 'Your payment of $50.00 has been processed successfully.',
                'data' => ['payment_id' => 1, 'amount' => 50.00],
                'is_read' => true,
                'read_at' => now()->subHours(6),
            ],
            [
                'user_id' => 6,
                'type' => 'booking_pending',
                'title' => 'Booking Pending',
                'message' => 'Your booking for Kigali Airport to Huye is pending confirmation.',
                'data' => ['booking_id' => 2, 'ride_id' => 2],
                'is_read' => false,
            ],
            [
                'user_id' => 2,
                'type' => 'new_booking',
                'title' => 'New Booking Request',
                'message' => 'You have a new booking request for your ride to Musanze.',
                'data' => ['booking_id' => 1, 'passenger_id' => 5],
                'is_read' => true,
                'read_at' => now()->subDays(1),
            ],
            [
                'user_id' => 2,
                'type' => 'review_received',
                'title' => 'New Review',
                'message' => 'You received a 5-star review from a passenger!',
                'data' => ['review_id' => 1, 'rating' => 5],
                'is_read' => true,
                'read_at' => now()->subHours(5),
            ],
            [
                'user_id' => 7,
                'type' => 'ride_reminder',
                'title' => 'Upcoming Ride',
                'message' => 'Reminder: Your ride from Rubavu to Kigali departs tomorrow at 7:00 AM.',
                'data' => ['ride_id' => 3],
                'is_read' => false,
            ],
        ];

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }
}
