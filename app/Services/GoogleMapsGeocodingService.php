<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsGeocodingService
{
    private const GOOGLE_GEOCODING_URL = 'https://maps.googleapis.com/maps/api/geocode/json';
    private const API_TIMEOUT = 10; // seconds
    private const MAX_LOCATION_LENGTH = 255;

    /**
     * Geocode a location name to latitude and longitude.
     *
     * Attempts geocoding in this order:
     * 1. Google Maps Geocoding API (if key available)
     * 2. Local database fallback (saved_locations table)
     * 3. Throws GeocodingException if all attempts fail
     *
     * @param  string  $location  Location name or address
     * @return array{lat: float, lng: float, formatted_address: string|null}
     *
     * @throws GeocodingException
     */
    public function geocode(string $location): array
    {
        // Input validation
        $location = trim($location);
        if (empty($location)) {
            throw new GeocodingException('Location cannot be empty');
        }

        if (strlen($location) > self::MAX_LOCATION_LENGTH) {
            throw new GeocodingException('Location name is too long (max 255 characters)');
        }

        Log::info('Geocoding request initiated', [
            'location' => $location,
        ]);

        // Step 1: Try Google Maps API
        $googleResult = $this->tryGoogleGeocoding($location);
        if ($googleResult !== null) {
            Log::info('Geocoding successful via Google Maps API', [
                'location' => $location,
                'lat' => $googleResult['lat'],
                'lng' => $googleResult['lng'],
            ]);
            return $googleResult;
        }

        // Step 2: Try local database fallback
        $dbResult = $this->tryDatabaseFallback($location);
        if ($dbResult !== null) {
            Log::info('Geocoding successful via database fallback', [
                'location' => $location,
                'lat' => $dbResult['lat'],
                'lng' => $dbResult['lng'],
            ]);
            return $dbResult;
        }

        // Step 3: Both methods failed
        Log::error('Geocoding failed for all methods', [
            'location' => $location,
        ]);

        throw new GeocodingException("Could not geocode location: {$location}");
    }

    /**
     * Attempt to geocode using Google Maps API.
     *
     * @param  string  $location
     * @return array|null
     */
    private function tryGoogleGeocoding(string $location): ?array
    {
        $apiKey = config('services.google_maps.key');

        if (empty($apiKey)) {
            Log::debug('Google Maps API key not configured, skipping Google geocoding');
            return null;
        }

        try {
            Log::debug('Attempting Google Maps geocoding', ['location' => $location]);

            $response = Http::timeout(self::API_TIMEOUT)
                ->get(self::GOOGLE_GEOCODING_URL, [
                    'address' => $location,
                    'key' => $apiKey,
                    'region' => 'rw', // Rwanda region
                ])
                ->json();

            Log::debug('Google Maps API response received', [
                'status' => $response['status'] ?? 'unknown',
                'results_count' => count($response['results'] ?? []),
            ]);

            // Check for successful response
            if ($response['status'] !== 'OK') {
                if ($response['status'] === 'ZERO_RESULTS') {
                    Log::debug('Google Maps returned ZERO_RESULTS', ['location' => $location]);
                } elseif ($response['status'] === 'OVER_QUERY_LIMIT') {
                    Log::warning('Google Maps API quota exceeded');
                } else {
                    Log::warning('Google Maps API error', ['status' => $response['status']]);
                }
                return null;
            }

            // Extract first result
            if (empty($response['results'])) {
                Log::debug('Google Maps returned empty results');
                return null;
            }

            $result = $response['results'][0];
            $location_obj = $result['geometry']['location'];
            $formatted_address = $result['formatted_address'] ?? null;

            return [
                'lat' => (float) $location_obj['lat'],
                'lng' => (float) $location_obj['lng'],
                'formatted_address' => $formatted_address,
            ];
        } catch (\Exception $e) {
            Log::error('Google Maps geocoding exception', [
                'location' => $location,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Fallback method: search local database for saved locations.
     *
     * Uses LIKE matching to handle partial location names.
     *
     * @param  string  $location
     * @return array|null
     */
    private function tryDatabaseFallback(string $location): ?array
    {
        try {
            // Ensure table exists before querying
            if (!$this->savedLocationsTableExists()) {
                Log::debug('saved_locations table does not exist, skipping database fallback');
                return null;
            }

            Log::debug('Attempting database fallback lookup', ['location' => $location]);

            // Search with LIKE pattern (case-insensitive)
            $saved = DB::table('saved_locations')
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($location) . '%'])
                ->limit(1)
                ->first(['name', 'lat', 'lng']);

            if ($saved === null) {
                Log::debug('No matching location in database', ['location' => $location]);
                return null;
            }

            Log::debug('Found matching location in database', [
                'searched' => $location,
                'matched' => $saved->name,
            ]);

            return [
                'lat' => (float) $saved->lat,
                'lng' => (float) $saved->lng,
                'formatted_address' => $saved->name,
            ];
        } catch (\Exception $e) {
            Log::error('Database fallback exception', [
                'location' => $location,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if saved_locations table exists.
     *
     * @return bool
     */
    private function savedLocationsTableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('saved_locations');
        } catch (\Exception $e) {
            Log::error('Error checking saved_locations table existence', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get a list of all saved locations (for debugging/admin purposes).
     *
     * @return array
     */
    public function getSavedLocations(): array
    {
        if (!$this->savedLocationsTableExists()) {
            return [];
        }

        return DB::table('saved_locations')
            ->get()
            ->map(fn ($loc) => [
                'id' => $loc->id,
                'name' => $loc->name,
                'lat' => (float) $loc->lat,
                'lng' => (float) $loc->lng,
            ])
            ->toArray();
    }

    /**
     * Add or update a saved location (for admin purposes).
     *
     * @param  string  $name
     * @param  float  $lat
     * @param  float  $lng
     * @return bool
     */
    public function saveLLocation(string $name, float $lat, float $lng): bool
    {
        if (!$this->savedLocationsTableExists()) {
            Log::warning('Cannot save location: saved_locations table does not exist');
            return false;
        }

        try {
            DB::table('saved_locations')->updateOrInsert(
                ['name' => $name],
                ['lat' => $lat, 'lng' => $lng]
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Error saving location to database', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
