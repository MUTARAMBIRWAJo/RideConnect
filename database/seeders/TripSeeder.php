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
        $trips = [
            // Trip 1 - Completed
            [
                'passenger_id' => 5, // Alice Uwimana (PASSENGER)
                'driver_id' => 1,    // Jean Mugabo (DRIVER)
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
            ],
            // Trip 2 - Completed
            [
                'passenger_id' => 6, // Grace Mukamana (PASSENGER)
                'driver_id' => 2,    // Patrick Habimana (DRIVER)
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
            ],
            // Trip 3 - Completed
            [
                'passenger_id' => 7, // David Tuyishime (PASSENGER)
                'driver_id' => 3,    // Claude Niyonzima (DRIVER)
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
            ],
            // Trip 4 - Completed
            [
                'passenger_id' => 8, // Marie Ingabire (PASSENGER)
                'driver_id' => 1,    // Jean Mugabo (DRIVER)
                'pickup_location' => 'Kigali Convention Centre, KG 2 Roundabout',
                'dropoff_location' => 'Remera, Kigali',
                'pickup_lat' => -1.9536,
                'pickup_lng' => 30.0929,
                'dropoff_lat' => -1.9578,
                'dropoff_lng' => 30.1063,
                'fare' => 1800.00,
                'status' => 'COMPLETED',
                'requested_at' => now()->subDays(10)->setHour(17)->setMinute(30),
                'started_at' => now()->subDays(10)->setHour(17)->setMinute(40),
                'completed_at' => now()->subDays(10)->setHour(18)->setMinute(5),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(10),
            ],
            // Trip 5 - Completed
            [
                'passenger_id' => 10, // Diane Muhire (PASSENGER)
                'driver_id' => 2,     // Patrick Habimana (DRIVER)
                'pickup_location' => 'Kigali Heights, KG 7 Ave',
                'dropoff_location' => 'Nyamirambo, Kigali',
                'pickup_lat' => -1.9530,
                'pickup_lng' => 30.0920,
                'dropoff_lat' => -1.9750,
                'dropoff_lng' => 30.0400,
                'fare' => 4000.00,
                'status' => 'COMPLETED',
                'requested_at' => now()->subDays(7)->setHour(9)->setMinute(0),
                'started_at' => now()->subDays(7)->setHour(9)->setMinute(12),
                'completed_at' => now()->subDays(7)->setHour(9)->setMinute(45),
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(7),
            ],
            // Trip 6 - Started (in progress)
            [
                'passenger_id' => 5, // Alice Uwimana (PASSENGER)
                'driver_id' => 3,    // Claude Niyonzima (DRIVER)
                'pickup_location' => 'Kacyiru, Kigali',
                'dropoff_location' => 'Kibagabaga Hospital, Kigali',
                'pickup_lat' => -1.9400,
                'pickup_lng' => 30.0700,
                'dropoff_lat' => -1.9350,
                'dropoff_lng' => 30.1100,
                'fare' => 2500.00,
                'status' => 'STARTED',
                'requested_at' => now()->subHours(1),
                'started_at' => now()->subMinutes(45),
                'completed_at' => null,
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subMinutes(45),
            ],
            // Trip 7 - Accepted (driver on the way)
            [
                'passenger_id' => 6, // Grace Mukamana (PASSENGER)
                'driver_id' => 1,    // Jean Mugabo (DRIVER)
                'pickup_location' => 'Gisozi Genocide Memorial, Kigali',
                'dropoff_location' => 'Kigali City Center',
                'pickup_lat' => -1.9320,
                'pickup_lng' => 30.0500,
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0606,
                'fare' => 2800.00,
                'status' => 'ACCEPTED',
                'requested_at' => now()->subMinutes(20),
                'started_at' => null,
                'completed_at' => null,
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(15),
            ],
            // Trip 8 - Pending
            [
                'passenger_id' => 7, // David Tuyishime (PASSENGER)
                'driver_id' => null,
                'pickup_location' => 'Musanze Town, Northern Province',
                'dropoff_location' => 'Volcanoes National Park Entrance',
                'pickup_lat' => -1.4995,
                'pickup_lng' => 29.6333,
                'dropoff_lat' => -1.4530,
                'dropoff_lng' => 29.5650,
                'fare' => 8000.00,
                'status' => 'PENDING',
                'requested_at' => now()->subMinutes(5),
                'started_at' => null,
                'completed_at' => null,
                'created_at' => now()->subMinutes(5),
                'updated_at' => now()->subMinutes(5),
            ],
            // Trip 9 - Cancelled
            [
                'passenger_id' => 8, // Marie Ingabire (PASSENGER)
                'driver_id' => 2,    // Patrick Habimana (DRIVER)
                'pickup_location' => 'Rubavu Beach, Western Province',
                'dropoff_location' => 'Rubavu Town Center',
                'pickup_lat' => -1.6833,
                'pickup_lng' => 29.2667,
                'dropoff_lat' => -1.6800,
                'dropoff_lng' => 29.2700,
                'fare' => 1500.00,
                'status' => 'CANCELLED',
                'requested_at' => now()->subDays(5)->setHour(11)->setMinute(0),
                'started_at' => null,
                'completed_at' => null,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            // Trip 10 - Completed
            [
                'passenger_id' => 10, // Diane Muhire (PASSENGER)
                'driver_id' => 3,     // Claude Niyonzima (DRIVER)
                'pickup_location' => 'Kigali Serena Hotel',
                'dropoff_location' => 'Kigali International Airport',
                'pickup_lat' => -1.9560,
                'pickup_lng' => 30.0590,
                'dropoff_lat' => -1.9686,
                'dropoff_lng' => 30.1394,
                'fare' => 6000.00,
                'status' => 'COMPLETED',
                'requested_at' => now()->subDays(3)->setHour(6)->setMinute(0),
                'started_at' => now()->subDays(3)->setHour(6)->setMinute(15),
                'completed_at' => now()->subDays(3)->setHour(6)->setMinute(50),
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ],
        ];

        foreach ($trips as $trip) {
            DB::table('trips')->insert($trip);
        }
    }
}
