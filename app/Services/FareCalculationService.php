<?php

namespace App\Services;

use App\Models\MotorcycleTrip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FareCalculationService - Route-based pricing engine
 *
 * Calculates trip fares using:
 * - Distance from Google Routes API
 * - Duration (ETA)
 * - Vehicle type
 * - Dynamic pricing rules
 */
class FareCalculationService
{
    private GoogleRouteService $routeService;

    /**
     * Pricing configuration by vehicle type
     */
    private const PRICING_RULES = [
        'MOTORCYCLE' => [
            'base_fare' => 500,      // RWF
            'per_km' => 300,         // RWF/km
            'per_minute' => 20,      // RWF/minute
        ],
        'BUS' => [
            'base_fare' => 300,      // RWF
            'per_km' => 200,         // RWF/km
            'per_minute' => 15,      // RWF/minute
        ],
        'PRIVATE_VEHICLE' => [
            'base_fare' => 1000,     // RWF
            'per_km' => 500,         // RWF/km
            'per_minute' => 30,      // RWF/minute
        ],
    ];

    private const CACHE_TTL = 300; // 5 minutes
    private const CURRENCY = 'RWF';

    public function __construct(GoogleRouteService $routeService)
    {
        $this->routeService = $routeService;
    }

    /**
     * Calculate fare for a trip
     *
     * @param float $pickupLat
     * @param float $pickupLng
     * @param float $dropoffLat
     * @param float $dropoffLng
     * @param string $vehicleType MOTORCYCLE, BUS, or PRIVATE_VEHICLE
     * @return array{
     *   distance_km: float,
     *   duration_minutes: int,
     *   vehicle_type: string,
     *   fare_breakdown: array{
     *     base_fare: int,
     *     distance_cost: int,
     *     time_cost: int,
     *     total: int
     *   },
     *   currency: string,
     *   cached: bool
     * }|null
     */
    public function calculateFare(
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng,
        string $vehicleType = 'MOTORCYCLE'
    ): ?array {
        try {
            // Validate vehicle type
            if (!isset(self::PRICING_RULES[$vehicleType])) {
                Log::warning('FareCalculationService: Unknown vehicle type', [
                    'vehicle_type' => $vehicleType,
                ]);
                $vehicleType = 'MOTORCYCLE'; // fallback
            }

            // Check cache first
            $cacheKey = $this->generateCacheKey($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $vehicleType);
            $cached = Cache::get($cacheKey);

            if ($cached) {
                Log::info('FareCalculationService: Using cached fare', [
                    'cache_key' => $cacheKey,
                    'vehicle_type' => $vehicleType,
                ]);
                return array_merge($cached, ['cached' => true]);
            }

            // Get route data from Google Routes API
            $routeData = $this->routeService->computeRoute(
                ['lat' => $pickupLat, 'lng' => $pickupLng],
                ['lat' => $dropoffLat, 'lng' => $dropoffLng]
            );

            if (!$routeData || !$routeData['success']) {
                Log::error('FareCalculationService: Failed to get route data', [
                    'pickup' => ['lat' => $pickupLat, 'lng' => $pickupLng],
                    'dropoff' => ['lat' => $dropoffLat, 'lng' => $dropoffLng],
                ]);
                return null;
            }

            // Extract distance and duration
            $distanceMeters = $routeData['distance_meters'] ?? 0;
            $distanceKm = round($distanceMeters / 1000, 1);

            // Parse duration (format: "600s")
            $durationStr = $routeData['duration'] ?? '0s';
            $durationSeconds = (int) filter_var($durationStr, FILTER_SANITIZE_NUMBER_INT);
            $durationMinutes = (int) ceil($durationSeconds / 60);

            // Calculate fare
            $fare = $this->calculateFareAmount($distanceKm, $durationMinutes, $vehicleType);

            // Build response
            $response = [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'vehicle_type' => $vehicleType,
                'fare_breakdown' => $fare,
                'currency' => self::CURRENCY,
                'cached' => false,
            ];

            // Cache the result
            Cache::put($cacheKey, [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'vehicle_type' => $vehicleType,
                'fare_breakdown' => $fare,
                'currency' => self::CURRENCY,
            ], self::CACHE_TTL);

            Log::info('FareCalculationService: Calculated fare', [
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'vehicle_type' => $vehicleType,
                'total_fare' => $fare['total'],
                'cache_ttl' => self::CACHE_TTL,
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('FareCalculationService: Exception during calculation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Calculate fare amount breakdown
     */
    private function calculateFareAmount(float $distanceKm, int $durationMinutes, string $vehicleType): array
    {
        $rules = self::PRICING_RULES[$vehicleType];

        $baseFare = $rules['base_fare'];
        $distanceCost = (int) ($distanceKm * $rules['per_km']);
        $timeCost = (int) ($durationMinutes * $rules['per_minute']);
        $totalFare = $baseFare + $distanceCost + $timeCost;

        return [
            'base_fare' => $baseFare,
            'distance_cost' => $distanceCost,
            'time_cost' => $timeCost,
            'total' => $totalFare,
        ];
    }

    /**
     * Generate cache key from coordinates
     */
    private function generateCacheKey(
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng,
        string $vehicleType
    ): string {
        // Round to 4 decimals for cache key
        $key = md5(sprintf(
            'fare_%s_%f_%f_%f_%f',
            $vehicleType,
            round($pickupLat, 4),
            round($pickupLng, 4),
            round($dropoffLat, 4),
            round($dropoffLng, 4)
        ));

        return $key;
    }

    /**
     * Get pricing rules for a vehicle type
     */
    public function getPricingRules(string $vehicleType = null): array
    {
        if ($vehicleType && isset(self::PRICING_RULES[$vehicleType])) {
            return self::PRICING_RULES[$vehicleType];
        }

        return self::PRICING_RULES;
    }

    /**
     * Clear fare cache (useful for testing or price updates)
     */
    public function clearCache(
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?string $vehicleType = null
    ): bool {
        if ($pickupLat && $pickupLng && $dropoffLat && $dropoffLng && $vehicleType) {
            $cacheKey = $this->generateCacheKey($pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $vehicleType);
            return Cache::forget($cacheKey);
        }

        // Clear all fare caches
        Cache::flush();
        return true;
    }
}
