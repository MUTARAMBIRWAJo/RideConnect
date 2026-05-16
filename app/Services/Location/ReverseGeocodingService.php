<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ReverseGeocodingService — converts latitude / longitude back to a
 * human-readable place name / formatted address using the Google
 * Reverse Geocoding API.
 *
 * Used by the map-picker interaction:
 *   user taps map → coordinates captured → reverse geocoded → place name shown.
 */
class ReverseGeocodingService
{
    private const REVERSE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Convert coordinates to an address.
     *
     * @return array{lat:float,lng:float,formatted_address:string}|null
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get(self::REVERSE_URL, [
                    'latlng' => sprintf('%F,%F', $lat, $lng),
                    'key' => $apiKey,
                    'language' => 'en',
                ]);

            if ($response->failed()) {
                return null;
            }

            $results = $response->json('results', []);

            if (empty($results)) {
                return null;
            }

            $first = $results[0];

            return [
                'lat'               => (float) ($first['geometry']['location']['lat'] ?? $lat),
                'lng'               => (float) ($first['geometry']['location']['lng'] ?? $lng),
                'formatted_address' => $first['formatted_address'] ?? sprintf('%F, %F', $lat, $lng),
            ];
        } catch (\Throwable $e) {
            Log::error('ReverseGeocodingService error: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    private function getApiKey(): ?string
    {
        return config('laramaps.api_key') ?: config('services.google_maps.key');
    }
}
