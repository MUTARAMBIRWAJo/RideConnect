<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MatchingTestDataSeeder extends Seeder
{
    private const PASSWORD = 'Test@12345';

    private const LOCATIONS = [
        'Kimironko Market' => ['lat' => -1.94982170, 'lng' => 30.12618020, 'radius_km' => 5],
        'Remera Bus Park' => ['lat' => -1.95670000, 'lng' => 30.10560000, 'radius_km' => 5],
        'Kigali Convention Centre' => ['lat' => -1.95450000, 'lng' => 30.09330000, 'radius_km' => 5],
        'Kigali International Airport' => ['lat' => -1.96860000, 'lng' => 30.13950000, 'radius_km' => 8],
        'Nyabugogo Bus Park' => ['lat' => -1.93990000, 'lng' => 30.04460000, 'radius_km' => 5],
        'Downtown Bus Park' => ['lat' => -1.95360000, 'lng' => 30.06000000, 'radius_km' => 5],
        'Kacyiru Police Headquarters' => ['lat' => -1.93930000, 'lng' => 30.07580000, 'radius_km' => 5],
        'Kigali Heights' => ['lat' => -1.95390000, 'lng' => 30.09270000, 'radius_km' => 5],
    ];

    /**
     * Seed stable passenger, driver, vehicle, location, corridor, and bus-position data
     * for end-to-end matching tests.
     */
    public function run(): void
    {
        $passwordHash = Hash::make(self::PASSWORD);

        $this->seedSavedLocations();

        $passengers = [
            [
                'first_name' => 'Aline',
                'last_name' => 'Uwase',
                'email' => 'test.passenger.aline@rideconnect.local',
                'phone' => '+250788100001',
                'home' => 'Kimironko Market',
            ],
            [
                'first_name' => 'Eric',
                'last_name' => 'Mugisha',
                'email' => 'test.passenger.eric@rideconnect.local',
                'phone' => '+250788100002',
                'home' => 'Kigali Convention Centre',
            ],
            [
                'first_name' => 'Claudine',
                'last_name' => 'Ishimwe',
                'email' => 'test.passenger.claudine@rideconnect.local',
                'phone' => '+250788100003',
                'home' => 'Nyabugogo Bus Park',
            ],
        ];

        foreach ($passengers as $passenger) {
            $this->upsertMobileBackedUser($passenger, 'PASSENGER', $passwordHash);
        }

        $drivers = [
            [
                'first_name' => 'Jean',
                'last_name' => 'Nshimiyimana',
                'email' => 'test.driver.moto.jean@rideconnect.local',
                'phone' => '+250788200001',
                'vehicle_type' => 'motorcycle',
                'vehicle' => ['make' => 'TVS', 'model' => 'Apache RTR', 'year' => 2023, 'color' => 'Red', 'seats' => 1],
                'license_number' => 'MATCH-MOTO-001',
                'license_plate' => 'RCM-101M',
                'location' => ['lat' => -1.94720000, 'lng' => 30.06310000],
                'rating' => 4.90,
                'total_rides' => 182,
            ],
            [
                'first_name' => 'Patrick',
                'last_name' => 'Habyarimana',
                'email' => 'test.driver.moto.patrick@rideconnect.local',
                'phone' => '+250788200002',
                'vehicle_type' => 'motorcycle',
                'vehicle' => ['make' => 'Bajaj', 'model' => 'Boxer', 'year' => 2022, 'color' => 'Black', 'seats' => 1],
                'license_number' => 'MATCH-MOTO-002',
                'license_plate' => 'RCM-102M',
                'location' => ['lat' => -1.95480000, 'lng' => 30.09220000],
                'rating' => 4.70,
                'total_rides' => 141,
            ],
            [
                'first_name' => 'Sandrine',
                'last_name' => 'Mukamana',
                'email' => 'test.driver.car.sandrine@rideconnect.local',
                'phone' => '+250788200003',
                'vehicle_type' => 'sedan',
                'vehicle' => ['make' => 'Toyota', 'model' => 'Corolla', 'year' => 2021, 'color' => 'White', 'seats' => 4],
                'license_number' => 'MATCH-CAR-001',
                'license_plate' => 'RCC-201C',
                'location' => ['lat' => -1.95280000, 'lng' => 30.08990000],
                'rating' => 4.80,
                'total_rides' => 208,
            ],
            [
                'first_name' => 'Claude',
                'last_name' => 'Mugenzi',
                'email' => 'test.driver.car.claude@rideconnect.local',
                'phone' => '+250788200004',
                'vehicle_type' => 'suv',
                'vehicle' => ['make' => 'Hyundai', 'model' => 'Tucson', 'year' => 2022, 'color' => 'Blue', 'seats' => 5],
                'license_number' => 'MATCH-CAR-002',
                'license_plate' => 'RCC-202C',
                'location' => ['lat' => -1.94040000, 'lng' => 30.07490000],
                'rating' => 4.60,
                'total_rides' => 97,
            ],
            [
                'first_name' => 'Emmanuel',
                'last_name' => 'Bus',
                'email' => 'test.driver.bus.emmanuel@rideconnect.local',
                'phone' => '+250788200005',
                'vehicle_type' => 'van',
                'vehicle' => ['make' => 'Toyota', 'model' => 'Coaster', 'year' => 2020, 'color' => 'Green', 'seats' => 29],
                'license_number' => 'MATCH-BUS-001',
                'license_plate' => 'RCB-301B',
                'location' => ['lat' => -1.94930000, 'lng' => 30.06280000],
                'rating' => 4.75,
                'total_rides' => 331,
            ],
            [
                'first_name' => 'Vestine',
                'last_name' => 'Transit',
                'email' => 'test.driver.bus.vestine@rideconnect.local',
                'phone' => '+250788200006',
                'vehicle_type' => 'van',
                'vehicle' => ['make' => 'Nissan', 'model' => 'Civilian', 'year' => 2019, 'color' => 'Yellow', 'seats' => 25],
                'license_number' => 'MATCH-BUS-002',
                'license_plate' => 'RCB-302B',
                'location' => ['lat' => -1.95710000, 'lng' => 30.10410000],
                'rating' => 4.65,
                'total_rides' => 289,
            ],
        ];

        $driverRecords = [];
        foreach ($drivers as $driverData) {
            $driverRecords[$driverData['email']] = $this->seedDriver($driverData, $passwordHash);
        }

        $corridor = $this->seedPublicBusCorridor();
        $this->seedBusAssignment($corridor, $driverRecords['test.driver.bus.emmanuel@rideconnect.local'], 'Kimironko Market', 6);
        $this->seedBusAssignment($corridor, $driverRecords['test.driver.bus.vestine@rideconnect.local'], 'Remera Bus Park', 10);

        $this->command?->info('Matching test data seeded.');
        $this->command?->info('Password for every seeded passenger and driver: '.self::PASSWORD);
        $this->command?->info('Reference: docs/MATCHING_TEST_DATA.md');
    }

    private function seedSavedLocations(): void
    {
        if (! Schema::hasTable('saved_locations')) {
            return;
        }

        foreach (self::LOCATIONS as $name => $coords) {
            $this->upsert('saved_locations', ['name' => $name], [
                'name' => $name,
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
            ]);
        }
    }

    private function seedDriver(array $data, string $passwordHash): array
    {
        $user = $this->upsertMobileBackedUser($data, 'DRIVER', $passwordHash);
        $location = $data['location'];

        $driver = $this->upsert('drivers', ['user_id' => $user['id']], [
            'user_id' => $user['id'],
            'license_number' => $data['license_number'],
            'license_plate' => $data['license_plate'],
            'status' => 'approved',
            'availability_status' => 'online',

            'current_trip_id' => null,
            'current_latitude' => $location['lat'],
            'current_longitude' => $location['lng'],
            'last_location_lat' => $location['lat'],
            'last_location_lng' => $location['lng'],
            'last_online_at' => now(),
            'online_since' => now()->subMinutes(12),
            'total_rides' => $data['total_rides'],
            'rating' => $data['rating'],
            'rating_count' => max(10, (int) floor($data['total_rides'] / 3)),
            'balance' => 0,
            'approved_at' => now()->subDays(45),
            'is_test' => true,
        ]);

        $vehicle = $this->upsert('vehicles', [
            'driver_id' => $driver['id'],
            'vehicle_type' => $data['vehicle_type'],
        ], [
            'driver_id' => $driver['id'],
            'make' => $data['vehicle']['make'],
            'model' => $data['vehicle']['model'],
            'year' => $data['vehicle']['year'],
            'color' => $data['vehicle']['color'],
            'vehicle_type' => $data['vehicle_type'],
            'seats' => $data['vehicle']['seats'],
            'air_conditioning' => $data['vehicle_type'] !== 'motorcycle',
            'is_active' => true,
            'maintenance_status' => 'operational',
            'verified_at' => now()->subDays(30),
        ]);

        $this->upsertDriverLocation((int) $user['mobile_user_id'], $location['lat'], $location['lng']);
        $this->upsertDriverLocationV3((int) $driver['id'], $location['lat'], $location['lng']);

        return [
            'user' => $user,
            'driver' => $driver,
            'vehicle' => $vehicle,
            'location' => $location,
        ];
    }

    private function seedPublicBusCorridor(): array
    {
        $corridor = $this->upsert('transport_corridors', ['corridor_code' => 'MATCH-105'], [
            'corridor_code' => 'MATCH-105',
            'corridor_name' => 'Kimironko Market -> Nyabugogo Bus Park (MATCH-105)',
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 28,
        ]);

        $stops = [
            ['name' => 'Kimironko Market', 'order' => 1, 'major' => true],
            ['name' => 'Remera Bus Park', 'order' => 2, 'major' => false],
            ['name' => 'Kigali Convention Centre', 'order' => 3, 'major' => false],
            ['name' => 'Downtown Bus Park', 'order' => 4, 'major' => false],
            ['name' => 'Nyabugogo Bus Park', 'order' => 5, 'major' => true],
        ];

        $firstStopId = null;
        $lastStopId = null;

        foreach ($stops as $stop) {
            $coords = self::LOCATIONS[$stop['name']];
            $row = $this->upsert('corridor_stops', [
                'corridor_id' => $corridor['id'],
                'stop_order' => $stop['order'],
            ], [
                'corridor_id' => $corridor['id'],
                'stop_name' => $stop['name'],
                'stop_order' => $stop['order'],
                'latitude' => $coords['lat'],
                'longitude' => $coords['lng'],
                'is_major_terminal' => $stop['major'],
                'status' => 'active',
            ]);

            $firstStopId ??= $row['id'];
            $lastStopId = $row['id'];
        }

        $corridor = $this->upsert('transport_corridors', ['corridor_code' => 'MATCH-105'], [
            'corridor_code' => 'MATCH-105',
            'corridor_name' => 'Kimironko Market -> Nyabugogo Bus Park (MATCH-105)',
            'start_stop_id' => $firstStopId,
            'end_stop_id' => $lastStopId,
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 28,
        ]);

        return $corridor;
    }

    private function seedBusAssignment(array $corridor, array $driverRecord, string $positionName, int $etaMinutes): void
    {
        $assignment = $this->upsert('bus_route_assignments', [
            'bus_id' => $driverRecord['vehicle']['id'],
            'corridor_id' => $corridor['id'],
        ], [
            'bus_id' => $driverRecord['vehicle']['id'],
            'corridor_id' => $corridor['id'],
            'driver_id' => $driverRecord['driver']['id'],
            'active_trip_id' => null,
            'status' => 'active',
            'started_at' => now()->subMinutes(20),
            'ended_at' => null,
        ]);

        $coords = self::LOCATIONS[$positionName];
        DB::table('bus_position_updates')->insert($this->filterColumns('bus_position_updates', [
            'bus_route_assignment_id' => $assignment['id'],
            'trip_id' => null,
            'latitude' => $coords['lat'],
            'longitude' => $coords['lng'],
            'speed_kph' => 28,
            'heading_degrees' => 270,
            'next_stop_id' => $this->corridorStopId((int) $corridor['id'], $positionName),
            'eta_minutes' => $etaMinutes,
            'route_progress_percent' => $positionName === 'Kimironko Market' ? 8 : 22,
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function upsertMobileBackedUser(array $data, string $role, string $passwordHash): array
    {
        $mobileUser = $this->upsert('mobile_users', ['email' => $data['email']], [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $passwordHash,
            'role' => $role,
            'is_verified' => true,
        ]);

        return $this->upsert('users', ['email' => $data['email']], [
            'name' => $data['first_name'].' '.$data['last_name'],
            'email' => $data['email'],
            'email_verified_at' => now(),
            'password' => $passwordHash,
            'role' => $role,
            'mobile_user_id' => $mobileUser['id'],
            'manager_id' => null,
            'phone' => $data['phone'],
            'profile_photo' => null,
            'is_verified' => true,
            'is_approved' => true,
            'approved_at' => now()->subDays(60),
        ]);
    }

    private function upsertDriverLocation(int $mobileUserId, float $lat, float $lng): void
    {
        if (! Schema::hasTable('driver_locations')) {
            return;
        }

        $values = [
            'driver_id' => $mobileUserId,
            'latitude' => $lat,
            'longitude' => $lng,
            'lat' => $lat,
            'lng' => $lng,
            'speed_kmh' => 22,
            'speed' => 22,
            'heading' => 90,
            'accuracy' => 6,
            'last_activity_at' => now(),
            'is_online' => true,
            'updated_at' => now(),
            'recorded_at' => now(),
            'created_at' => now(),
        ];

        DB::table('driver_locations')->updateOrInsert(
            ['driver_id' => $mobileUserId],
            $this->filterColumns('driver_locations', $values)
        );
    }

    private function upsertDriverLocationV3(int $driverId, float $lat, float $lng): void
    {
        if (! Schema::hasTable('driver_locations_v3')) {
            return;
        }

        $values = [
            'id' => (string) Str::uuid(),
            'driver_id' => $driverId,
            'latitude' => $lat,
            'longitude' => $lng,
            'is_online' => true,
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // We can't use updateOrInsert with a generated UUID as primary key easily if it doesn't exist,
        // so we'll check if a record exists for this driver.
        $existing = DB::table('driver_locations_v3')->where('driver_id', $driverId)->first();
        if ($existing) {
            DB::table('driver_locations_v3')->where('driver_id', $driverId)->update([
                'latitude' => $lat,
                'longitude' => $lng,
                'is_online' => true,
                'is_available' => true,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('driver_locations_v3')->insert($values);
        }
    }

    private function corridorStopId(int $corridorId, string $stopName): ?int
    {
        if (! Schema::hasTable('corridor_stops')) {
            return null;
        }

        $id = DB::table('corridor_stops')
            ->where('corridor_id', $corridorId)
            ->where('stop_name', $stopName)
            ->value('id');

        return $id ? (int) $id : null;
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

        DB::table($table)->updateOrInsert(
            $this->filterColumns($table, $attributes),
            $this->filterColumns($table, $values)
        );

        $query = DB::table($table);
        foreach ($this->filterColumns($table, $attributes) as $column => $value) {
            $query->where($column, $value);
        }

        return (array) $query->first();
    }

    private function filterColumns(string $table, array $values): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($values)
            ->only($columns)
            ->all();
    }
}
