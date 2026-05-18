<?php

namespace Database\Seeders;

use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\Driver;
use App\Models\TransportCorridor;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class BusWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $corridor1 = TransportCorridor::create([
            'corridor_code' => 'DWN-AIR',
            'corridor_name' => 'Downtown - Airport Express',
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 45,
        ]);

        $corridor2 = TransportCorridor::create([
            'corridor_code' => 'CTY-WLS',
            'corridor_name' => 'City Center - Westlands',
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 30,
        ]);

        $corridor3 = TransportCorridor::create([
            'corridor_code' => 'EAM-EMB',
            'corridor_name' => 'East Africa Mall - Embakasi',
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 25,
        ]);

        $stops1 = [
            ['stop_name' => 'Downtown Bus Station', 'stop_order' => 1, 'latitude' => -1.286389, 'longitude' => 36.817223, 'is_major_terminal' => true],
            ['stop_name' => 'Nairobi Central', 'stop_order' => 2, 'latitude' => -1.292911, 'longitude' => 36.824141, 'is_major_terminal' => false],
            ['stop_name' => 'JKIA Terminal 1', 'stop_order' => 3, 'latitude' => -1.319264, 'longitude' => 36.927670, 'is_major_terminal' => false],
            ['stop_name' => 'JKIA Terminal 3', 'stop_order' => 4, 'latitude' => -1.318894, 'longitude' => 36.929894, 'is_major_terminal' => true],
        ];

        foreach ($stops1 as $stopData) {
            CorridorStop::create(array_merge($stopData, [
                'corridor_id' => $corridor1->id,
                'status' => 'active',
            ]));
        }

        $stops2 = [
            ['stop_name' => 'City Center', 'stop_order' => 1, 'latitude' => -1.286667, 'longitude' => 36.82, 'is_major_terminal' => true],
            ['stop_name' => 'Kilimani Estate', 'stop_order' => 2, 'latitude' => -1.300833, 'longitude' => 36.7825, 'is_major_terminal' => false],
            ['stop_name' => 'Westlands', 'stop_order' => 3, 'latitude' => -1.280556, 'longitude' => 36.802222, 'is_major_terminal' => false],
            ['stop_name' => 'Westlands Shopping Mall', 'stop_order' => 4, 'latitude' => -1.2785, 'longitude' => 36.804444, 'is_major_terminal' => true],
        ];

        foreach ($stops2 as $stopData) {
            CorridorStop::create(array_merge($stopData, [
                'corridor_id' => $corridor2->id,
                'status' => 'active',
            ]));
        }

        $stops3 = [
            ['stop_name' => 'East Africa Mall', 'stop_order' => 1, 'latitude' => -1.306667, 'longitude' => 36.76, 'is_major_terminal' => true],
            ['stop_name' => 'South B', 'stop_order' => 2, 'latitude' => -1.310833, 'longitude' => 36.770833, 'is_major_terminal' => false],
            ['stop_name' => 'South C', 'stop_order' => 3, 'latitude' => -1.320556, 'longitude' => 36.777778, 'is_major_terminal' => false],
            ['stop_name' => 'Embakasi Estate', 'stop_order' => 4, 'latitude' => -1.330833, 'longitude' => 36.79, 'is_major_terminal' => true],
        ];

        foreach ($stops3 as $stopData) {
            CorridorStop::create(array_merge($stopData, [
                'corridor_id' => $corridor3->id,
                'status' => 'active',
            ]));
        }

        $user = User::firstOrCreate(
            ['email' => 'driver@rideconnect.test'],
            [
                'name' => 'John Driver',
                'email' => 'driver@rideconnect.test',
                'password' => bcrypt('password'),
            ]
        );

        $driver = Driver::firstOrCreate(
            ['license_plate' => 'B1234'],
            [
                'user_id' => $user->id,
                'license_number' => 'DRIVER-001',
                'status' => 'active',
                'availability_status' => 'available',
            ]
        );

        $vehicle = Vehicle::firstOrCreate(
            ['license_plate' => 'KBA123ABC'],
            [
                'driver_id' => $driver->id,
                'make' => 'Toyota',
                'model' => 'Coaster',
                'year' => 2019,
                'color' => 'White',
                'vehicle_type' => 'van',
                'seats' => 14,
                'air_conditioning' => true,
                'is_active' => true,
            ]
        );

        BusRouteAssignment::firstOrCreate(
            ['bus_id' => $vehicle->id, 'corridor_id' => $corridor1->id],
            [
                'driver_id' => $driver->id,
                'status' => 'active',
                'started_at' => now(),
            ]
        );

        BusRouteAssignment::firstOrCreate(
            ['bus_id' => $vehicle->id, 'corridor_id' => $corridor2->id],
            [
                'driver_id' => $driver->id,
                'status' => 'active',
                'started_at' => now(),
            ]
        );

        $this->command->info('Bus workflow seeding completed successfully!');
    }
}
