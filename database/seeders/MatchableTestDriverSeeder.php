<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds ONE guaranteed-matchable motorcycle driver sitting on a common Kigali
 * pickup, so motor-vehicle matching always has an eligible candidate during
 * demos/tests. Idempotent: keyed on the driver's phone/user.
 *
 * Eligibility checklist this satisfies (see MatchingService::buildEligibleDriversList):
 *   status = approved, availability_status = online, active motorcycle vehicle,
 *   current_latitude/longitude set, and a driver_locations row.
 */
class MatchableTestDriverSeeder extends Seeder
{
    // Central Kigali (near Kigali Heights / Kacyiru) — within 5km of the common
    // Kimironko / CBD pickups, and within debug-mode radius of the whole city.
    private const LAT = -1.9529;
    private const LNG = 30.0927;
    private const PHONE = '0788000111';
    private const NAME = 'Demo Moto Rider';

    public function run(): void
    {
        $mobileUser = DB::table('mobile_users')->where('phone', self::PHONE)->first();
        if (! $mobileUser) {
            $mobileUserId = DB::table('mobile_users')->insertGetId([
                'first_name' => 'Demo',
                'last_name' => 'Rider',
                'phone' => self::PHONE,
                'email' => self::PHONE . '@rideconnect.local',
                'password' => bcrypt('password123'),
                'role' => 'DRIVER',
                'is_verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $mobileUser = (object) ['id' => $mobileUserId];
        }

        $user = DB::table('users')->where('mobile_user_id', $mobileUser->id)->first();
        if (! $user) {
            $userId = DB::table('users')->insertGetId([
                'name' => self::NAME,
                'email' => self::PHONE . '@rideconnect.local',
                'phone' => self::PHONE,
                'password' => bcrypt('password123'),
                'mobile_user_id' => $mobileUser->id,
                'is_approved' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $user = (object) ['id' => $userId];
        }

        $driver = Driver::updateOrCreate(
            ['user_id' => $user->id],
            [
                'license_number' => 'DL-DEMO-' . Str::upper(Str::random(6)),
                'license_plate' => 'RAD-DEMO-01',
                'status' => 'approved',
                'availability_status' => 'online',

                'total_rides' => 50,
                'rating' => 4.8,
                'rating_count' => 25,
                'approved_at' => now()->subDays(10),
                'current_latitude' => self::LAT,
                'current_longitude' => self::LNG,
            ]
        );

        Vehicle::updateOrCreate(
            ['driver_id' => $driver->id, 'vehicle_type' => 'motorcycle'],
            [
                'make' => 'Bajaj',
                'model' => 'Boxer',
                'year' => 2023,
                'color' => 'Red',
                'is_active' => true,
                'seats' => 1,
                'air_conditioning' => 0,
                'verified_at' => now()->subDays(5),
            ]
        );

        DB::table('driver_locations')->updateOrInsert(
            ['driver_id' => $mobileUser->id],
            [
                'latitude' => self::LAT,
                'longitude' => self::LNG,
                'is_online' => true,
                'updated_at' => now(),
            ]
        );

        echo '✅ Matchable demo moto driver ready at (' . self::LAT . ', ' . self::LNG . ")\n";
    }
}
