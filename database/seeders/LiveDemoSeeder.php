<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TransportCorridor;
use App\Models\CorridorStop;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LiveDemoSeeder extends Seeder
{
    private array $passengers = [];
    private array $drivers = [];
    private array $vehicles = [];
    private array $corridors = [];
    
    // Bounds for Kigali
    private float $minLat = -1.9700;
    private float $maxLat = -1.9300;
    private float $minLng = 30.0400;
    private float $maxLng = 30.1200;

    public function run(): void
    {
        $this->command->info('Starting Live Demo Seeder...');

        $this->seedPassengers(50);
        $this->seedDriversAndVehicles();
        $this->seedCorridors(50);
        
        $this->seedTrips();
        $this->seedBookings();
        $this->seedPublicTransportBookings();
        
        $this->seedMatchRecords(100);
        $this->seedReviews(150);
        $this->seedPayments(200);
        $this->seedNotifications(300);

        $this->command->info('Live Demo Seeder completed successfully!');
    }

    private function randomDate(): Carbon
    {
        // 70% chance of being in the last 14 days for dashboard density
        // 30% chance of being in the 3 months prior
        if (rand(1, 100) <= 70) {
            return now()->subDays(rand(0, 14))->subMinutes(rand(0, 1440));
        }
        return now()->subDays(rand(15, 90))->subMinutes(rand(0, 1440));
    }

    private function randomCoordinate(string $type): float
    {
        if ($type === 'lat') {
            return $this->minLat + (lcg_value() * ($this->maxLat - $this->minLat));
        }
        return $this->minLng + (lcg_value() * ($this->maxLng - $this->minLng));
    }

    private function seedPassengers(int $count): void
    {
        $this->command->info("Seeding $count Passengers...");
        $personas = ['Student', 'Commuter', 'Tourist', 'Business'];

        for ($i = 1; $i <= $count; $i++) {
            $email = "demo_passenger_{$i}@rideconnect.com";
            $firstName = "DemoPass{$i}";
            $lastName = $personas[array_rand($personas)];

            $mobileUser = MobileUser::firstOrCreate(
                ['email' => $email, 'phone' => '+250788100' . str_pad((string)$i, 3, '0', STR_PAD_LEFT)],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => Hash::make('password'),
                    'role' => 'PASSENGER',
                    'is_verified' => true,
                ]
            );

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => "$firstName $lastName",
                    'password' => Hash::make('password'),
                    'role' => 'PASSENGER',
                    'mobile_user_id' => $mobileUser->id,
                    'is_approved' => true,
                    'is_verified' => true,
                    'created_at' => $this->randomDate(),
                ]
            );

            $this->passengers[] = $user;
        }
    }

    private function seedDriversAndVehicles(): void
    {
        $this->command->info('Seeding 25 Drivers and Vehicles...');
        $types = [
            'sedan' => 10,
            'motorcycle' => 5,
            'BUS' => 10,
        ];
        
        $driverIndex = 1;
        foreach ($types as $type => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $email = "demo_driver_{$type}_{$i}@rideconnect.com";
                
                $mobileUser = MobileUser::firstOrCreate(
                    ['email' => $email, 'phone' => '+250788200' . str_pad((string)$driverIndex, 3, '0', STR_PAD_LEFT)],
                    [
                        'first_name' => "DemoDriver",
                        'last_name' => ucfirst($type) . $i,
                        'password' => Hash::make('password'),
                        'role' => 'DRIVER',
                        'is_verified' => true,
                    ]
                );

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => "DemoDriver " . ucfirst($type) . $i,
                        'password' => Hash::make('password'),
                        'role' => 'DRIVER',
                        'mobile_user_id' => $mobileUser->id,
                        'is_approved' => true,
                        'is_verified' => true,
                        'created_at' => $this->randomDate(),
                    ]
                );

                $driver = Driver::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'license_number' => "DL-DEMO-" . str_pad((string)$driverIndex, 4, '0', STR_PAD_LEFT),
                        'license_plate' => "RAB " . str_pad((string)$driverIndex, 3, '0', STR_PAD_LEFT) . strtoupper(substr($type, 0, 1)),
                        'status' => 'approved',
                        'availability_status' => 'online',
                        'rating' => rand(40, 50) / 10,
                    ]
                );

                $seats = $type === 'motorcycle' ? 1 : ($type === 'sedan' ? 4 : 30);
                
                $vehicle = Vehicle::updateOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'vehicle_type' => $type,
                        'seats' => $seats,
                        'is_active' => true,
                        'color' => 'White',
                        'make' => 'Demo Make',
                        'model' => 'Demo Model',
                        'year' => 2022
                    ]
                );

                $this->drivers[] = $driver;
                $this->vehicles[] = $vehicle;
                $driverIndex++;
            }
        }
    }

    private function seedCorridors(int $count): void
    {
        $this->command->info("Seeding $count Corridors...");
        $locations = ['Nyabugogo', 'Kimironko', 'Remera', 'Kacyiru', 'Kicukiro', 'Nyamirambo', 'Kigali CBD', 'Gikondo', 'Kabuga', 'Kanombe'];

        for ($i = 0; $i < $count; $i++) {
            $origin = $locations[array_rand($locations)];
            $dest = $locations[array_rand($locations)];
            while ($origin === $dest) {
                $dest = $locations[array_rand($locations)];
            }
            
            $name = "Demo: $origin to $dest";
            
            $corridor = TransportCorridor::firstOrCreate(
                ['name' => $name],
                [
                    'description' => "Demo public transport route",
                    'is_active' => true,
                    'base_fare' => rand(300, 1000)
                ]
            );

            if ($corridor->stops()->count() === 0) {
                CorridorStop::create([
                    'transport_corridor_id' => $corridor->id,
                    'name' => $origin . ' Main Stop',
                    'latitude' => $this->randomCoordinate('lat'),
                    'longitude' => $this->randomCoordinate('lng'),
                    'sequence_order' => 1
                ]);
                CorridorStop::create([
                    'transport_corridor_id' => $corridor->id,
                    'name' => $dest . ' Main Stop',
                    'latitude' => $this->randomCoordinate('lat'),
                    'longitude' => $this->randomCoordinate('lng'),
                    'sequence_order' => 2
                ]);
            }
            
            $this->corridors[] = $corridor;
        }
    }

    private function seedTrips(): void
    {
        $this->command->info('Seeding Trips (150 Completed, 20 Active, 40 Scheduled, 15 Cancelled)...');
        
        $this->createTrips('COMPLETED', 150);
        $this->createTrips('STARTED', 20, true); 
        $this->createTrips('PENDING', 40, false, true); 
        $this->createTrips('CANCELLED', 15);
    }

    private function createTrips(string $status, int $count, bool $isActive = false, bool $isScheduled = false): void
    {
        for ($i = 0; $i < $count; $i++) {
            $passenger = $this->passengers[array_rand($this->passengers)];
            $driver = $this->drivers[array_rand($this->drivers)];
            
            $date = $isActive ? now() : ($isScheduled ? now()->addDays(rand(1, 7)) : $this->randomDate());
            
            $trip = new Trip([
                'passenger_id' => $passenger->id,
                'driver_id' => $driver->id,
                'pickup_location' => 'Demo Pickup ' . Str::random(4),
                'dropoff_location' => 'Demo Dropoff ' . Str::random(4),
                'pickup_lat' => $this->randomCoordinate('lat'),
                'pickup_lng' => $this->randomCoordinate('lng'),
                'dropoff_lat' => $this->randomCoordinate('lat'),
                'dropoff_lng' => $this->randomCoordinate('lng'),
                'fare' => rand(1500, 10000),
                'status' => $status,
                'transport_type' => 'CAR',
                'requested_at' => $date,
                'idempotency_key' => 'demo_trip_' . $status . '_' . uniqid(),
            ]);

            if ($status === 'COMPLETED') {
                $trip->started_at = $date->copy()->addMinutes(5);
                $trip->completed_at = $date->copy()->addMinutes(rand(15, 60));
                $trip->actual_fare = $trip->fare;
                $trip->actual_distance = rand(2, 20);
            } elseif ($status === 'STARTED') {
                $trip->started_at = $date->copy()->subMinutes(rand(1, 15));
            } elseif ($status === 'CANCELLED') {
                $trip->rejection_reason = 'Passenger requested cancellation';
            }

            $trip->save();
        }
    }

    private function seedBookings(): void
    {
        $this->command->info('Seeding 120 regular Bookings...');
        
        for ($i = 0; $i < 120; $i++) {
            $passenger = $this->passengers[array_rand($this->passengers)];
            $date = $this->randomDate();
            $statuses = ['pending', 'accepted', 'completed', 'cancelled'];
            
            Booking::create([
                'user_id' => $passenger->id,
                'pickup_location' => 'Booking Pickup',
                'dropoff_location' => 'Booking Dropoff',
                'pickup_lat' => $this->randomCoordinate('lat'),
                'pickup_lng' => $this->randomCoordinate('lng'),
                'dropoff_lat' => $this->randomCoordinate('lat'),
                'dropoff_lng' => $this->randomCoordinate('lng'),
                'schedule_time' => $date,
                'status' => $statuses[array_rand($statuses)],
                'fare_estimate' => rand(2000, 8000),
                'transport_type' => 'CAR'
            ]);
        }
    }

    private function seedPublicTransportBookings(): void
    {
        $this->command->info('Seeding 80 Public Transport Bookings...');
        
        for ($i = 0; $i < 80; $i++) {
            $passenger = $this->passengers[array_rand($this->passengers)];
            $corridor = $this->corridors[array_rand($this->corridors)];
            $stops = $corridor->stops;
            
            if ($stops->count() < 2) continue;
            
            $date = $this->randomDate();
            
            Booking::create([
                'user_id' => $passenger->id,
                'pickup_location' => $stops->first()->name,
                'dropoff_location' => $stops->last()->name,
                'pickup_lat' => $stops->first()->latitude,
                'pickup_lng' => $stops->first()->longitude,
                'dropoff_lat' => $stops->last()->latitude,
                'dropoff_lng' => $stops->last()->longitude,
                'schedule_time' => $date,
                'status' => 'completed',
                'fare_estimate' => $corridor->base_fare,
                'transport_type' => 'PUBLIC_BUS',
                'seats_booked' => rand(1, 3)
            ]);
        }
    }

    private function seedMatchRecords(int $count): void
    {
        $this->command->info("Seeding $count Match Records...");
        $trips = Trip::where('status', 'COMPLETED')->take($count)->get();
        
        foreach ($trips as $trip) {
            TripAssignmentAttempt::create([
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
                'status' => 'accepted',
                'score' => rand(80, 100) / 100,
                'score_breakdown' => [
                    'distance' => rand(70, 100) / 100,
                    'route' => rand(70, 100) / 100,
                    'preference' => rand(70, 100) / 100,
                ],
                'responded_at' => $trip->requested_at ? $trip->requested_at->addMinutes(1) : now(),
                'expires_at' => $trip->requested_at ? $trip->requested_at->addMinutes(5) : now()->addMinutes(5),
            ]);
        }
    }

    private function seedReviews(int $count): void
    {
        $this->command->info("Seeding $count Reviews...");
        $trips = Trip::where('status', 'COMPLETED')->take($count)->get();
        
        $comments = ['Great ride', 'Very professional', 'On time', 'Clean car', 'Smooth driving'];
        
        foreach ($trips as $trip) {
            Review::create([
                'trip_id' => $trip->id,
                'user_id' => $trip->passenger_id,
                'driver_id' => $trip->driver_id,
                'rating' => rand(3, 5),
                'comment' => $comments[array_rand($comments)],
            ]);
        }
    }

    private function seedPayments(int $count): void
    {
        $this->command->info("Seeding $count Payments...");
        $trips = Trip::where('status', 'COMPLETED')->inRandomOrder()->take($count)->get();
        $methods = ['mobile_money', 'card', 'cash'];
        $statuses = ['successful', 'pending', 'failed'];
        
        foreach ($trips as $trip) {
            Payment::create([
                'trip_id' => $trip->id,
                'user_id' => $trip->passenger_id,
                'amount' => $trip->fare,
                'currency' => 'RWF',
                'payment_method' => $methods[array_rand($methods)],
                'status' => $statuses[array_rand($statuses)],
                'transaction_id' => 'demo_txn_' . uniqid(),
            ]);
        }
    }

    private function seedNotifications(int $count): void
    {
        $this->command->info("Seeding $count Notifications...");
        $users = User::inRandomOrder()->take(50)->get();
        $types = ['ride_request_accepted', 'driver_arriving', 'trip_started', 'trip_completed'];
        
        for ($i = 0; $i < $count; $i++) {
            $user = $users[array_rand($users->toArray())];
            Notification::create([
                'user_id' => $user->id,
                'type' => $types[array_rand($types)],
                'title' => 'Demo Notification',
                'message' => 'This is a generated notification for demo purposes.',
                'is_read' => rand(0, 1) == 1,
            ]);
        }
    }
}
