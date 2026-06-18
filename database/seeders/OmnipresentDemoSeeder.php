<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OmnipresentDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates a massive fleet of drivers across Kigali for every vehicle type
     * to guarantee matches everywhere for demo purposes.
     */
    public function run(): void
    {
        $vehicleTypes = [
            'motorcycle' => ['make' => 'TVS', 'model' => 'Victor', 'seats' => 1],
            'sedan' => ['make' => 'Toyota', 'model' => 'Corolla', 'seats' => 4],
            'bus' => ['make' => 'Yutong', 'model' => 'CityBus', 'seats' => 40],
        ];

        // Kigali Bounding Box
        $minLat = -2.0000;
        $maxLat = -1.9000;
        $minLng = 30.0000;
        $maxLng = 30.1500;

        $totalDriversToGenerate = 30; // 30 of each type = 90 total drivers
        $password = Hash::make('password123');

        foreach ($vehicleTypes as $type => $specs) {
            for ($i = 0; $i < $totalDriversToGenerate; $i++) {
                $lat = $minLat + mt_rand() / mt_getrandmax() * ($maxLat - $minLat);
                $lng = $minLng + mt_rand() / mt_getrandmax() * ($maxLng - $minLng);

                $phone = '0788' . str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                $email = "demo_{$type}_{$i}@rideconnect.local";

                // 1. Create Mobile User
                $mobileUserId = DB::table('mobile_users')->insertGetId([
                    'first_name' => 'Demo',
                    'last_name' => ucfirst($type) . " " . $i,
                    'phone' => $phone,
                    'email' => $email,
                    'password' => $password,
                    'role' => 'DRIVER',
                    'is_verified' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 2. Create Web User (Admin Panel accessible)
                $userId = DB::table('users')->insertGetId([
                    'name' => "Demo " . ucfirst($type) . " $i",
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $password,
                    'mobile_user_id' => $mobileUserId,
                    'is_approved' => true,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3. Create Driver Profile
                $driverId = DB::table('drivers')->insertGetId([
                    'user_id' => $userId,
                    'license_number' => 'DL-' . strtoupper(Str::random(8)),
                    'license_plate' => 'RA' . chr(rand(65, 90)) . '-' . rand(100, 999) . chr(rand(65, 90)),
                    'status' => 'approved',
                    'availability_status' => 'online',
                    'is_online' => true,
                    'is_available' => true,
                    'total_rides' => rand(10, 500),
                    'rating' => rand(40, 50) / 10,
                    'rating_count' => rand(5, 100),
                    'approved_at' => now()->subDays(rand(1, 60)),
                    'current_latitude' => $lat,
                    'current_longitude' => $lng,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 4. Create Vehicle
                DB::table('vehicles')->insert([
                    'driver_id' => $driverId,
                    'vehicle_type' => $type,
                    'make' => $specs['make'],
                    'model' => $specs['model'],
                    'year' => rand(2015, 2023),
                    'color' => ['White', 'Black', 'Silver', 'Red', 'Blue'][rand(0, 4)],
                    'is_active' => true,
                    'seats' => $specs['seats'],
                    'air_conditioning' => ($type === 'motorcycle') ? false : true,
                    'verified_at' => now()->subDays(rand(1, 30)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 5. Update driver_locations tracking table
                DB::table('driver_locations')->updateOrInsert(
                    ['driver_id' => $driverId],
                    [
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'is_online' => true,
                        'updated_at' => now(),
                    ]
                );
            }
            
            $this->command->info("Seeded $totalDriversToGenerate omnipresent drivers for $type");
        }

        $this->command->info("✅ Omnipresent Demo Data Seeded! You now have drivers everywhere in Kigali.");
    }
}
