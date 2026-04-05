<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleV2Seeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $driverIds = DB::table('mobile_users')
            ->where('role', 'DRIVER')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        if ($driverIds->isEmpty()) {
            return;
        }

        $vehicles = [
            [
                'driver_id' => $driverIds[0] ?? null,
                'plate_number' => 'RAC 123 A',
                'vehicle_type' => 'Sedan',
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'color' => 'Silver',
                'capacity' => 4,
                'status' => 'ACTIVE',
                'created_at' => now()->subDays(58),
                'updated_at' => now()->subDays(58),
            ],
            [
                'driver_id' => $driverIds[1] ?? $driverIds[0],
                'plate_number' => 'RAD 456 B',
                'vehicle_type' => 'SUV',
                'brand' => 'Honda',
                'model' => 'CR-V',
                'color' => 'White',
                'capacity' => 5,
                'status' => 'ACTIVE',
                'created_at' => now()->subDays(43),
                'updated_at' => now()->subDays(43),
            ],
            [
                'driver_id' => $driverIds[2] ?? $driverIds[0],
                'plate_number' => 'RAE 789 C',
                'vehicle_type' => 'Sedan',
                'brand' => 'Hyundai',
                'model' => 'Elantra',
                'color' => 'Black',
                'capacity' => 4,
                'status' => 'ACTIVE',
                'created_at' => now()->subDays(28),
                'updated_at' => now()->subDays(28),
            ],
            [
                'driver_id' => $driverIds[3] ?? $driverIds[0],
                'plate_number' => 'RAF 321 D',
                'vehicle_type' => 'Minivan',
                'brand' => 'Toyota',
                'model' => 'HiAce',
                'color' => 'Blue',
                'capacity' => 8,
                'status' => 'INACTIVE',
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4),
            ],
            [
                'driver_id' => $driverIds[0],
                'plate_number' => 'RAG 654 E',
                'vehicle_type' => 'Hatchback',
                'brand' => 'Volkswagen',
                'model' => 'Golf',
                'color' => 'Red',
                'capacity' => 4,
                'status' => 'SUSPENDED',
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(10),
            ],
        ];

        foreach ($vehicles as $vehicle) {
            if ($vehicle['driver_id'] === null) {
                continue;
            }

            DB::table('vehicles_v2')->updateOrInsert(
                ['plate_number' => $vehicle['plate_number']],
                $vehicle
            );
        }
    }
}
