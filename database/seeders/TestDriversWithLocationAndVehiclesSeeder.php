<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestDriversWithLocationAndVehiclesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Central Kigali (near Kigali Heights / Kacyiru). The previous value
        // (29.7733) sat ~39km west of every real Kigali pickup, so these drivers
        // were never within matching radius.
        $baseLatitude = -1.9529;
        $baseLongitude = 30.0927;

        $driverData = [
            [
                'name' => 'Jean Claude Moto',
                'phone' => '0788123456',
                'vehicle_type' => 'motorcycle',
                'plate' => 'RAC-MOTO-001',
                'color' => 'Red',
                'lat_offset' => 0.0,
                'lng_offset' => 0.0,
            ],
            [
                'name' => 'Patrick Express',
                'phone' => '0788234567',
                'vehicle_type' => 'motorcycle',
                'plate' => 'RAC-MOTO-002',
                'color' => 'Black',
                'lat_offset' => 0.002,
                'lng_offset' => 0.002,
            ],
            [
                'name' => 'Sophie Rider',
                'phone' => '0788345678',
                'vehicle_type' => 'motorcycle',
                'plate' => 'RAC-MOTO-003',
                'color' => 'Yellow',
                'lat_offset' => -0.003,
                'lng_offset' => 0.001,
            ],
            [
                'name' => 'Michel Transporteur',
                'phone' => '0788456789',
                'vehicle_type' => 'sedan',
                'plate' => 'RAC-CAR-001',
                'color' => 'White',
                'lat_offset' => 0.001,
                'lng_offset' => -0.002,
            ],
            [
                'name' => 'Therese Voiture',
                'phone' => '0788567890',
                'vehicle_type' => 'suv',
                'plate' => 'RAC-CAR-002',
                'color' => 'Blue',
                'lat_offset' => -0.002,
                'lng_offset' => -0.001,
            ],
        ];

        foreach ($driverData as $data) {
            [$firstName, $lastName] = explode(' ', $data['name'], 2) + ['', ''];

            // Create or get mobile user
            $mobileUser = DB::table('mobile_users')->where('phone', $data['phone'])->first();
            if (!$mobileUser) {
                $mobileUserId = DB::table('mobile_users')->insertGetId([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $data['phone'],
                    'email' => $data['phone'] . '@rideconnect.local',
                    'password' => bcrypt('password123'),
                    'role' => 'DRIVER',
                    'is_verified' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $mobileUser = (object) ['id' => $mobileUserId];
            }

            // Create or get user (for auth)
            $user = DB::table('users')->where('mobile_user_id', $mobileUser->id)->first();
            if (!$user) {
                $userId = DB::table('users')->insertGetId([
                    'name' => $data['name'],
                    'email' => $data['phone'] . '@rideconnect.local',
                    'phone' => $data['phone'],
                    'password' => bcrypt('password123'),
                    'mobile_user_id' => $mobileUser->id,
                    'is_approved' => true,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $user = (object) ['id' => $userId];
            }

            // Create or update driver
            $driver = Driver::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'license_number' => 'DL-' . Str::upper(Str::random(8)),
                    'license_plate' => $data['plate'],
                    'status' => 'approved',
                    'availability_status' => 'online', // KEY: Must be online
                    'total_rides' => rand(15, 100),
                    'rating' => round(rand(40, 50) / 10, 2),
                    'rating_count' => rand(5, 50),
                    'balance' => rand(100, 500),
                    'approved_at' => now()->subDays(rand(1, 90)),
                    'current_latitude' => $baseLatitude + $data['lat_offset'],
                    'current_longitude' => $baseLongitude + $data['lng_offset'],
                ]
            );

            // Create or update vehicle
            Vehicle::updateOrCreate(
                ['driver_id' => $driver->id, 'vehicle_type' => $data['vehicle_type']],
                [
                    'driver_id' => $driver->id,
                    'vehicle_type' => $data['vehicle_type'],
                    'make' => 'Generic',
                    'model' => $data['vehicle_type'] === 'motorcycle' ? 'Moto' : 'Sedan',
                    'year' => 2023,
                    'color' => $data['color'],
                    'is_active' => true,
                    'seats' => $data['vehicle_type'] === 'motorcycle' ? 1 : 4,
                    'air_conditioning' => $data['vehicle_type'] === 'motorcycle' ? 0 : rand(0, 1),
                    'verified_at' => now()->subDays(rand(1, 30)),
                ]
            );

            // Create driver location
            DB::table('driver_locations')->updateOrInsert(
                ['driver_id' => $mobileUser->id],
                [
                    'driver_id' => $mobileUser->id,
                    'latitude' => $baseLatitude + $data['lat_offset'],
                    'longitude' => $baseLongitude + $data['lng_offset'],
                    'is_online' => true,
                    'updated_at' => now(),
                ]
            );

            echo "✅ Created/Updated Driver: {$data['name']} ({$data['vehicle_type']}) at (" .
                ($baseLatitude + $data['lat_offset']) . ", " .
                ($baseLongitude + $data['lng_offset']) . ")\n";
        }

        echo "\n✅ All test drivers created successfully!\n";
    }
}
