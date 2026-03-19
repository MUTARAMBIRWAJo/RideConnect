<?php

namespace App\Services;

class RuraZoneService
{
    private array $zones = [
        // Only a few zones shown for brevity; add all from your Python RURA_ZONES
        [
            'name' => 'Remera',
            'lat_min' => -1.935,
            'lat_max' => -1.905,
            'lng_min' => 30.105,
            'lng_max' => 30.143,
            'corridor' => 'A/B/C/E',
            'terminal' => 'Remera Bus Park',
            'fare_base_rwf' => 256,
        ],
        [
            'name' => 'Nyabugogo',
            'lat_min' => -1.960,
            'lat_max' => -1.940,
            'lng_min' => 30.050,
            'lng_max' => 30.080,
            'corridor' => 'A/B/D/F/G',
            'terminal' => 'Nyabugogo Bus Park',
            'fare_base_rwf' => 205,
        ],
        // ...add all other zones here...
    ];

    public function coordsToZone(float $lat, float $lng): string
    {
        foreach ($this->zones as $zone) {
            if ($lat >= $zone['lat_min'] && $lat <= $zone['lat_max'] && $lng >= $zone['lng_min'] && $lng <= $zone['lng_max']) {
                return $zone['name'];
            }
        }
        // Fallback: nearest zone (not implemented for brevity)
        return 'Other';
    }

    // Add more methods for fare lookup, corridor, etc.
}
