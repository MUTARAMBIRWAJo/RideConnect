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
     * Fallback coordinates for common locations in Rwanda (for testing when API key lacks billing)
     */
    private const FALLBACK_LOCATIONS = [
        'kigali' => ['lat' => -1.9536, 'lng' => 30.0605],
        'kigali, rwanda' => ['lat' => -1.9536, 'lng' => 30.0605],
        'kigali international airport' => ['lat' => -1.9753, 'lng' => 30.1376],
        'huye' => ['lat' => -2.6000, 'lng' => 29.7450],
        'butare' => ['lat' => -2.6000, 'lng' => 29.7450],
        'gitarama' => ['lat' => -1.9500, 'lng' => 30.0667],
        'muhanga' => ['lat' => -2.0167, 'lng' => 30.4333],
        'musanze' => ['lat' => -1.5000, 'lng' => 29.6333],
        'gisenyi' => ['lat' => -2.0681, 'lng' => 29.2553],
    ];

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
                // Fall through to fallback coordinates
            } else {
                $results = $response->json('results', []);
                $status = $response->json('status');

                if (! empty($results)) {
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
                } else {
                    Log::warning('GeocodingService: No results from API', [
                        'address' => $address,
                        'status' => $status,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('GeocodingService: Exception during geocoding', [
                'address' => $address,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }

        // Fallback: Check for known locations
        $fallback = $this->getFallbackCoordinates($address);
        if ($fallback) {
            Log::info('GeocodingService: Using fallback coordinates', [
                'address' => $address,
                'lat' => $fallback['lat'],
                'lng' => $fallback['lng'],
            ]);
            return $fallback;
        }

        Log::error('GeocodingService: Could not geocode address', [
            'address' => $address,
            'note' => 'No API results and no fallback coordinates found. Provide lat/lng directly in request.',
        ]);
        return null;
    }

    /**
     * Get fallback coordinates for known locations (for testing/offline mode)
     */
    private function getFallbackCoordinates(string $address): ?array
    {
        $normalized = strtolower(trim($address));
        
        // First try exact match
        if (isset(self::FALLBACK_LOCATIONS[$normalized])) {
            $coords = self::FALLBACK_LOCATIONS[$normalized];
            return [
                'lat'              => $coords['lat'],
                'lng'              => $coords['lng'],
                'formatted_address' => $address . ' (fallback)',
            ];
        }

        // Then try partial match, starting with longest keys first (most specific)
        $sortedKeys = array_keys(self::FALLBACK_LOCATIONS);
        usort($sortedKeys, fn($a, $b) => strlen($b) <=> strlen($a));
        
        foreach ($sortedKeys as $location) {
            if (stripos($normalized, $location) !== false) {
                $coords = self::FALLBACK_LOCATIONS[$location];
                return [
                    'lat'              => $coords['lat'],
                    'lng'              => $coords['lng'],
                    'formatted_address' => $address . ' (fallback)',
                ];
            }
        }

        return null;
    }

    private function getApiKey(): ?string
    {
        return config('laramaps.api_key') ?: config('services.google_maps.key');
    }
}
