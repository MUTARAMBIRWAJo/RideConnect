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
        $userIds = \App\Models\User::orderBy('id')->pluck('id')->toArray();
        $bookingIds = \App\Models\Booking::orderBy('id')->pluck('id')->toArray();
        $rideIds = \App\Models\Ride::orderBy('id')->pluck('id')->toArray();
        $paymentIds = \App\Models\Payment::orderBy('id')->pluck('id')->toArray();
        $reviewIds = \App\Models\Review::orderBy('id')->pluck('id')->toArray();

        $notifications = [];
        if (!empty($userIds) && !empty($bookingIds) && !empty($rideIds)) {
            $notifications[] = [
                'user_id' => $userIds[0],
                'type' => 'booking_confirmed',
                'title' => 'Booking Confirmed',
                'message' => 'Your booking for Kigali to Musanze has been confirmed.',
                'data' => ['booking_id' => $bookingIds[0], 'ride_id' => $rideIds[0]],
                'is_read' => true,
                'read_at' => now()->subHours(12),
            ];
            if (!empty($paymentIds)) {
                $notifications[] = [
                    'user_id' => $userIds[0],
                    'type' => 'payment_received',
                    'title' => 'Payment Successful',
                    'message' => 'Your payment of $50.00 has been processed successfully.',
                    'data' => ['payment_id' => $paymentIds[0], 'amount' => 50.00],
                    'is_read' => true,
                    'read_at' => now()->subHours(6),
                ];
            }
            if (isset($userIds[1], $bookingIds[1], $rideIds[1])) {
                $notifications[] = [
                    'user_id' => $userIds[1],
                    'type' => 'booking_pending',
                    'title' => 'Booking Pending',
                    'message' => 'Your booking for Kigali Airport to Huye is pending confirmation.',
                    'data' => ['booking_id' => $bookingIds[1], 'ride_id' => $rideIds[1]],
                    'is_read' => false,
                ];
            }
            if (isset($userIds[2], $bookingIds[0])) {
                $notifications[] = [
                    'user_id' => $userIds[2],
                    'type' => 'new_booking',
                    'title' => 'New Booking Request',
                    'message' => 'You have a new booking request for your ride to Musanze.',
                    'data' => ['booking_id' => $bookingIds[0], 'passenger_id' => $userIds[0]],
                    'is_read' => true,
                    'read_at' => now()->subDays(1),
                ];
            }
            if (isset($userIds[2], $reviewIds[0])) {
                $notifications[] = [
                    'user_id' => $userIds[2],
                    'type' => 'review_received',
                    'title' => 'New Review',
                    'message' => 'You received a 5-star review from a passenger!',
                    'data' => ['review_id' => $reviewIds[0], 'rating' => 5],
                    'is_read' => true,
                    'read_at' => now()->subHours(5),
                ];
            }
            if (isset($userIds[3], $rideIds[2])) {
                $notifications[] = [
                    'user_id' => $userIds[3],
                    'type' => 'ride_reminder',
                    'title' => 'Upcoming Ride',
                    'message' => 'Reminder: Your ride from Rubavu to Kigali departs tomorrow at 7:00 AM.',
                    'data' => ['ride_id' => $rideIds[2]],
                    'is_read' => false,
                ];
            }
        }

        foreach ($notifications as $notification) {
            Notification::create($notification);
        }
    }
}
