<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'driver_id' => 1,
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2022,
                'color' => 'Silver',
                'vehicle_type' => 'sedan',
                'seats' => 4,
                'air_conditioning' => true,
                'is_active' => true,
                'verified_at' => now()->subDays(25),
            ],
            [
                'driver_id' => 2,
                'make' => 'Honda',
                'model' => 'CR-V',
                'year' => 2023,
                'color' => 'White',
                'vehicle_type' => 'suv',
                'seats' => 5,
                'air_conditioning' => true,
                'is_active' => true,
                'verified_at' => now()->subDays(55),
            ],
            [
                'driver_id' => 3,
                'make' => 'Toyota',
                'model' => 'Corolla',
                'year' => 2021,
                'color' => 'Blue',
                'vehicle_type' => 'compact',
                'seats' => 4,
                'air_conditioning' => true,
                'is_active' => true,
                'verified_at' => now()->subDays(10),
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
