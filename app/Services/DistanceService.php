<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DistanceService
{
    private const GOOGLE_DISTANCE_MATRIX_URL = 'https://maps.googleapis.com/maps/api/distancematrix/json';
    private const API_TIMEOUT = 10; // seconds
    private const EARTH_RADIUS_KM = 6371; // Earth radius in kilometers

    /**
     * Calculate distance between two geographic coordinates.
     *
     * Attempts distance calculation in this order:
     * 1. Google Distance Matrix API (preferred, includes traffic data)
     * 2. Haversine formula fallback (fast, no API calls)
     * 3. Throws exception if both methods fail
     *
     * @param  float  $originLat   Origin latitude
     * @param  float  $originLng   Origin longitude
     * @param  float  $destLat     Destination latitude
     * @param  float  $destLng     Destination longitude
     * @return array{distance_km: float, duration_minutes: int, status: string}
     *
     * @throws \Exception
     */
    public function calculateDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        // Input validation
        $this->validateCoordinates($originLat, $originLng, $destLat, $destLng);

        Log::info('Distance calculation initiated', [
            'origin' => ["lat" => $originLat, "lng" => $originLng],
            'destination' => ["lat" => $destLat, "lng" => $destLng],
        ]);

        // Step 1: Try Google Distance Matrix API
        $googleResult = $this->tryGoogleDistanceMatrix($originLat, $originLng, $destLat, $destLng);
        if ($googleResult !== null) {
            Log::info('Distance calculation successful via Google Distance Matrix API', [
                'distance_km' => $googleResult['distance_km'],
                'duration_minutes' => $googleResult['duration_minutes'],
            ]);
            return $googleResult;
        }

        // Step 2: Fall back to Haversine formula
        $haversineResult = $this->calculateHaversineDistance($originLat, $originLng, $destLat, $destLng);
        Log::info('Distance calculation via Haversine formula fallback', [
            'distance_km' => $haversineResult['distance_km'],
            'duration_minutes' => $haversineResult['duration_minutes'],
        ]);

        return $haversineResult;
    }

    /**
     * Validate geographic coordinates are within valid ranges.
     *
     * @param  float  $originLat
     * @param  float  $originLng
     * @param  float  $destLat
     * @param  float  $destLng
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private function validateCoordinates(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): void {
        if ($originLat < -90 || $originLat > 90 || $destLat < -90 || $destLat > 90) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90');
        }

        if ($originLng < -180 || $originLng > 180 || $destLng < -180 || $destLng > 180) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180');
        }
    }

    /**
     * Calculate distance using Google Distance Matrix API.
     *
     * Google Distance Matrix API provides accurate distance and duration
     * considering real routes and traffic conditions.
     *
     * @param  float  $originLat
     * @param  float  $originLng
     * @param  float  $destLat
     * @param  float  $destLng
     * @return array|null
     */
    private function tryGoogleDistanceMatrix(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): ?array {
        $apiKey = config('services.google_maps.key');

        if (empty($apiKey)) {
            Log::debug('Google Maps API key not configured, skipping Google Distance Matrix');
            return null;
        }

        try {
            Log::debug('Attempting Google Distance Matrix API call', [
                'origins' => "{$originLat},{$originLng}",
                'destinations' => "{$destLat},{$destLng}",
            ]);

            $response = Http::timeout(self::API_TIMEOUT)
                ->get(self::GOOGLE_DISTANCE_MATRIX_URL, [
                    'origins' => "{$originLat},{$originLng}",
                    'destinations' => "{$destLat},{$destLng}",
                    'key' => $apiKey,
                    'mode' => 'driving',
                    'units' => 'metric',
                ])
                ->json();

            Log::debug('Google Distance Matrix API response', [
                'status' => $response['status'] ?? 'unknown',
                'rows' => count($response['rows'] ?? []),
            ]);

            // Check overall response status
            if ($response['status'] !== 'OK') {
                Log::warning('Google Distance Matrix API error', [
                    'status' => $response['status'],
                    'error_message' => $response['error_message'] ?? null,
                ]);
                return null;
            }

            // Check element status (specific route status)
            if (empty($response['rows'][0]['elements'])) {
                Log::debug('Google Distance Matrix returned empty elements');
                return null;
            }

            $element = $response['rows'][0]['elements'][0];

            // Check element status
            if ($element['status'] !== 'OK') {
                Log::debug('Distance Matrix element error', [
                    'element_status' => $element['status'],
                ]);
                return null;
            }

            // Extract distance and duration
            $distanceMeters = $element['distance']['value'] ?? null;
            $durationSeconds = $element['duration']['value'] ?? null;

            if ($distanceMeters === null || $durationSeconds === null) {
                Log::debug('Missing distance or duration in response');
                return null;
            }

            $distanceKm = round($distanceMeters / 1000, 2);
            $durationMinutes = (int) ceil($durationSeconds / 60);

            return [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'status' => 'OK',
            ];
        } catch (\Exception $e) {
            Log::error('Google Distance Matrix exception', [
                'origins' => "{$originLat},{$originLng}",
                'destinations' => "{$destLat},{$destLng}",
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calculate distance using Haversine formula.
     *
     * The Haversine formula calculates the great-circle distance between
     * two points on a sphere given their latitudes and longitudes.
     *
     * Formula:
     * a = sin²(Δlat/2) + cos(lat₁) × cos(lat₂) × sin²(Δlng/2)
     * c = 2 × atan2(√a, √(1-a))
     * d = R × c
     *
     * @param  float  $originLat
     * @param  float  $originLng
     * @param  float  $destLat
     * @param  float  $destLng
     * @return array{distance_km: float, duration_minutes: int, status: string}
     */
    private function calculateHaversineDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        // Convert degrees to radians
        $latFrom = deg2rad($originLat);
        $lonFrom = deg2rad($originLng);
        $latTo = deg2rad($destLat);
        $lonTo = deg2rad($destLng);

        // Haversine formula
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) ** 2 +
            cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = self::EARTH_RADIUS_KM * $c;

        // Distance in kilometers
        $distanceKm = round($distance, 2);

        // Estimate duration assuming average speed of 40 km/h in urban area
        $estimatedSpeed = 40; // km/h
        $durationMinutes = (int) ceil(($distanceKm / $estimatedSpeed) * 60);

        Log::debug('Haversine calculation result', [
            'distance_km' => $distanceKm,
            'duration_minutes' => $durationMinutes,
        ]);

        return [
            'distance_km' => $distanceKm,
            'duration_minutes' => $durationMinutes,
            'status' => 'OK_FALLBACK',
        ];
    }

    /**
     * Calculate distance for multiple destination coordinates in batch.
     *
     * Useful for finding the closest bus among multiple options.
     * Returns destinations sorted by distance (closest first).
     *
     * @param  float  $originLat
     * @param  float  $originLng
     * @param  array  $destinations  Array of ['id' => mixed, 'lat' => float, 'lng' => float]
     * @return array  Array of destinations with calculated distances, sorted by distance
     *
     * @throws \Exception
     */
    public function calculateBatchDistances(
        float $originLat,
        float $originLng,
        array $destinations
    ): array {
        if (empty($destinations)) {
            return [];
        }

        Log::info('Batch distance calculation initiated', [
            'origin' => ["lat" => $originLat, "lng" => $originLng],
            'destination_count' => count($destinations),
        ]);

        $results = [];

        foreach ($destinations as $dest) {
            try {
                $distance = $this->calculateDistance(
                    $originLat,
                    $originLng,
                    $dest['lat'],
                    $dest['lng']
                );

                $results[] = array_merge($dest, $distance);
            } catch (\Exception $e) {
                Log::error('Failed to calculate distance for destination', [
                    'destination_id' => $dest['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Sort by distance (closest first)
        usort($results, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        Log::info('Batch distance calculation complete', [
            'total_destinations' => count($destinations),
            'successful' => count($results),
            'closest_distance_km' => $results[0]['distance_km'] ?? null,
        ]);

        return $results;
    }
}
