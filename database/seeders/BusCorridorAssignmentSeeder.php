<?php

namespace Database\Seeders;

use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\Driver;
use App\Models\TransportCorridor;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusCorridorAssignmentSeeder extends Seeder
{
    /**
     * Assign active vehicles to all transport corridors.
     * Uses approved drivers with active vans.
     * Skips corridors that already have assignments to preserve existing data.
     */
    public function run(): void
    {
        $this->ensureBusCorridorsExist();
        $this->ensureEligibleDriverPool();

        // Get all approved drivers with active vans
        $driversWithVans = Driver::query()
            ->where('status', 'approved')
            ->whereIn('availability_status', ['available', 'online'])
            ->whereHas('vehicles', function ($query) {
                $query->where('vehicle_type', 'van')
                    ->where('is_active', true);
            })
            ->with(['vehicles' => function ($query) {
                $query->where('vehicle_type', 'van')
                    ->where('is_active', true);
            }])
            ->get();

        $corridors = TransportCorridor::query()
            ->where('transport_type', 'BUS')
            ->where('status', 'active')
            ->get();

        $assigned = 0;
        $skipped = 0;
        $driverCount = count($driversWithVans);

        if ($driverCount === 0) {
            $driversWithVans = $this->createFallbackDriverWithVan();
            $driverCount = count($driversWithVans);
        }

        foreach ($corridors as $index => $corridor) {
            // Check if corridor already has assignments
            if (BusRouteAssignment::where('corridor_id', $corridor->id)->exists()) {
                $skipped++;
                continue;
            }

            // Round-robin: assign drivers cyclically to corridors
            $driverIndex = $index % $driverCount;
            $driver = $driversWithVans[$driverIndex];

            // Get first active van for this driver
            $van = $driver->vehicles->first();

            if (!$van) {
                $this->command?->warn("Driver {$driver->id} has no active vans. Skipping corridor {$corridor->corridor_code}");
                continue;
            }

            BusRouteAssignment::firstOrCreate(
                ['bus_id' => $van->id, 'corridor_id' => $corridor->id],
                [
                    'driver_id' => $driver->id,
                    'status' => 'active',
                    'started_at' => now(),
                ]
            );

            $firstStop = CorridorStop::query()
                ->where('corridor_id', $corridor->id)
                ->orderBy('stop_order')
                ->first();

            if ($firstStop && $firstStop->latitude !== null && $firstStop->longitude !== null) {
                $driver->forceFill([
                    'current_latitude' => $firstStop->latitude,
                    'current_longitude' => $firstStop->longitude,
                ])->save();
            }

            $assigned++;
        }

        $this->command?->info("BusCorridorAssignmentSeeder: assigned {$assigned} corridors, skipped {$skipped}.");
    }

    private function ensureBusCorridorsExist(): void
    {
        $hasActiveBusCorridors = TransportCorridor::query()
            ->where('transport_type', 'BUS')
            ->where('status', 'active')
            ->exists();

        if ($hasActiveBusCorridors) {
            return;
        }

        $this->command?->warn('No active BUS corridors found. Running TransportCorridorSeeder...');
        $this->call(TransportCorridorSeeder::class);
    }

    private function ensureEligibleDriverPool(): void
    {
        Driver::query()
            ->where('status', 'approved')
            ->where('availability_status', 'offline')
            ->whereHas('vehicles', fn ($query) => $query->where('vehicle_type', 'van')->where('is_active', true))
            ->limit(25)
            ->update(['availability_status' => 'available']);
    }

    private function createFallbackDriverWithVan()
    {
        $email = 'bus.seed.'.Str::lower(Str::random(8)).'@example.local';

        $user = User::query()->create([
            'name' => 'Bus Seed Driver',
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'role' => 'DRIVER',
            'is_verified' => true,
            'is_approved' => true,
        ]);

        $driver = Driver::query()->create([
            'user_id' => $user->id,
            'license_number' => 'SEED-'.strtoupper(Str::random(8)),
            'license_plate' => 'SEED'.strtoupper(Str::random(4)),
            'status' => 'approved',
            'availability_status' => 'available',
            'rating' => 4.5,
        ]);

        $driver->vehicles()->create([
            'make' => 'Toyota',
            'model' => 'Coaster',
            'year' => (int) now()->year,
            'color' => 'White',
            'vehicle_type' => 'van',
            'seats' => 29,
            'air_conditioning' => true,
            'is_active' => true,
        ]);

        return Driver::query()
            ->whereKey($driver->id)
            ->with(['vehicles' => function ($query) {
                $query->where('vehicle_type', 'van')->where('is_active', true);
            }])
            ->get();
    }
}
