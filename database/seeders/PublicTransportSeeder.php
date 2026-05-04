<?php

namespace Database\Seeders;

use App\Models\Corridor;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PublicTransportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get default zones for corridors
        $defaultZone = Zone::firstOrCreate(
            ['name' => 'Kigali'],
            ['code' => 'KGL']
        );

        // Corridors and routes extracted from the provided images
        $corridors = [
            'A' => [
                ['code' => '102', 'name' => 'KABUGA - MURINDI - NYABUGOGO (via SONATUBE)'],
                ['code' => '118', 'name' => 'KABUGA - KIBAYA - NYABUGOGO (via KACYIRU)'],
                ['code' => '104', 'name' => 'KABUGA - KIBAYA - DOWNTOWN (via SONATUBE)'],
                ['code' => '124', 'name' => 'KABUGA - MURINDI - SONATUBE - DOWNTOWN (via MU KIYOVU)'],
                ['code' => '125', 'name' => 'KIMIRONKO - REMERA - KANOMBE - BUSANZA'],
                ['code' => '106', 'name' => 'REMERA - KIMIRONKO - AZAM - SEZ - NDERA'],
                ['code' => '113', 'name' => 'REMERA - BUSANZA (via RUBILIZI)'],
            ],
            'B' => [
                ['code' => '217', 'name' => 'DOWNTOWN - MUYANGE (via SAINT JOSEPH)'],
                ['code' => '203', 'name' => 'NYANZA YA KICUKIRO - GATENGA - DOWNTOWN'],
                ['code' => '213', 'name' => 'NYANZA YA KICUKIRO - KIMIRONKO (via REMERA)'],
                ['code' => '214', 'name' => 'NYABUGOGO - GATENGA - NYANZA YA KICUKIRO'],
                ['code' => '211', 'name' => 'NYANZA YA KICUKIRO - KACYIRU'],
                ['code' => '212', 'name' => 'NYABUGOGO - KADILAKE - MU MYEMBE - UNILAK - SAHARA - SAINT JOSEPH'],
                ['code' => '205', 'name' => 'DOWNTOWN - BWERANKORI - MU MIDUHA'],
                ['code' => '206', 'name' => 'NYABUGOGO - BWERANKORI - MU MIDUHA'],
                ['code' => '201', 'name' => 'DOWNTOWN - SAINT JOSEPH (via KICUKIRO CENTRE)'],
                ['code' => '210', 'name' => 'NYANZA YA KICUKIRO - BWERANKOLI - NYAMIRAMBO ERP'],
                ['code' => '215', 'name' => 'KIMIRONKO - BWERANKORI - MU MIDUHA (via REMERA)'],
            ],
            'C' => [
                ['code' => '301', 'name' => 'KINYINYA - NYARUTARAMA - DOWNTOWN (via KACYIRU)'],
                ['code' => '302', 'name' => 'KIMIRONKO - DOWNTOWN - CBD (via CITY HALL)'],
                ['code' => '309', 'name' => 'KINYINYA - KIMIRONKO'],
                ['code' => '316', 'name' => 'KIMIRONKO - ZINDIRO - MUSAVE'],
                ['code' => '318', 'name' => 'NYACYONGA - BATSINDA - KIMIRONKO'],
                ['code' => '325', 'name' => 'KIMIRONKO - MASAKA - KABUGA (via KPS/REMERA)'],
            ],
            'D' => [
                ['code' => '303', 'name' => 'GASANZE - BATSINDA - DOWNTOWN (via GAKINJIRO)'],
                ['code' => '305', 'name' => 'KIMIRONKO - NYABUGOGO (via KACYIRU)'],
                ['code' => '310', 'name' => 'NYABUGOGO - BATSINDA - GASANZE (via GAKINJIRO)'],
                ['code' => '319', 'name' => 'DOWNTOWN - ULK - KIGARAMA - KARURUMA'],
                ['code' => '313', 'name' => 'DOWNTOWN - BATSINDA (via ULK)'],
                ['code' => '311', 'name' => 'NYABUGOGO - BATSINDA (via ULK)'],
                ['code' => '312', 'name' => 'DOWNTOWN - NYARUTARAMA - NYAGATOVU - KIMIRONKO'],
                ['code' => '314', 'name' => 'KIMIRONKO - NYABUGOGO (via KIBAGABAGA)'],
                ['code' => '315', 'name' => 'KINYINYA - NYABUGOGO (via UTEXRWA)'],
                ['code' => '317', 'name' => 'BIREMBO - KINYINYA - DOWNTOWN (via UTEXRWA)'],
            ],
            'E' => [
                ['code' => '401', 'name' => 'KITABI - KU RYANYUMA - CHUK - DOWNTOWN (via QUARTIER COMMERCIAL)'],
                ['code' => '402', 'name' => 'KITABI - NYAMIRAMBO - KIMISAGARA - NYABUGOGO - DOWNTOWN'],
                ['code' => '403', 'name' => 'NYACYONGA - DOWNTOWN'],
                ['code' => '404', 'name' => 'BISHENYI - DOWNTOWN (via NYABUGOGO)'],
                ['code' => '405', 'name' => 'NYABUGOGO - KANYINYA - SHYORONGI'],
                ['code' => '414', 'name' => 'KARAMA - NYABUGOGO (via RURIBA)'],
                ['code' => '416', 'name' => 'GIHARA - DOWNTOWN (via NYABUGOGO)'],
                ['code' => '416-2', 'name' => 'NYABUGOGO - BWERAMVURA'],
                ['code' => '416-3', 'name' => 'DOWNTOWN - CBD (IZENGURUKA MU MUJYI)'],
            ],
        ];

        foreach ($corridors as $corridorCode => $routes) {
            $corridor = Corridor::firstOrCreate(
                ['code' => $corridorCode],
                [
                    'name' => 'Corridor ' . $corridorCode,
                    'kinyarwanda_name' => 'ICYEREKEZO ' . $corridorCode,
                    'start_zone_id' => $defaultZone->id,
                    'end_zone_id' => $defaultZone->id,
                    'base_fare' => 200,
                    'price_per_km' => 50,
                ]
            );

            foreach ($routes as $r) {
                // Extract via portion in parentheses if present
                $via = null;
                $name = $r['name'];
                if (preg_match('/\((via\s+[^)]+)\)/i', $name, $m)) {
                    $via = $m[1];
                    // remove the parenthetical
                    $name = trim(str_replace($m[0], '', $name));
                }

                // Normalize route code (use string)
                $routeCode = (string) $r['code'];

                // Parse origin/destination: split on ' - '
                $parts = array_map('trim', preg_split('/\s*-\s*/', $name));
                $origin = $parts[0] ?? null;
                $destination = $parts[count($parts) - 1] ?? null;

                $route = TransportRoute::updateOrCreate(
                    ['route_code' => $routeCode],
                    [
                        'corridor_id' => $corridor->id,
                        'name' => $name,
                        'via' => $via,
                        'origin' => $origin,
                        'destination' => $destination,
                        'is_active' => true,
                    ]
                );

                // Create a driver and vehicle representing a bus for this route
                $driverEmail = 'driver_' . Str::slug($routeCode) . '@example.local';
                $user = User::firstOrCreate(
                    ['email' => $driverEmail],
                    [
                        'name' => 'Driver ' . $routeCode,
                        'password' => bcrypt('password'),
                        'role' => 'DRIVER',
                        'is_approved' => true,
                    ]
                );

                $driver = Driver::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'license_number' => 'LIC-' . strtoupper(Str::random(6)),
                        'license_plate' => strtoupper(Str::random(6)),
                        'status' => 'approved',
                        'availability_status' => 'available',
                    ]
                );

                $vehicle = Vehicle::firstOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'make' => 'Government Bus',
                        'model' => 'City Bus',
                        'year' => date('Y'),
                        'color' => 'white',
                        'vehicle_type' => 'van',
                        'seats' => 40,
                        'is_active' => true,
                    ]
                );

                // Create a sample scheduled ride for this route
                // Departure times staggered in next few days to avoid collisions
                $departure = now()->addDays(rand(1, 10))->setTime(rand(5, 20), 0, 0);

                // Avoid duplicate rides for same route at same departure
                $existing = Ride::query()
                    ->where('route_id', $route->id)
                    ->where('departure_time', $departure)
                    ->first();

                if (! $existing) {
                    Ride::create([
                        'driver_id' => $driver->id,
                        'vehicle_id' => $vehicle->id,
                        'corridor_id' => $corridor->id,
                        'route_id' => $route->id,
                        'transport_type' => 'BUS',
                        'travel_mode' => 'SCHEDULED',
                        'origin_address' => $route->origin,
                        'origin_lat' => -1.9536,
                        'origin_lng' => 29.8739,
                        'destination_address' => $route->destination,
                        'destination_lat' => -1.9536,
                        'destination_lng' => 29.8739,
                        'bus_number' => $routeCode,
                        'available_seats' => 40,
                        'price_per_seat' => 200,
                        'currency' => 'RWF',
                        'departure_time' => $departure,
                        'arrival_time_estimated' => $departure->copy()->addHours(1),
                        'status' => 'published',
                        'ride_type' => 'LOCAL',
                    ]);
                }
            }
        }
    }
}
