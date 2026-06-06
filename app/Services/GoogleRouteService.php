<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * GoogleRouteService - Secure Backend Route Engine
 *
 * Handles all Google Routes API v2 communication.
 * The Flutter app NEVER calls Google directly - all routing goes through this service.
 *
 * Features:
 * - Secure (API key never exposed to client)
 * - Cacheable (routes cached for 5-10 minutes)
 * - Scalable (supports motorcycle, bus, private vehicle routing)
 * - Error handling (safe failures, logging)
 */
class GoogleRouteService
{
    private const CACHE_TTL_MINUTES = 10;
    private const TRAVEL_MODE = 'DRIVE';
    private const ROUTING_PREFERENCE = 'TRAFFIC_AWARE';

    /**
     * Compute route between two coordinates
     *
     * @param array $origin ['lat' => float, 'lng' => float]
     * @param array $destination ['lat' => float, 'lng' => float]
     * @return array {
     *     'success' => bool,
     *     'polyline' => string|null,
     *     'distance_meters' => int|null,
     *     'duration' => string|null,
     *     'distance_km' => float|null,
     *     'error' => string|null
     * }
     */
    public function computeRoute(array $origin, array $destination): array
    {
        try {
            // Generate cache key from coordinates
            $cacheKey = $this->generateCacheKey($origin, $destination);

            // Check cache first
            $cachedRoute = Cache::get($cacheKey);
            if ($cachedRoute) {
                Log::info('Route retrieved from cache', ['cache_key' => $cacheKey]);
                return array_merge(['success' => true], $cachedRoute);
            }

            // Call Google Routes API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => config('services.google_maps.key'),
                'X-Goog-FieldMask' => 'routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline',
            ])->timeout(config('services.google_maps.timeout', 10))
            ->post(config('services.google_maps.routes_api_url'), [
                'origin' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $origin['lat'],
                            'longitude' => $origin['lng'],
                        ]
                    ]
                ],
                'destination' => [
                    'location' => [
                        'latLng' => [
                            'latitude' => $destination['lat'],
                            'longitude' => $destination['lng'],
                        ]
                    ]
                ],
                'travelMode' => self::TRAVEL_MODE,
                'routingPreference' => self::ROUTING_PREFERENCE,
            ]);

            if (!$response->successful()) {
                Log::error('Google Routes API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'origin' => $origin,
                    'destination' => $destination,
                ]);

                return [
                    'success' => false,
                    'polyline' => null,
                    'distance_meters' => null,
                    'duration' => null,
                    'distance_km' => null,
                    'error' => 'Route service unavailable',
                ];
            }

            $data = $response->json();
            $route = $data['routes'][0] ?? null;

            if (!$route) {
                Log::warning('No routes found in response', ['data' => $data]);
                return [
                    'success' => false,
                    'polyline' => null,
                    'distance_meters' => null,
                    'duration' => null,
                    'distance_km' => null,
                    'error' => 'No route found',
                ];
            }

            // Extract route data
            $distanceMeters = $route['distanceMeters'] ?? 0;
            $duration = $route['duration'] ?? null;
            $polyline = $route['polyline']['encodedPolyline'] ?? null;

            // Prepare response
            $routeData = [
                'polyline' => $polyline,
                'distance_meters' => $distanceMeters,
                'duration' => $duration,
                'distance_km' => round($distanceMeters / 1000, 2),
            ];

            // Cache the result
            Cache::put($cacheKey, $routeData, now()->addMinutes(self::CACHE_TTL_MINUTES));

            Log::info('Route computed successfully', [
                'distance_meters' => $distanceMeters,
                'duration' => $duration,
                'cache_key' => $cacheKey,
            ]);

            return array_merge(['success' => true], $routeData);
        } catch (\Exception $e) {
            Log::error('GoogleRouteService Error', [
                'message' => $e->getMessage(),
                'origin' => $origin,
                'destination' => $destination,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'polyline' => null,
                'distance_meters' => null,
                'duration' => null,
                'distance_km' => null,
                'error' => 'Route computation failed',
            ];
        }
    }

    /**
     * Compute route for trip distance estimation
     * Used by fare calculation and matching services
     *
     * @param array $origin
     * @param array $destination
     * @return int Distance in meters (0 if failed)
     */
    public function getDistanceMeters(array $origin, array $destination): int
    {
        $route = $this->computeRoute($origin, $destination);
        return $route['distance_meters'] ?? 0;
    }

    /**
     * Compute route for trip duration estimation
     *
     * @param array $origin
     * @param array $destination
     * @return string|null Duration string (e.g., "900s")
     */
    public function getDuration(array $origin, array $destination): ?string
    {
        $route = $this->computeRoute($origin, $destination);
        return $route['duration'] ?? null;
    }

    /**
     * Compute route for polyline visualization (Flutter maps)
     *
     * @param array $origin
     * @param array $destination
     * @return string|null Encoded polyline string
     */
    public function getPolyline(array $origin, array $destination): ?string
    {
        $route = $this->computeRoute($origin, $destination);
        return $route['polyline'] ?? null;
    }

    /**
     * Generate cache key for route
     *
     * @param array $origin
     * @param array $destination
     * @return string
     */
    private function generateCacheKey(array $origin, array $destination): string
    {
        // Round coordinates to 4 decimals for cache key consistency
        $originKey = round($origin['lat'], 4) . ',' . round($origin['lng'], 4);
        $destKey = round($destination['lat'], 4) . ',' . round($destination['lng'], 4);
        return 'route:' . hash('md5', $originKey . '|' . $destKey);
    }

    /**
     * Clear cache for specific route or all routes
     *
     * @param array|null $origin
     * @param array|null $destination
     * @return void
     */
    public function clearCache(?array $origin = null, ?array $destination = null): void
    {
        if ($origin && $destination) {
            $cacheKey = $this->generateCacheKey($origin, $destination);
            Cache::forget($cacheKey);
            Log::info('Route cache cleared', ['cache_key' => $cacheKey]);
        } else {
            // Clear all route caches
            Cache::flush(); // Warning: this clears ALL cache
            Log::warning('All cache flushed');
        }
    }
}
