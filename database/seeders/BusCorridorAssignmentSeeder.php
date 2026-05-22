<?php

namespace Database\Seeders;

use App\Models\BusRouteAssignment;
use App\Models\Driver;
use App\Models\TransportCorridor;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusCorridorAssignmentSeeder extends Seeder
{
    /**
     * Assign active vehicles to all transport corridors.
     * Uses approved drivers with active vans.
     * Skips corridors that already have assignments to preserve existing data.
     */
    public function run(): void
    {
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
            $this->command?->warn('No approved drivers with active vans found. Skipping corridor assignments.');
            return;
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

            $assigned++;
        }

        $this->command?->info("BusCorridorAssignmentSeeder: assigned {$assigned} corridors, skipped {$skipped}.");
    }
}
