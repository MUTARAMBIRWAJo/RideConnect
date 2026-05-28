<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MobileUser;
use App\Models\User;
use App\Models\Ride;
use App\Models\Booking;
use App\Models\Trip;

// Create mobile user + app user
$mobile = MobileUser::query()->create([
    'first_name' => 'Test',
    'last_name' => 'Passenger',
    'email' => 'test-passenger+'.uniqid().'@example.test',
    'phone' => '+2507'.rand(10000000,99999999),
    'role' => 'PASSENGER',
    'is_verified' => true,
    'password' => bcrypt('password'),
]);

$user = User::query()->create([
    'name' => 'Test Passenger',
    'email' => $mobile->email,
    'password' => bcrypt('password'),
    'role' => 'PASSENGER',
    'is_approved' => true,
    'mobile_user_id' => $mobile->id,
]);

// Create a BUS ride
$ride = Ride::factory()->create([
    'transport_type' => Ride::TRANSPORT_BUS,
    'travel_mode' => Ride::MODE_SCHEDULED,
]);

// Create booking for user
$booking = Booking::create([
    'user_id' => $user->id,
    'ride_id' => $ride->id,
    'seats_booked' => 1,
    'total_price' => $ride->price_per_seat ?? 1000,
    'currency' => 'RWF',
    'status' => 'PENDING',
    'pickup_address' => $ride->origin_address,
    'dropoff_address' => $ride->destination_address,
    'pickup_lat' => $ride->origin_lat,
    'pickup_lng' => $ride->origin_lng,
    'dropoff_lat' => $ride->destination_lat,
    'dropoff_lng' => $ride->destination_lng,
]);

// Now simulate createFromBooking logic to create Trip
$passengerMobileUserId = $booking->user?->mobile_user_id ? (int) $booking->user->mobile_user_id : (int) $booking->user_id;

$trip = Trip::create([
    'booking_id' => $booking->id,
    'ride_id' => $booking->ride_id,
    'passenger_id' => $passengerMobileUserId,
    'driver_id' => null,
    'pickup_location' => $booking->pickup_address ?: $booking->ride?->origin_address,
    'pickup_place_name' => $booking->pickup_address ?: $booking->ride?->origin_address,
    'pickup_lat' => $booking->pickup_lat ?: $booking->ride?->origin_lat,
    'pickup_lng' => $booking->pickup_lng ?: $booking->ride?->origin_lng,
    'dropoff_location' => $booking->dropoff_address ?: $booking->ride?->destination_address,
    'dropoff_place_name' => $booking->dropoff_address ?: $booking->ride?->destination_address,
    'dropoff_lat' => $booking->dropoff_lat ?: $booking->ride?->destination_lat,
    'dropoff_lng' => $booking->dropoff_lng ?: $booking->ride?->destination_lng,
    'fare' => $booking->total_price,
    'status' => 'PENDING',
    'requested_at' => $booking->created_at,
]);

// Now query trips visible to user using same fallback logic
$passengerFallbackIds = array_filter([(int) $user->mobile_user_id, (int) $user->id]);
$found = Trip::whereIn('passenger_id', $passengerFallbackIds)->get();

echo "Created trip id: {$trip->id}\n";
echo "Passenger fallback ids: ".json_encode($passengerFallbackIds)."\n";
echo "Trips found for passenger: ".count($found)."\n";
foreach ($found as $t) {
    echo "- trip {$t->id} passenger_id={$t->passenger_id} booking_id={$t->booking_id}\n";
}
