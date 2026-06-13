<?php

namespace App\Services\Location;

use Illuminate\Support\Facades\Log;

class DriverLocationValidator
{
    private const MAX_LATITUDE = 90.0;
    private const MIN_LATITUDE = -90.0;
    private const MAX_LONGITUDE = 180.0;
    private const MIN_LONGITUDE = -180.0;
    private const MAX_SPEED_KMH = 200.0; // Unrealistic speed for ground vehicles
    private const MAX_ACCURACY_METERS = 1000.0; // GPS accuracy threshold
    private const MAX_DISTANCE_JUMP_KM = 10.0; // Max distance between consecutive updates

    /**
     * Validate driver location coordinates
     */
    public function validate(float $latitude, float $longitude, ?float $accuracy = null): array
    {
        $errors = [];
        $warnings = [];

        // Coordinate range validation
        if ($latitude > self::MAX_LATITUDE || $latitude < self::MIN_LATITUDE) {
            $errors[] = "Invalid latitude: {$latitude}. Must be between " . self::MIN_LATITUDE . " and " . self::MAX_LATITUDE;
        }

        if ($longitude > self::MAX_LONGITUDE || $longitude < self::MIN_LONGITUDE) {
            $errors[] = "Invalid longitude: {$longitude}. Must be between " . self::MIN_LONGITUDE . " and " . self::MAX_LONGITUDE;
        }

        // Accuracy validation
        if ($accuracy !== null && $accuracy > self::MAX_ACCURACY_METERS) {
            $warnings[] = "Low GPS accuracy: {$accuracy}m. Threshold: " . self::MAX_ACCURACY_METERS . "m";
        }

        // Zero coordinates check
        if ($latitude === 0.0 && $longitude === 0.0) {
            $errors[] = "Invalid coordinates: (0, 0) - likely default/placeholder values";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate location update sequence (detect impossible movements)
     */
    public function validateSequence(
        float $prevLat,
        float $prevLng,
        ?float $prevTime,
        float $currLat,
        float $currLng,
        ?float $currSpeed = null
    ): array {
        $errors = [];
        $warnings = [];

        if ($prevTime === null) {
            return ['valid' => true, 'errors' => [], 'warnings' => []];
        }

        $timeDiffSeconds = now()->diffInSeconds(\Carbon\Carbon::parse($prevTime));
        
        if ($timeDiffSeconds <= 0) {
            $errors[] = "Invalid time sequence: current time is not after previous time";
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        // Calculate distance between points
        $distanceKm = $this->calculateDistance($prevLat, $prevLng, $currLat, $currLng);
        
        // Calculate implied speed
        $impliedSpeedKmh = ($distanceKm / $timeDiffSeconds) * 3600;

        // Speed validation
        if ($currSpeed !== null && $currSpeed > self::MAX_SPEED_KMH) {
            $errors[] = "Invalid speed: {$currSpeed} km/h exceeds maximum " . self::MAX_SPEED_KMH . " km/h";
        }

        if ($impliedSpeedKmh > self::MAX_SPEED_KMH) {
            $warnings[] = "Impossibly high movement speed: {$impliedSpeedKmh} km/h (distance: {$distanceKm}km, time: {$timeDiffSeconds}s)";
        }

        // Distance jump validation
        if ($distanceKm > self::MAX_DISTANCE_JUMP_KM) {
            $warnings[] = "Large location jump: {$distanceKm}km between updates";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'distance_km' => $distanceKm,
            'implied_speed_kmh' => $impliedSpeedKmh,
        ];
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Check if location is within Rwanda's bounding box
     */
    public function isWithinRwanda(float $latitude, float $longitude): bool
    {
        // Rwanda approximate bounding box
        $minLat = -2.84;
        $maxLat = -1.05;
        $minLng = 29.0;
        $maxLng = 30.9;

        return $latitude >= $minLat && $latitude <= $maxLat
            && $longitude >= $minLng && $longitude <= $maxLng;
    }

    /**
     * Validate location for trip context
     */
    public function validateForTrip(
        float $latitude,
        float $longitude,
        ?int $tripId = null
    ): array {
        $baseValidation = $this->validate($latitude, $longitude);
        
        if ($tripId) {
            // Add trip-specific validation if needed
            // For example, check if location is within reasonable distance of trip route
        }

        return $baseValidation;
    }
}
