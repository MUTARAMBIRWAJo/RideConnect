<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class V3TripLifecycleSeeder extends Seeder
{
    private const PASSWORD = 'Test@12345';

    private array $locations = [
        'pickup' => ['name' => 'Kigali Convention Centre', 'lat' => -1.95437, 'lng' => 30.09292],
        'dropoff' => ['name' => 'Kigali International Airport', 'lat' => -1.96863, 'lng' => 30.13945],
        'driver' => ['name' => 'Kigali Heights', 'lat' => -1.95332, 'lng' => 30.09224],
    ];

    public function run(): void
    {
        $passenger = $this->seedUser('lifecycle.passenger@rideconnect.local', 'Lifecycle Passenger', 'PASSENGER', '+250788440001');
        $drivers = [
            'matching' => $this->seedDriver('lifecycle.driver.matching@rideconnect.local', 'Lifecycle Matching Driver', '+250788440101', 'sedan'),
            'assigned' => $this->seedDriver('lifecycle.driver.assigned@rideconnect.local', 'Lifecycle Assigned Driver', '+250788440102', 'suv'),
            'arrived' => $this->seedDriver('lifecycle.driver.arrived@rideconnect.local', 'Lifecycle Arrived Driver', '+250788440103', 'sedan'),
            'progress' => $this->seedDriver('lifecycle.driver.progress@rideconnect.local', 'Lifecycle Active Driver', '+250788440104', 'compact'),
            'completed' => $this->seedDriver('lifecycle.driver.completed@rideconnect.local', 'Lifecycle Completed Driver', '+250788440105', 'sedan'),
            'paid' => $this->seedDriver('lifecycle.driver.paid@rideconnect.local', 'Lifecycle Paid Driver', '+250788440106', 'suv'),
            'rated' => $this->seedDriver('lifecycle.driver.rated@rideconnect.local', 'Lifecycle Rated Driver', '+250788440107', 'sedan'),
        ];

        $states = [
            'MATCHING' => ['driver' => 'matching', 'event' => 'trip.offer.created'],
            'DRIVER_ASSIGNED' => ['driver' => 'assigned', 'event' => 'trip.driver.accepted'],
            'DRIVER_ARRIVED' => ['driver' => 'arrived', 'event' => 'trip.driver.arrived'],
            'IN_PROGRESS' => ['driver' => 'progress', 'event' => 'trip.started'],
            'COMPLETED' => ['driver' => 'completed', 'event' => 'trip.completed'],
            'PAID' => ['driver' => 'paid', 'event' => 'trip.payment.completed'],
            'RATED' => ['driver' => 'rated', 'event' => 'trip.rating.submitted'],
        ];

        foreach ($states as $status => $config) {
            $driver = $drivers[$config['driver']];
            $trip = $this->seedTrip($passenger, $driver, $status);
            $this->seedOffer($trip, $driver, $status);
            $this->seedTripEvent($trip, $driver, $config['event']);
            $this->seedActiveTrip($trip, $driver);
        }

        $this->command?->info('Seeded V3 trip lifecycle demo data.');
        $this->command?->info('Passenger: lifecycle.passenger@rideconnect.local / '.self::PASSWORD);
        $this->command?->info('Drivers: lifecycle.driver.{matching,assigned,arrived,progress,completed,paid,rated}@rideconnect.local / '.self::PASSWORD);
    }

    private function seedUser(string $email, string $name, string $role, string $phone): array
    {
        [$firstName, $lastName] = explode(' ', $name, 2) + ['', 'User'];
        $mobileUser = $this->upsert('mobile_users', ['email' => $email], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
            'is_verified' => true,
        ]);

        return $this->upsert('users', ['email' => $email], [
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
            'mobile_user_id' => $mobileUser['id'],
            'phone' => $phone,
            'is_verified' => true,
            'is_approved' => true,
            'approved_at' => now()->subDays(30),
            'last_seen_at' => now(),
            'is_online' => true,
        ]);
    }

    private function seedDriver(string $email, string $name, string $phone, string $vehicleType): array
    {
        $user = $this->seedUser($email, $name, 'DRIVER', $phone);
        $index = (int) preg_replace('/\D+/', '', $phone);

        $driver = $this->upsert('drivers', ['user_id' => $user['id']], [
            'user_id' => $user['id'],
            'license_number' => 'LIFE-'.substr((string) $index, -6),
            'license_plate' => 'RCL '.substr((string) $index, -3).' D',
            'status' => 'approved',
            'availability_status' => 'available',
            'is_active' => true,
            'is_available' => true,
            'current_trip_id' => null,
            'is_test' => true,
            'current_latitude' => $this->locations['driver']['lat'],
            'current_longitude' => $this->locations['driver']['lng'],
            'last_location_lat' => $this->locations['driver']['lat'],
            'last_location_lng' => $this->locations['driver']['lng'],
            'last_online_at' => now(),
            'online_since' => now()->subMinutes(15),
            'last_seen_at' => now(),
            'is_online' => true,
            'total_rides' => 42,
            'rating' => 4.85,
            'rating_count' => 18,
            'balance' => 0,
            'approved_at' => now()->subDays(30),
        ]);

        $this->upsert('vehicles', ['driver_id' => $driver['id']], [
            'driver_id' => $driver['id'],
            'make' => $vehicleType === 'suv' ? 'Toyota' : 'Hyundai',
            'model' => $vehicleType === 'suv' ? 'RAV4' : 'Elantra',
            'year' => 2022,
            'color' => 'White',
            'vehicle_type' => $vehicleType,
            'seats' => $vehicleType === 'compact' ? 4 : 5,
            'air_conditioning' => true,
            'is_active' => true,
            'maintenance_status' => 'good',
            'verified_at' => now()->subDays(20),
        ]);

        $this->upsertDriverLocation((int) $user['mobile_user_id']);

        return $driver;
    }

    private function seedTrip(array $passenger, array $driver, string $status): array
    {
        $tripId = $this->stableUuid('v3-lifecycle-'.$status);
        $startedAt = in_array($status, ['IN_PROGRESS', 'COMPLETED', 'PAID', 'RATED'], true) ? now()->subMinutes(18) : null;
        $completedAt = in_array($status, ['COMPLETED', 'PAID', 'RATED'], true) ? now()->subMinutes(3) : null;
        $paidAt = in_array($status, ['PAID', 'RATED'], true) ? now()->subMinute() : null;

        return $this->upsert('trips_v3', ['id' => $tripId], [
            'id' => $tripId,
            'user_id' => $passenger['id'],
            'driver_id' => $status === 'MATCHING' ? null : $driver['id'],
            'matched_driver_id' => $driver['id'],
            'transport_type' => 'private_car',
            'status' => $status,
            'pickup_location' => $this->locations['pickup']['name'],
            'pickup_lat' => $this->locations['pickup']['lat'],
            'pickup_lng' => $this->locations['pickup']['lng'],
            'dropoff_location' => $this->locations['dropoff']['name'],
            'dropoff_lat' => $this->locations['dropoff']['lat'],
            'dropoff_lng' => $this->locations['dropoff']['lng'],
            'fare_estimate' => 6200,
            'fare_actual' => $completedAt ? 6400 : null,
            'metadata' => ['seeded_by' => static::class, 'demo_status' => $status],
            'driver_response_status' => $status === 'MATCHING' ? 'pending' : 'accepted',
            'match_attempt_count' => 1,
            'last_matched_at' => now()->subSeconds(20),
            'ignored_driver_ids' => [],
            'matching_started_at' => now()->subMinute(),
            'matched_at' => $status === 'MATCHING' ? null : now()->subSeconds(35),
            'trip_started_at' => $startedAt,
            'trip_completed_at' => $completedAt,
            'payment_method' => $paidAt ? 'mobile_money' : null,
            'payment_reference' => $paidAt ? 'LIFE-'.$status : null,
            'amount_paid' => $paidAt ? 6400 : null,
            'paid_at' => $paidAt,
            'rating' => $status === 'RATED' ? 5 : null,
            'rating_comment' => $status === 'RATED' ? 'Excellent lifecycle demo ride.' : null,
            'rated_at' => $status === 'RATED' ? now() : null,
        ]);
    }

    private function seedOffer(array $trip, array $driver, string $status): void
    {
        $offerStatus = match ($status) {
            'MATCHING' => 'pending',
            default => 'accepted',
        };

        $this->upsert('driver_trip_offers', [
            'id' => $this->stableUuid('v3-lifecycle-offer-'.$status),
        ], [
            'id' => $this->stableUuid('v3-lifecycle-offer-'.$status),
            'trip_id' => $trip['id'],
            'driver_id' => $driver['id'],
            'status' => $offerStatus,
            'expires_at' => now()->addSeconds(30),
            'responded_at' => $offerStatus === 'accepted' ? now()->subSeconds(10) : null,
            'payload' => [
                'trip_id' => $trip['id'],
                'driver_id' => $driver['id'],
                'passenger_name' => 'Lifecycle Passenger',
                'pickup_location' => $this->locations['pickup']['name'],
                'dropoff_location' => $this->locations['dropoff']['name'],
                'estimated_distance' => 5.1,
                'estimated_fare' => 6200,
                'pickup_lat' => $this->locations['pickup']['lat'],
                'pickup_lng' => $this->locations['pickup']['lng'],
                'expires_at' => now()->addSeconds(30)->toIso8601String(),
            ],
        ]);
    }

    private function seedTripEvent(array $trip, array $driver, string $eventName): void
    {
        $this->upsert('trip_events_v3', [
            'id' => $this->stableUuid('v3-lifecycle-event-'.$trip['status']),
        ], [
            'id' => $this->stableUuid('v3-lifecycle-event-'.$trip['status']),
            'trip_id' => $trip['id'],
            'event_type' => $eventName,
            'payload' => [
                'trip_id' => $trip['id'],
                'driver_id' => $driver['id'],
                'status' => $trip['status'],
                'seeded_by' => static::class,
            ],
        ]);
    }

    private function seedActiveTrip(array $trip, array $driver): void
    {
        if (! Schema::hasTable('active_trips_v3')) {
            return;
        }

        $this->upsert('active_trips_v3', ['trip_id' => $trip['id']], [
            'id' => $this->stableUuid('v3-lifecycle-active-'.$trip['status']),
            'trip_id' => $trip['id'],
            'driver_id' => $trip['status'] === 'MATCHING' ? null : $driver['id'],
            'passenger_id' => $trip['user_id'],
            'status' => $trip['status'],
        ]);
    }

    private function upsertDriverLocation(int $userId): void
    {
        if (! Schema::hasTable('driver_locations')) {
            return;
        }

        $this->upsert('driver_locations', ['driver_id' => $userId], [
            'driver_id' => $userId,
            'latitude' => $this->locations['driver']['lat'],
            'longitude' => $this->locations['driver']['lng'],
            'lat' => $this->locations['driver']['lat'],
            'lng' => $this->locations['driver']['lng'],
            'speed' => 18,
            'speed_kmh' => 18,
            'heading' => 80,
            'accuracy' => 5,
            'recorded_at' => now(),
            'last_activity_at' => now(),
            'is_online' => true,
            'updated_at' => now(),
        ]);
    }

    private function upsert(string $table, array $attributes, array $values): array
    {
        $now = now();
        $values = array_merge($attributes, $values);

        if (Schema::hasColumn($table, 'created_at')) {
            $values['created_at'] ??= $now;
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $values['updated_at'] = $now;
        }

        $attributes = $this->filterColumns($table, $attributes);
        $values = $this->filterColumns($table, $values);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = json_encode($value);
            }
        }

        DB::table($table)->updateOrInsert($attributes, $values);

        $query = DB::table($table);
        foreach ($attributes as $column => $value) {
            $query->where($column, $value);
        }

        return (array) $query->first();
    }

    private function filterColumns(string $table, array $values): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($values)->only($columns)->all();
    }

    private function stableUuid(string $value): string
    {
        $hash = md5($value);

        return substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
    }
}
