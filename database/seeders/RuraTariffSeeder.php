<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RuraTariff;

class RuraTariffSeeder extends Seeder
{
    public function run(): void
    {
        $tariffs = [
            // Only a few rows for brevity; add all from your RURA_TARIFFS
            ["route_code" => "101", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "DOWN TOWN BUS PARK", "fare_rwf" => 307],
            ["route_code" => "102", "corridor" => "A", "origin_stop" => "KABUGA BUS PARK", "destination_stop" => "NYABUGOGO BUS PARK", "fare_rwf" => 741],
            ["route_code" => "103", "corridor" => "A", "origin_stop" => "DOWN TOWN BUS PARK", "destination_stop" => "RUBIRIZI BUS TERMINAL", "fare_rwf" => 484],
            ["route_code" => "105", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "NYABUGOGO BUS PARK", "fare_rwf" => 355],
            ["route_code" => "108", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "NYANZA BUS PARK", "fare_rwf" => 256],
            ["route_code" => "109", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "BWERANKORI BUS TERMINAL", "fare_rwf" => 306],
            ["route_code" => "112", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "NYABUGOGO BUS PARK", "fare_rwf" => 307],
            ["route_code" => "120", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "SEZ BUS TERMINAL", "fare_rwf" => 295],
            ["route_code" => "124", "corridor" => "A", "origin_stop" => "DOWN TOWN BUS PARK", "destination_stop" => "KABUGA BUS PARK", "fare_rwf" => 741],
            ["route_code" => "125", "corridor" => "A", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "BUSANZA BUS TERMINAL", "fare_rwf" => 267],
            // ...add all other tariffs from your list...
        ];
        foreach ($tariffs as $row) {
            RuraTariff::updateOrCreate([
                'route_code' => $row['route_code'],
                'origin_stop' => $row['origin_stop'],
                'destination_stop' => $row['destination_stop'],
            ], $row);
        }
    }
}
