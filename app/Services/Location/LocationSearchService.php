<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LocationSearchService — search places by name using the Google Places API.
 *
 * Used by the frontend location-chooser (the search bar / autocomplete).
 * Returns place names, formatted addresses, and coordinates.
 *
 * Env config:
 *   LARAMAP_GOOGLE_API_KEY or GOOGLE_MAPS_API_KEY
 */
class LocationSearchService
{
    private const PLACES_AUTOCOMPLETE_URL = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    private const PLACES_DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    /**
     * Search for places matching the given input string.
     *
     * @param  string  $query           The user's search query (e.g. "Nyabugogo Bus Park")
     * @param  string|null  $country    ISO 3166-1 Alpha-2 country code (default: Rwanda "rw")
     * @return array<int, array{place_id:string,description:string,main_text:string,secondary_text:string,lat:float,lng:float}>
     */
    public function search(string $query, ?string $country = 'rw'): array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey) {
            Log::warning('LocationSearchService: no API key configured.');

            return [];
        }

        try {
            $response = Http::timeout(10)
                ->get(self::PLACES_AUTOCOMPLETE_URL, [
                    'input' => $query,
                    'key' => $apiKey,
                    'language' => 'en',
                    'components' => 'country:' . $country,
                ]);

            if ($response->failed()) {
                Log::warning('LocationSearchService: Places API request failed.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $predictions = $response->json('predictions', []);

            if (! is_array($predictions)) {
                return [];
            }

            return array_map(function (array $prediction): array {
                return [
                    'place_id'       => $prediction['place_id'] ?? '',
                    'description'    => $prediction['description'] ?? '',
                    'main_text'      => $prediction['structured_formatting']['main_text'] ?? $prediction['description'] ?? '',
                    'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? '',
                ];
            }, $predictions);
        } catch (\Throwable $e) {
            Log::error('LocationSearchService error: ' . $e->getMessage(), ['exception' => $e]);

            return [];
        }
    }

    /**
     * Resolve a place_id to its full details including coordinates.
     *
     * @return array{place_id:string,formatted_address:string,lat:float,lng:float}|null
     */
    public function getDetails(string $placeId): ?array
    {
        $apiKey = $this->getApiKey();

        if (! $apiKey || ! $placeId) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->get(self::PLACES_DETAILS_URL, [
                    'place_id' => $placeId,
                    'key' => $apiKey,
                    'language' => 'en',
                    'fields' => 'place_id,formatted_address,geometry',
                ]);

            if ($response->failed()) {
                return null;
            }

            $result = $response->json('result');

            if (! is_array($result)) {
                return null;
            }

            $location = $result['geometry']['location'] ?? null;

            return [
                'place_id'         => $result['place_id'] ?? $placeId,
                'formatted_address' => $result['formatted_address'] ?? '',
                'lat'              => $location['lat'] ?? 0.0,
                'lng'              => $location['lng'] ?? 0.0,
            ];
        } catch (\Throwable $e) {
            Log::error('LocationSearchService getDetails error: ' . $e->getMessage(), ['exception' => $e]);

            return null;
        }
    }

    private function getApiKey(): ?string
    {
        return config('laramaps.api_key') ?: config('services.google_maps.key');
    }
}
