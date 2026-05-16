<?php

/**
 * ML Prediction Service for Laravel integration.
 */

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MLPredictionService
 *
 * Integrates with the FastAPI ML microservice for driver matching,
 * demand prediction, and ETA estimation.
 *
 * Configuration:
 * - ML_SERVICE_URL: Base URL of the ML microservice (e.g., http://localhost:8000)
 * - ML_SERVICE_TIMEOUT: Request timeout in seconds (default: 30)
 * - ML_SERVICE_ENABLED: Enable/disable ML service integration (default: true)
 */
class MLPredictionService
{
    const SERVICE_NAME = 'ml-service';

    const HEALTH_ENDPOINT = '/health';

    const MATCH_DRIVER_ENDPOINT = '/predict/match-driver';

    const PREDICT_DEMAND_ENDPOINT = '/ml/predict-demand';

    const PREDICT_ETA_ENDPOINT = '/predict/eta';

    private string $baseUrl;

    private int $timeout;

    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.ml_service.url', 'http://localhost:8000');
        $this->timeout = config('services.ml_service.timeout', 30);
        $this->enabled = config('services.ml_service.enabled', true);
    }

    /**
     * Check ML service health
     */
    public function isHealthy(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->get($this->baseUrl.self::HEALTH_ENDPOINT);

            return $response->successful() && $response->json('status') === 'healthy';
        } catch (Exception $e) {
            Log::warning('ML service health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Match a driver to a ride request
     *
     * @param  array  $rideRequest  {
     *
     * @var float $pickup_latitude
     * @var float $pickup_longitude
     * @var float $destination_latitude
     * @var float $destination_longitude
     * @var string $requested_vehicle_type
     * @var int $required_seats
     *          }
     *
     * @param  array  $candidateDrivers  Array of candidate drivers with metrics
     * @return array {
     *
     * @var int $driver_id Best matching driver ID
     * @var float $score Best match score (0-1)
     * @var array $ranked_drivers All drivers ranked by score
     *            }
     *
     * @throws Exception
     */
    public function matchDriver(array $rideRequest, array $candidateDrivers): array
    {
        if (! $this->enabled) {
            throw new Exception('ML service is disabled');
        }

        try {
            $payload = [
                'ride_request' => $rideRequest,
                'candidate_drivers' => $candidateDrivers,
            ];

            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl.self::MATCH_DRIVER_ENDPOINT, $payload);

            if (! $response->successful()) {
                throw new Exception('ML service returned error: '.$response->body());
            }

            $data = $response->json();

            Log::info('Driver matching completed', [
                'best_driver_id' => $data['best_driver']['driver_id'],
                'score' => $data['best_driver']['score'],
                'candidates_count' => count($candidateDrivers),
            ]);

            return $data;

        } catch (Exception $e) {
            Log::error('Driver matching failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Predict ride demand for a location
     *
     * @param  int  $hour  Hour of day (0-23)
     * @param  int  $dayOfWeek  Day of week (0=Monday, 6=Sunday)
     * @return array {
     *
     * @var float $demand_level (0-1)
     * @var int $expected_wait_time_minutes
     * @var float $confidence
     *            }
     *
     * @throws Exception
     */
    public function predictDemand(
        float $latitude,
        float $longitude,
        int $hour,
        int $dayOfWeek
    ): array {
        if (! $this->enabled) {
            throw new Exception('ML service is disabled');
        }

        try {
            $payload = [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'hour' => $hour,
                'day_of_week' => $dayOfWeek,
            ];

            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl.self::PREDICT_DEMAND_ENDPOINT, $payload);

            if (! $response->successful()) {
                throw new Exception('ML service returned error: '.$response->body());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('Demand prediction failed', [
                'error' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
            throw $e;
        }
    }

    /**
     * Predict ETA for a route
     *
     * @param  float  $trafficLevel  Traffic level (0-1)
     * @param  float  $distanceKm  Distance in kilometers
     * @return array {
     *
     * @var float $estimated_time_minutes
     * @var float $distance_km
     * @var float $confidence
     *            }
     *
     * @throws Exception
     */
    public function predictETA(
        float $pickupLatitude,
        float $pickupLongitude,
        float $destinationLatitude,
        float $destinationLongitude,
        float $trafficLevel,
        float $distanceKm
    ): array {
        if (! $this->enabled) {
            throw new Exception('ML service is disabled');
        }

        try {
            $payload = [
                'pickup_latitude' => $pickupLatitude,
                'pickup_longitude' => $pickupLongitude,
                'destination_latitude' => $destinationLatitude,
                'destination_longitude' => $destinationLongitude,
                'traffic_level' => $trafficLevel,
                'distance_km' => $distanceKm,
            ];

            $response = Http::timeout($this->timeout)
                ->post($this->baseUrl.self::PREDICT_ETA_ENDPOINT, $payload);

            if (! $response->successful()) {
                throw new Exception('ML service returned error: '.$response->body());
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('ETA prediction failed', [
                'error' => $e->getMessage(),
                'distance_km' => $distanceKm,
            ]);
            throw $e;
        }
    }

    /**
     * Get service version and status
     */
    public function getServiceInfo(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->baseUrl.'/');

            return $response->successful() ? $response->json() : [];
        } catch (Exception $e) {
            Log::warning('Failed to get service info', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
