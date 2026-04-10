<?php

namespace Database\Seeders;

use App\Models\Corridor;
use App\Models\Ride;
use App\Models\RuraTariff;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ZoneCorridorSeeder extends Seeder
{
    public function run(): void
    {
        $legacyZones = [
            ['name' => 'Remera', 'code' => 'REM'],
            ['name' => 'Nyabugogo', 'code' => 'NYA'],
            ['name' => 'Kimironko', 'code' => 'KIM'],
            ['name' => 'Kacyiru', 'code' => 'KAC'],
        ];

        foreach ($legacyZones as $zoneData) {
            Zone::query()->updateOrCreate(['code' => $zoneData['code']], $zoneData);
        }

        $tariffs = RuraTariff::query()->get();

        if ($tariffs->isEmpty()) {
            $this->seedLegacyCorridors();

            return;
        }

        $corridorsByRoute = [];

        foreach ($tariffs as $tariff) {
            $originStop = trim((string) $tariff->origin_stop);
            $destinationStop = trim((string) $tariff->destination_stop);

            if ($originStop === '' || $destinationStop === '') {
                continue;
            }

            $startZone = Zone::query()->updateOrCreate(
                ['code' => $this->zoneCodeFromStop($originStop)],
                ['name' => Str::title(strtolower($originStop))]
            );

            $endZone = Zone::query()->updateOrCreate(
                ['code' => $this->zoneCodeFromStop($destinationStop)],
                ['name' => Str::title(strtolower($destinationStop))]
            );

            $corridorName = sprintf('%s -> %s (%s)', $originStop, $destinationStop, $tariff->route_code);

            $corridor = Corridor::query()->updateOrCreate(
                ['name' => $corridorName],
                [
                    'start_zone_id' => $startZone->id,
                    'end_zone_id' => $endZone->id,
                    'base_fare' => (float) $tariff->fare_rwf,
                    'price_per_km' => 0,
                ]
            );

            $corridorsByRoute[$this->pairKey($originStop, $destinationStop)] = [
                'corridor_id' => $corridor->id,
                'zone_id' => $startZone->id,
                'fare' => (float) $tariff->fare_rwf,
            ];
        }

        Ride::query()->chunkById(200, function ($rides) use ($corridorsByRoute): void {
            foreach ($rides as $ride) {
                $origin = trim((string) $ride->origin_address);
                $destination = trim((string) $ride->destination_address);

                if ($origin === '' || $destination === '') {
                    continue;
                }

                $match = $corridorsByRoute[$this->pairKey($origin, $destination)] ?? null;

                if (! $match) {
                    continue;
                }

                $ride->forceFill([
                    'zone_id' => $match['zone_id'],
                    'corridor_id' => $match['corridor_id'],
                    'price_per_seat' => $match['fare'],
                ])->save();
            }
        });
    }

    private function seedLegacyCorridors(): void
    {
        $remera = Zone::query()->where('code', 'REM')->first();
        $nyabugogo = Zone::query()->where('code', 'NYA')->first();
        $kimironko = Zone::query()->where('code', 'KIM')->first();
        $kacyiru = Zone::query()->where('code', 'KAC')->first();

        $remera = Zone::query()->where('code', 'REM')->first();
        $nyabugogo = Zone::query()->where('code', 'NYA')->first();
        $kimironko = Zone::query()->where('code', 'KIM')->first();
        $kacyiru = Zone::query()->where('code', 'KAC')->first();

        $corridors = [
            [
                'name' => 'Remera - Nyabugogo',
                'start_zone_id' => $remera?->id,
                'end_zone_id' => $nyabugogo?->id,
                'base_fare' => 350,
                'price_per_km' => 120,
            ],
            [
                'name' => 'Kimironko - Kacyiru',
                'start_zone_id' => $kimironko?->id,
                'end_zone_id' => $kacyiru?->id,
                'base_fare' => 300,
                'price_per_km' => 110,
            ],
        ];

        foreach ($corridors as $corridorData) {
            if (! $corridorData['start_zone_id'] || ! $corridorData['end_zone_id']) {
                continue;
            }

            Corridor::query()->updateOrCreate(
                ['name' => $corridorData['name']],
                $corridorData
            );
        }
    }

    private function zoneCodeFromStop(string $stop): string
    {
        $normalized = preg_replace('/[^A-Z0-9]/', '', Str::upper($stop)) ?? 'ZONE';

        return substr(str_pad($normalized, 3, 'X'), 0, 3);
    }

    private function pairKey(string $origin, string $destination): string
    {
        $a = $this->normalizeStop($origin);
        $b = $this->normalizeStop($destination);

        return $a <= $b ? $a . '|' . $b : $b . '|' . $a;
    }

    private function normalizeStop(string $value): string
    {
        return Str::upper(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }
}
