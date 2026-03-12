<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverLocationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed driver locations around Kigali for dashboard map preview.
     */
    public function run(): void
    {
        $drivers = DB::table('mobile_users')
            ->join('drivers', 'drivers.user_id', '=', 'mobile_users.id')
            ->where('mobile_users.role', 'DRIVER')
            ->get(['drivers.id']);

        $kigaliLat = -1.9441;
        $kigaliLng = 30.0619;

        foreach ($drivers as $index => $driver) {
            $latOffset = (($index % 5) - 2) * 0.0045;
            $lngOffset = ((int) floor($index / 5) - 1) * 0.004;

            DB::table('driver_locations')->updateOrInsert(
                ['driver_id' => $driver->id],
                [
                    'latitude' => $kigaliLat + $latOffset,
                    'longitude' => $kigaliLng + $lngOffset,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
