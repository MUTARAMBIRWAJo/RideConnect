<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FareCalculatorService
{
    public function estimate(
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng,
        string $transportType
    ): float {
        $distanceKm = $this->distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        if (Schema::hasColumn('rura_tariffs', 'base_fare') && Schema::hasColumn('rura_tariffs', 'price_per_km')) {
            $tariff = DB::table('rura_tariffs')
                ->when(Schema::hasColumn('rura_tariffs', 'transport_type'), fn ($query) => $query->where('transport_type', $transportType))
                ->orderBy('id')
                ->first();

            if ($tariff) {
                return round(((float) $tariff->base_fare) + ($distanceKm * (float) $tariff->price_per_km), 2);
            }
        }

        $fallback = match ($transportType) {
            'moto' => [500, 180],
            'car' => [1200, 350],
            'bus' => [300, 80],
            default => [800, 250],
        };

        return round($fallback[0] + ($distanceKm * $fallback[1]), 2);
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
