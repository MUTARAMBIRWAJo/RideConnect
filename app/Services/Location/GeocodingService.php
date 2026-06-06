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

        if (! $apiKey) {
            Log::error('GeocodingService: API key not configured', [
                'laramaps_key' => config('laramaps.api_key') ? 'set' : 'missing',
                'services_key' => config('services.google_maps.key') ? 'set' : 'missing',
                'env_var' => env('GOOGLE_MAPS_API_KEY') ? 'set' : 'missing',
            ]);
            return null;
        }

        if (! trim($address)) {
            Log::warning('GeocodingService: Empty address provided');
            return null;
        }

        try {
            Log::info('GeocodingService: Geocoding address', [
                'address' => $address,
                'country' => $country,
            ]);

            $response = Http::timeout(10)
                ->get(self::GEOCODE_URL, [
                    'address' => $address,
                    'key' => $apiKey,
                    'language' => 'en',
                    'region' => $country,
                ]);

            if ($response->failed()) {
                Log::error('GeocodingService: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'address' => $address,
                ]);
                return null;
            }

            $results = $response->json('results', []);
            $status = $response->json('status');

            if (empty($results)) {
                Log::warning('GeocodingService: No results found', [
                    'address' => $address,
                    'status' => $status,
                ]);
                return null;
            }

            $first = $results[0];
            $location = $first['geometry']['location'];

            Log::info('GeocodingService: Successfully geocoded address', [
                'address' => $address,
                'lat' => $location['lat'],
                'lng' => $location['lng'],
            ]);

            return [
                'lat'              => (float) ($location['lat'] ?? 0.0),
                'lng'              => (float) ($location['lng'] ?? 0.0),
                'formatted_address' => $first['formatted_address'] ?? $address,
            ];
        } catch (\Throwable $e) {
            Log::error('GeocodingService: Exception during geocoding', [
                'address' => $address,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    private function getApiKey(): ?string
    {
        return config('laramaps.api_key') ?: config('services.google_maps.key');
    }
}
