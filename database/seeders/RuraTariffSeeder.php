<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\RuraTariff;

class RuraTariffSeeder extends Seeder
{
    public function run(): void
    {
        $tariffs = [
            // Corridor A
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
            // Corridor B
            ["route_code" => "104", "corridor" => "B", "origin_stop" => "DOWN TOWN BUS PARK", "destination_stop" => "KIBAYA BUS TERMINAL", "fare_rwf" => 516],
            ["route_code" => "106", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "NDERA BUS TERMINAL", "fare_rwf" => 269],
            ["route_code" => "107", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "MASAKA BUS TERMINAL", "fare_rwf" => 384],
            ["route_code" => "111", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "KABUGA BUS PARK", "fare_rwf" => 420],
            ["route_code" => "113", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "BUSANZA BUS TERMINAL", "fare_rwf" => 227],
            ["route_code" => "114", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "KIBAYA BUS TERMINAL", "fare_rwf" => 224],
            ["route_code" => "115", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "BUSANZA BUS TERMINAL", "fare_rwf" => 291],
            ["route_code" => "118", "corridor" => "B", "origin_stop" => "NYABUGOGO BUS PARK", "destination_stop" => "KIBAYA BUS TERMINAL", "fare_rwf" => 565],
            ["route_code" => "121", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "MASORO (AUCA) BUS TERMINAL", "fare_rwf" => 291],
            ["route_code" => "122", "corridor" => "B", "origin_stop" => "REMERA BUS PARK", "destination_stop" => "GASOGI BUS TERMINAL", "fare_rwf" => 439],
        ];

        if (! DB::getSchemaBuilder()->hasTable('rura_tariffs')) {
            return;
        }

        $this->seedTariffs($tariffs);
    }

    private function seedTariffs(array $tariffs): void
    {
        $routeCodes = array_unique(array_column($tariffs, 'route_code'));

        DB::transaction(function () use ($tariffs, $routeCodes) {
            DB::table('rura_tariffs')
                ->whereIn('route_code', $routeCodes)
                ->delete();

            DB::table('rura_tariffs')->insert($tariffs);
        });
    }
}
