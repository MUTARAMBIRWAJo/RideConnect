<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MLTrainingVolumeSeeder extends Seeder
{
    use WithoutModelEvents;

    private const TARGET_PASSENGERS = 700;

    private const TARGET_DRIVER_MOBILE_USERS = 360;

    private const TARGET_DRIVERS = 320;

    private const TARGET_VEHICLES = 320;

    private const TARGET_RIDES = 1000;

    private const TARGET_BOOKINGS = 1000;

    private const TARGET_PAYMENTS = 900;

    private const TARGET_TRIPS = 1000;

    private const TARGET_REVIEWS = 500;

    /**
     * Kigali-centric anchors to generate realistic spatial and temporal variability.
     */
    private const HOTSPOTS = [
        ['name' => 'Kigali CBD', 'lat' => -1.9536, 'lng' => 30.0606],
        ['name' => 'Kigali International Airport', 'lat' => -1.9686, 'lng' => 30.1394],
        ['name' => 'Kimironko', 'lat' => -1.9411, 'lng' => 30.1098],
        ['name' => 'Nyabugogo', 'lat' => -1.9456, 'lng' => 30.0444],
        ['name' => 'Remera', 'lat' => -1.9578, 'lng' => 30.1063],
        ['name' => 'Kacyiru', 'lat' => -1.9400, 'lng' => 30.0700],
        ['name' => 'Nyamirambo', 'lat' => -1.9750, 'lng' => 30.0400],
        ['name' => 'Huye', 'lat' => -2.5969, 'lng' => 29.5944],
        ['name' => 'Musanze', 'lat' => -1.4995, 'lng' => 29.6333],
    ];

    public function run(): void
    {
        $faker = fake();
        $password = Hash::make('password123');

        $this->topUpMobileUsersAndUsers($faker, $password);
        $this->topUpDrivers($faker);
        $this->topUpVehicles($faker);
        $this->topUpRides($faker);
        $this->topUpBookings($faker);
        $this->topUpPayments();
        $this->topUpTrips($faker);
        $this->topUpReviews($faker);
    }

    private function topUpMobileUsersAndUsers($faker, string $password): void
    {
        $existingPassengers = (int) DB::table('mobile_users')->where('role', 'PASSENGER')->count();
        $existingDrivers = (int) DB::table('mobile_users')->where('role', 'DRIVER')->count();

        $newPassengerCount = max(0, self::TARGET_PASSENGERS - $existingPassengers);
        $newDriverCount = max(0, self::TARGET_DRIVER_MOBILE_USERS - $existingDrivers);

        $mobileRows = [];
        $baseTime = now()->subMonths(8);

        for ($i = 0; $i < $newPassengerCount; $i++) {
            $fullName = $faker->unique()->name();
            $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];
            $firstName = $parts[0] ?? 'Passenger';
            $lastName = $parts[1] ?? 'User';

            $mobileRows[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => '+25079'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => strtolower('passenger_'.Str::uuid().'@example.com'),
                'password' => $password,
                'role' => 'PASSENGER',
                'profile_photo' => null,
                'is_verified' => random_int(1, 100) <= 92,
                'created_at' => $baseTime->copy()->addMinutes(random_int(0, 60 * 24 * 220)),
                'updated_at' => now(),
            ];
        }

        for ($i = 0; $i < $newDriverCount; $i++) {
            $fullName = $faker->unique()->name();
            $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];
            $firstName = $parts[0] ?? 'Driver';
            $lastName = $parts[1] ?? 'User';

            $mobileRows[] = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => '+25078'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
                'email' => strtolower('driver_'.Str::uuid().'@example.com'),
                'password' => $password,
                'role' => 'DRIVER',
                'profile_photo' => null,
                'is_verified' => random_int(1, 100) <= 95,
                'created_at' => $baseTime->copy()->addMinutes(random_int(0, 60 * 24 * 220)),
                'updated_at' => now(),
            ];
        }

        if (! empty($mobileRows)) {
            foreach (array_chunk($mobileRows, 500) as $chunk) {
                DB::table('mobile_users')->insert($chunk);
            }
        }

        $mobileUsersWithoutUser = DB::table('mobile_users as mu')
            ->leftJoin('users as u', 'u.mobile_user_id', '=', 'mu.id')
            ->whereNull('u.id')
            ->select(['mu.id', 'mu.first_name', 'mu.last_name', 'mu.phone', 'mu.email', 'mu.password', 'mu.role', 'mu.is_verified', 'mu.created_at'])
            ->get();

        if ($mobileUsersWithoutUser->isEmpty()) {
            return;
        }

        $userRows = [];
        foreach ($mobileUsersWithoutUser as $mobileUser) {
            $isDriver = $mobileUser->role === 'DRIVER';

            $userRows[] = [
                'name' => trim($mobileUser->first_name.' '.$mobileUser->last_name),
                'email' => $mobileUser->email,
                'role' => $mobileUser->role,
                'mobile_user_id' => $mobileUser->id,
                'manager_id' => null,
                'phone' => $mobileUser->phone,
                'profile_photo' => null,
                'is_verified' => (bool) $mobileUser->is_verified,
                'password' => $mobileUser->password,
                'remember_token' => Str::random(10),
                'is_approved' => $isDriver,
                'approved_by' => null,
                'approved_at' => $isDriver ? now()->subDays(random_int(5, 120)) : null,
                'created_at' => $mobileUser->created_at,
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($userRows, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }
    }

    private function topUpDrivers($faker): void
    {
        $current = (int) DB::table('drivers')->count();
        $needed = max(0, self::TARGET_DRIVERS - $current);

        if ($needed === 0) {
            return;
        }

        $candidateUsers = DB::table('users as u')
            ->leftJoin('drivers as d', 'd.user_id', '=', 'u.id')
            ->whereNull('d.id')
            ->where('u.role', 'DRIVER')
            ->orderBy('u.id')
            ->limit($needed)
            ->select(['u.id'])
            ->get();

        if ($candidateUsers->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($candidateUsers as $index => $user) {
            $rows[] = [
                'user_id' => $user->id,
                'license_number' => 'DL-'.now()->format('Y').'-'.str_pad((string) ($user->id + 1000), 8, '0', STR_PAD_LEFT),
                'license_plate' => 'RA'.strtoupper(Str::random(2)).'-'.str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT),
                'status' => random_int(1, 100) <= 94 ? 'approved' : 'pending',
                'total_rides' => random_int(0, 700),
                'rating' => round($faker->randomFloat(2, 3.4, 5.0), 2),
                'rating_count' => random_int(0, 450),
                'balance' => round($faker->randomFloat(2, 0, 400000), 2),
                'approved_at' => now()->subDays(random_int(1, 180)),
                'created_at' => now()->subDays(random_int(1, 220)),
                'updated_at' => now()->subMinutes($index),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('drivers')->insert($chunk);
        }
    }

    private function topUpVehicles($faker): void
    {
        $current = (int) DB::table('vehicles')->count();
        $needed = max(0, self::TARGET_VEHICLES - $current);

        if ($needed === 0) {
            return;
        }

        $driverIds = DB::table('drivers')->orderBy('id')->pluck('id')->all();
        if (empty($driverIds)) {
            return;
        }

        $types = ['sedan', 'suv', 'hatchback', 'van', 'compact'];
        $makes = ['Toyota', 'Honda', 'Hyundai', 'Nissan', 'Kia', 'Suzuki'];
        $models = ['Corolla', 'Camry', 'Civic', 'CR-V', 'Elantra', 'Sportage', 'Swift'];
        $colors = ['White', 'Silver', 'Black', 'Blue', 'Gray', 'Red'];

        $rows = [];
        for ($i = 0; $i < $needed; $i++) {
            $driverId = $driverIds[array_rand($driverIds)];
            $rows[] = [
                'driver_id' => $driverId,
                'make' => $makes[array_rand($makes)],
                'model' => $models[array_rand($models)],
                'year' => random_int(2010, 2025),
                'color' => $colors[array_rand($colors)],
                'vehicle_type' => $types[array_rand($types)],
                'seats' => random_int(3, 6),
                'air_conditioning' => random_int(1, 100) <= 88,
                'is_active' => random_int(1, 100) <= 94,
                'photo_url' => null,
                'verified_at' => now()->subDays(random_int(1, 180)),
                'created_at' => now()->subDays(random_int(1, 220)),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('vehicles')->insert($chunk);
        }
    }

    private function topUpRides($faker): void
    {
        $current = (int) DB::table('rides')->count();
        $needed = max(0, self::TARGET_RIDES - $current);

        if ($needed === 0) {
            return;
        }

        $driverIds = DB::table('drivers')->orderBy('id')->pluck('id')->all();
        $vehicleIds = DB::table('vehicles')->orderBy('id')->pluck('id')->all();

        if (empty($driverIds) || empty($vehicleIds)) {
            return;
        }

        $hasRequestTime = Schema::hasColumn('rides', 'request_time');
        $hasAssignedTime = Schema::hasColumn('rides', 'driver_assigned_time');
        $hasPickupTime = Schema::hasColumn('rides', 'pickup_time');
        $hasDropoffTime = Schema::hasColumn('rides', 'dropoff_time');
        $hasRideDuration = Schema::hasColumn('rides', 'ride_duration');
        $hasRideDistance = Schema::hasColumn('rides', 'ride_distance');
        $hasRideStatus = Schema::hasColumn('rides', 'ride_status');
        $hasPickupLat = Schema::hasColumn('rides', 'pickup_lat');
        $hasPickupLng = Schema::hasColumn('rides', 'pickup_lng');
        $hasDropoffLat = Schema::hasColumn('rides', 'dropoff_lat');
        $hasDropoffLng = Schema::hasColumn('rides', 'dropoff_lng');

        $rows = [];
        for ($i = 0; $i < $needed; $i++) {
            $origin = self::HOTSPOTS[array_rand(self::HOTSPOTS)];
            $destination = self::HOTSPOTS[array_rand(self::HOTSPOTS)];

            $requestTime = now()->subDays(random_int(1, 210))->subMinutes(random_int(0, 1440));
            $statusRoll = random_int(1, 100);
            $status = $statusRoll <= 8 ? 'cancelled' : ($statusRoll <= 72 ? 'completed' : ($statusRoll <= 90 ? 'in_progress' : 'scheduled'));

            $departure = $requestTime->copy()->addMinutes(random_int(5, 120));
            $durationMinutes = random_int(12, 95);
            $arrival = $departure->copy()->addMinutes($durationMinutes);

            $distanceKm = round($faker->randomFloat(2, 1.2, 35.0), 3);
            $surgeMultiplier = $this->isPeakHour((int) $requestTime->format('G')) ? $faker->randomFloat(2, 1.05, 1.60) : $faker->randomFloat(2, 0.90, 1.25);
            $pricePerSeat = round((700 + ($distanceKm * 500)) * $surgeMultiplier, 2);

            $cancelledAt = $status === 'cancelled' ? $departure->copy()->subMinutes(random_int(1, 4)) : null;
            $arrivalForStatus = $status === 'completed' ? $arrival : ($status === 'in_progress' ? now()->addMinutes(random_int(10, 65)) : null);

            $row = [
                'driver_id' => $driverIds[array_rand($driverIds)],
                'vehicle_id' => $vehicleIds[array_rand($vehicleIds)],
                'origin_address' => $origin['name'].', Rwanda',
                'origin_lat' => $this->jitter($origin['lat'], 0.0070),
                'origin_lng' => $this->jitter($origin['lng'], 0.0070),
                'destination_address' => $destination['name'].', Rwanda',
                'destination_lat' => $this->jitter($destination['lat'], 0.0070),
                'destination_lng' => $this->jitter($destination['lng'], 0.0070),
                'departure_time' => $departure,
                'arrival_time_estimated' => $arrivalForStatus,
                'available_seats' => random_int(1, 4),
                'price_per_seat' => $pricePerSeat,
                'currency' => 'RWF',
                'description' => $status === 'completed' ? 'Historical ride with complete telemetry.' : 'Scheduled route with variable demand pattern.',
                'status' => $status,
                'ride_type' => random_int(1, 100) <= 82 ? 'INTERCITY' : 'LOCAL',
                'luggage_allowed' => random_int(1, 100) <= 80,
                'pets_allowed' => random_int(1, 100) <= 20,
                'smoking_allowed' => random_int(1, 100) <= 5,
                'cancelled_at' => $cancelledAt,
                'cancellation_reason' => $status === 'cancelled' ? 'Demand mismatch during pickup window' : null,
                'created_at' => $requestTime,
                'updated_at' => $arrivalForStatus ?? $departure,
            ];

            if ($hasPickupLat) {
                $row['pickup_lat'] = $row['origin_lat'];
            }
            if ($hasPickupLng) {
                $row['pickup_lng'] = $row['origin_lng'];
            }
            if ($hasDropoffLat) {
                $row['dropoff_lat'] = $row['destination_lat'];
            }
            if ($hasDropoffLng) {
                $row['dropoff_lng'] = $row['destination_lng'];
            }
            if ($hasRequestTime) {
                $row['request_time'] = $requestTime;
            }
            if ($hasAssignedTime) {
                $row['driver_assigned_time'] = $requestTime->copy()->addMinutes(random_int(1, 9));
            }
            if ($hasPickupTime) {
                $row['pickup_time'] = $departure;
            }
            if ($hasDropoffTime) {
                $row['dropoff_time'] = $status === 'completed' ? $arrival : null;
            }
            if ($hasRideDuration) {
                $row['ride_duration'] = $status === 'completed' ? $durationMinutes : null;
            }
            if ($hasRideDistance) {
                $row['ride_distance'] = $distanceKm;
            }
            if ($hasRideStatus) {
                $row['ride_status'] = strtoupper($status);
            }

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('rides')->insert($chunk);
        }
    }

    private function topUpBookings($faker): void
    {
        $current = (int) DB::table('bookings')->count();
        $needed = max(0, self::TARGET_BOOKINGS - $current);

        if ($needed === 0) {
            return;
        }

        $userIds = DB::table('users')->where('role', 'PASSENGER')->orderBy('id')->pluck('id')->all();
        $rideRows = DB::table('rides')->orderBy('id')->select(['id', 'price_per_seat', 'origin_address', 'origin_lat', 'origin_lng', 'destination_address', 'destination_lat', 'destination_lng', 'status', 'departure_time'])->get();

        if (empty($userIds) || $rideRows->isEmpty()) {
            return;
        }

        $rows = [];
        for ($i = 0; $i < $needed; $i++) {
            $ride = $rideRows[array_rand($rideRows->all())];
            $seats = random_int(1, 3);
            $totalPrice = round(((float) $ride->price_per_seat) * $seats, 2);

            $status = $ride->status === 'completed'
                ? (random_int(1, 100) <= 85 ? 'completed' : 'confirmed')
                : ($ride->status === 'cancelled' ? 'cancelled' : (random_int(1, 100) <= 75 ? 'confirmed' : 'pending'));

            $confirmedAt = in_array($status, ['confirmed', 'completed'], true)
                ? Carbon::parse($ride->departure_time)->subMinutes(random_int(10, 180))
                : null;

            $rows[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'ride_id' => $ride->id,
                'seats_booked' => $seats,
                'total_price' => $totalPrice,
                'currency' => 'RWF',
                'status' => $status,
                'pickup_address' => $ride->origin_address,
                'pickup_lat' => $ride->origin_lat,
                'pickup_lng' => $ride->origin_lng,
                'dropoff_address' => $ride->destination_address,
                'dropoff_lat' => $ride->destination_lat,
                'dropoff_lng' => $ride->destination_lng,
                'special_requests' => random_int(1, 100) <= 12 ? $faker->sentence(6) : null,
                'confirmed_at' => $confirmedAt,
                'cancelled_at' => $status === 'cancelled' ? Carbon::parse($ride->departure_time)->subMinutes(random_int(5, 60)) : null,
                'cancellation_reason' => $status === 'cancelled' ? 'Passenger unavailable at pickup time' : null,
                'created_at' => Carbon::parse($ride->departure_time)->subMinutes(random_int(45, 360)),
                'updated_at' => Carbon::parse($ride->departure_time),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('bookings')->insert($chunk);
        }
    }

    private function topUpPayments(): void
    {
        $current = (int) DB::table('payments')->count();
        $needed = max(0, self::TARGET_PAYMENTS - $current);

        if ($needed === 0) {
            return;
        }

        $bookingRows = DB::table('bookings')
            ->leftJoin('payments', 'payments.booking_id', '=', 'bookings.id')
            ->whereNull('payments.id')
            ->orderBy('bookings.id')
            ->limit($needed)
            ->select(['bookings.id', 'bookings.user_id', 'bookings.total_price', 'bookings.status', 'bookings.created_at'])
            ->get();

        if ($bookingRows->isEmpty()) {
            return;
        }

        $hasProviderFields = Schema::hasColumn('payments', 'payment_provider')
            && Schema::hasColumn('payments', 'provider_transaction_id')
            && Schema::hasColumn('payments', 'webhook_event_id')
            && Schema::hasColumn('payments', 'verification_status');

        $methods = ['mobile_money', 'card', 'cash'];
        $providers = ['mtn_momo', 'airtel_money', 'stripe', 'cash'];

        $rows = [];
        foreach ($bookingRows as $booking) {
            $amount = (float) $booking->total_price;
            $platformFee = round($amount * 0.12, 2);
            $driverAmount = round($amount - $platformFee, 2);

            $status = $booking->status === 'completed'
                ? (random_int(1, 100) <= 92 ? 'completed' : 'processing')
                : ($booking->status === 'cancelled' ? 'failed' : 'pending');

            $paidAt = $status === 'completed' ? Carbon::parse($booking->created_at)->addMinutes(random_int(2, 90)) : null;
            $transactionId = 'TXN-'.strtoupper(Str::random(14));

            $row = [
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount' => $amount,
                'platform_fee' => $platformFee,
                'driver_amount' => $driverAmount,
                'currency' => 'RWF',
                'payment_method' => $methods[array_rand($methods)],
                'transaction_id' => $transactionId,
                'supabase_payment_id' => null,
                'status' => $status,
                'payment_details' => json_encode(['quality_score' => random_int(70, 99), 'risk_bucket' => random_int(1, 100) <= 7 ? 'high' : 'normal']),
                'paid_at' => $paidAt,
                'refunded_at' => null,
                'created_at' => $booking->created_at,
                'updated_at' => now(),
            ];

            if ($hasProviderFields) {
                $row['payment_provider'] = $providers[array_rand($providers)];
                $row['provider_transaction_id'] = 'PROV-'.strtoupper(Str::random(18));
                $row['webhook_event_id'] = 'WH-'.strtoupper(Str::random(20));
                $row['verification_status'] = $status === 'completed' ? 'verified' : 'pending';
            }

            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('payments')->insert($chunk);
        }
    }

    private function topUpTrips($faker): void
    {
        $current = (int) DB::table('trips')->count();
        $needed = max(0, self::TARGET_TRIPS - $current);

        if ($needed === 0) {
            return;
        }

        $passengerIds = DB::table('mobile_users')->where('role', 'PASSENGER')->pluck('id')->all();
        $driverIds = DB::table('drivers')->pluck('id')->all();

        if (empty($passengerIds) || empty($driverIds)) {
            return;
        }

        $rows = [];
        for ($i = 0; $i < $needed; $i++) {
            $pickup = self::HOTSPOTS[array_rand(self::HOTSPOTS)];
            $dropoff = self::HOTSPOTS[array_rand(self::HOTSPOTS)];

            $requestedAt = now()->subDays(random_int(1, 210))->subMinutes(random_int(0, 1200));
            $statusRoll = random_int(1, 100);
            $status = $statusRoll <= 8 ? 'CANCELLED' : ($statusRoll <= 72 ? 'COMPLETED' : ($statusRoll <= 90 ? 'STARTED' : 'PENDING'));

            $startedAt = in_array($status, ['STARTED', 'COMPLETED'], true) ? $requestedAt->copy()->addMinutes(random_int(3, 20)) : null;
            $completedAt = $status === 'COMPLETED' ? $startedAt?->copy()->addMinutes(random_int(10, 80)) : null;

            $distance = max(1.2, $faker->randomFloat(2, 1.2, 33.5));
            $fare = round(700 + ($distance * 470), 2);

            $rows[] = [
                'passenger_id' => $passengerIds[array_rand($passengerIds)],
                'driver_id' => $status === 'PENDING' ? null : $driverIds[array_rand($driverIds)],
                'pickup_location' => $pickup['name'].', Rwanda',
                'dropoff_location' => $dropoff['name'].', Rwanda',
                'pickup_lat' => $this->jitter($pickup['lat'], 0.0075),
                'pickup_lng' => $this->jitter($pickup['lng'], 0.0075),
                'dropoff_lat' => $this->jitter($dropoff['lat'], 0.0075),
                'dropoff_lng' => $this->jitter($dropoff['lng'], 0.0075),
                'pickup_zone' => str_replace(' ', '_', strtoupper($pickup['name'])),
                'dropoff_zone' => str_replace(' ', '_', strtoupper($dropoff['name'])),
                'fare' => $fare,
                'status' => $status,
                'requested_at' => $requestedAt,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'created_at' => $requestedAt,
                'updated_at' => $completedAt ?? $startedAt ?? $requestedAt,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('trips')->insert($chunk);
        }
    }

    private function topUpReviews($faker): void
    {
        $current = (int) DB::table('reviews')->count();
        $needed = max(0, self::TARGET_REVIEWS - $current);

        if ($needed === 0) {
            return;
        }

        $bookings = DB::table('bookings')
            ->leftJoin('reviews', 'reviews.booking_id', '=', 'bookings.id')
            ->join('rides', 'rides.id', '=', 'bookings.ride_id')
            ->whereNull('reviews.id')
            ->whereIn('bookings.status', ['confirmed', 'completed'])
            ->orderBy('bookings.id')
            ->limit($needed)
            ->select(['bookings.id as booking_id', 'bookings.user_id', 'bookings.ride_id', 'bookings.created_at'])
            ->get();

        if ($bookings->isEmpty()) {
            return;
        }

        $driverByRide = DB::table('rides')->pluck('driver_id', 'id');

        $rows = [];
        foreach ($bookings as $booking) {
            $driverId = (int) ($driverByRide[$booking->ride_id] ?? 0);
            if ($driverId <= 0) {
                continue;
            }

            $rating = random_int(2, 5);
            $rows[] = [
                'booking_id' => $booking->booking_id,
                'user_id' => $booking->user_id,
                'driver_id' => $driverId,
                'ride_id' => $booking->ride_id,
                'rating' => $rating,
                'comment' => $faker->sentence(12),
                'safety_rating' => max(1, min(5, $rating + random_int(-1, 1))),
                'punctuality_rating' => max(1, min(5, $rating + random_int(-1, 1))),
                'communication_rating' => max(1, min(5, $rating + random_int(-1, 1))),
                'vehicle_condition_rating' => max(1, min(5, $rating + random_int(-1, 1))),
                'reviewer_type' => 'passenger',
                'is_public' => random_int(1, 100) <= 93,
                'created_at' => Carbon::parse($booking->created_at)->addHours(random_int(1, 72)),
                'updated_at' => now(),
            ];
        }

        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('reviews')->insert($chunk);
        }
    }

    private function jitter(float $base, float $radius): float
    {
        $offset = (random_int(-1000, 1000) / 1000) * $radius;

        return round($base + $offset, 7);
    }

    private function isPeakHour(int $hour): bool
    {
        return ($hour >= 7 && $hour <= 10) || ($hour >= 17 && $hour <= 21);
    }
}
