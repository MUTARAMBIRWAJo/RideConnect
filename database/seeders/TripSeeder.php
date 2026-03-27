<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $driverIds = \App\Models\Driver::orderBy('id')->pluck('id')->toArray();
        $passengerIds = \App\Models\MobileUser::where('role', 'PASSENGER')->orderBy('id')->pluck('id')->toArray();

        $trips = [];
        // Only create trips if we have at least 3 drivers and 5 passengers
        if (count($driverIds) >= 3 && count($passengerIds) >= 5) {
            $trips[] = [
                'passenger_id' => $passengerIds[0],
                'driver_id' => $driverIds[0],
                'pickup_location' => 'Kigali City Tower, KN 2 Ave, Kigali',
                'dropoff_location' => 'Kigali International Airport, KK 15 Rd',
                'pickup_lat' => -1.9536,
                'pickup_lng' => 30.0606,
                'dropoff_lat' => -1.9686,
                'dropoff_lng' => 30.1394,
                'fare' => 5500.00,
                'status' => 'COMPLETED',
                'requested_at' => now()->subDays(20)->setHour(8)->setMinute(0),
                'started_at' => now()->subDays(20)->setHour(8)->setMinute(10),
                'completed_at' => now()->subDays(20)->setHour(8)->setMinute(45),
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(20),
            ];
            $trips[] = [
                'passenger_id' => $passengerIds[1],
                'driver_id' => $driverIds[1],
                'pickup_location' => 'Kimironko Market, Kigali',
                'dropoff_location' => 'Nyabugogo Bus Terminal, Kigali',
                'pickup_lat' => -1.9411,
                'pickup_lng' => 30.1098,
                'dropoff_lat' => -1.9456,
                'dropoff_lng' => 30.0444,
                'fare' => 3200.00,
                'status' => 'COMPLETED',
                'requested_at' => now()->subDays(18)->setHour(14)->setMinute(0),
                'started_at' => now()->subDays(18)->setHour(14)->setMinute(8),
                'completed_at' => now()->subDays(18)->setHour(14)->setMinute(35),
                'created_at' => now()->subDays(18),
                'updated_at' => now()->subDays(18),
            ];
            $trips[] = [
                'passenger_id' => $passengerIds[2],
                'driver_id' => $driverIds[2],
                'pickup_location' => 'University of Rwanda, Huye Campus',
                'dropoff_location' => 'Huye Town Center',
                'pickup_lat' => -2.6133,
                'pickup_lng' => 29.7417,
                'dropoff_lat' => -2.5969,
                'dropoff_lng' => 29.5944,
                'fare' => 2000.00,
                'status' => 'COMPLETED',
                'requested_at' => now()->subDays(15)->setHour(10)->setMinute(0),
                'started_at' => now()->subDays(15)->setHour(10)->setMinute(5),
                'completed_at' => now()->subDays(15)->setHour(10)->setMinute(20),
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(15),
            ];
            // ...add more trips as needed, following the same pattern and using available IDs...
        }

        foreach ($trips as $trip) {
            DB::table('trips')->insert($trip);
        }
    }
}
