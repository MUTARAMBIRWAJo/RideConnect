<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeocodingService — resolves a human-readable address to latitude / longitude
 * using the Google Geocoding API.
 *
 * Used by the backend when it needs to normalise or enrich a place string
 * from user input (Filament admin forms, API trip creation).
 */
class GeocodingService
{
    private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Convert an address string to coordinates.
     *
     * @return array{lat:float,lng:float,formatted_address:string}|null
     */
    public function geocode(string $address, ?string $country = 'rw'): ?array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey || ! trim($address)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get(self::GEOCODE_URL, [
                    'address' => $address,
                    'key' => $apiKey,
                    'language' => 'en',
                    'region' => $country,
                ]);

            if ($response->failed()) {
                return null;
            }

            $results = $response->json('results', []);

            if (empty($results)) {
                return null;
            }

            $first = $results[0];
            $location = $first['geometry']['location'];

            return [
                'lat'              => (float) ($location['lat'] ?? 0.0),
                'lng'              => (float) ($location['lng'] ?? 0.0),
                'formatted_address' => $first['formatted_address'] ?? $address,
            ];
        } catch (\Throwable $e) {
            Log::error('GeocodingService error: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    private function getApiKey(): ?string
    {
        return config('laramaps.api_key') ?: config('services.google_maps.key');
    }
}
