<?php

namespace App\Services\Matching;

/**
 * Centralizes the dynamic search-radius schedule used by motor-vehicle matching.
 * Radius widens the longer a passenger has been waiting (or the more retries
 * have run), so nearby drivers are tried first and distant ones only as a
 * fallback.
 */
class RadiusExpansionService
{
    public const MAX_RADIUS_KM = 30.0;

    /**
     * Search radius (km) based on seconds elapsed since the request was made.
     *   0-15s = 5km, 15-30s = 8km, 30-45s = 12km, 45-60s = 20km, 60+s = 30km
     */
    public function radiusForElapsedSeconds(int $seconds): float
    {
        return match (true) {
            $seconds < 15 => 5.0,
            $seconds < 30 => 8.0,
            $seconds < 45 => 12.0,
            $seconds < 60 => 20.0,
            default => self::MAX_RADIUS_KM,
        };
    }

    /**
     * Search radius (km) for a given retry attempt (0-based).
     */
    public function radiusForRetry(int $retryCount): float
    {
        $schedule = [5.0, 8.0, 12.0, 20.0, self::MAX_RADIUS_KM];

        return $schedule[min(max($retryCount, 0), count($schedule) - 1)];
    }
}
