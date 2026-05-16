<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Location\GeocodingService;
use App\Services\Location\LocationSearchService;
use App\Services\Location\ReverseGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LocationApiController — standalone Google Maps-backed locations endpoint.
 *
 * Endpoints:
 *  GET  /api/v1/locations/search?q=...          — Places autocomplete
 *  GET  /api/v1/locations/reverse-geocode        — reverse geocode from lat/lng
 */
class LocationApiController extends Controller
{
    public function __construct(
        private readonly LocationSearchService $searchService,
        private readonly GeocodingService $geocodingService,
        private readonly ReverseGeocodingService $reverseGeocodingService,
    ) {}

    /**
     * Search places by user-typed query string.
     *
     * Query params:
     *  q       (required)  search query, e.g. "Nyabugogo Bus Park"
     *  country (optional)  ISO-3166-1 alpha-2, default = "rw"
     *
     * Response: { success: true, data: [{ place_id, description, main_text, secondary_text }] }
     */
    public function search(Request $request): JsonResponse
    {
        $query   = trim((string) $request->query('q', ''));
        $country = $request->query('country', 'rw');

        if (mb_strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $results = $this->searchService->search($query, $country);

            return response()->json([
                'success' => true,
                'data'    => $results,
            ]);
        } catch (\Throwable $e) {
            Log::error('Location search error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Location search failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Resolve a Google place_id to full coordinates + formatted address.
     *
     * Query params:
     *  place_id  (required)
     *
     * Response: { success: true, data: { place_id, formatted_address, lat, lng } }
     */
    public function placeDetails(Request $request): JsonResponse
    {
        $placeId = $request->query('place_id', '');

        if (! $placeId) {
            return response()->json([
                'success' => false,
                'message' => 'place_id is required.',
            ], 422);
        }

        try {
            $details = $this->searchService->getDetails($placeId);

            if (! $details) {
                return response()->json([
                    'success' => false,
                    'message' => 'Place details not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $details,
            ]);
        } catch (\Throwable $e) {
            Log::error('Location placeDetails error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Could not retrieve place details.',
            ], 500);
        }
    }

    /**
     * Reverse-geocode coordinates into a human-readable address.
     *
     * Query params:
     *  lat  (required)  latitude  -90 .. 90
     *  lng  (required)  longitude -180 .. 180
     *
     * Response: { success: true, data: { lat, lng, formatted_address } }
     */
    public function reverseGeocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        try {
            $result = $this->reverseGeocodingService->reverseGeocode(
                (float) $validated['lat'],
                (float) $validated['lng']
            );

            if (! $result) {
                return response()->json([
                    'success' => false,
                    'message' => 'No address found for these coordinates.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Location reverse-geocode error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Reverse geocoding failed.',
            ], 500);
        }
    }

    /**
     * Forward-geocode an address string into coordinates.
     *
     * Query params:
     *  address  (required)  human-readable address
     *
     * Response: { success: true, data: { lat, lng, formatted_address } }
     */
    public function geocode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address' => 'required|string|min:3',
        ]);

        try {
            $result = $this->geocodingService->geocode($validated['address']);

            if (! $result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not geocode the provided address.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error('Location geocode error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Geocoding failed.',
            ], 500);
        }
    }
}
