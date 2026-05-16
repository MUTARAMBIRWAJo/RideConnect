<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ML Service Diagnostics Controller
 *
 * Test endpoints to verify ML service integration and test Demand Prediction API
 *
 * Routes:
 * GET  /api/diagnostics/ml-health           - Check ML service health
 * GET  /api/diagnostics/ml-test-demand      - Test demand prediction endpoint
 * GET  /api/diagnostics/ml-test-matching    - Test matching endpoint
 * GET  /api/diagnostics/config              - Show ML service configuration
 */
class MlServiceDiagnosticsController extends Controller
{
    public function __construct(private readonly AiPredictionService $aiService) {}

    /**
     * Check ML Service Health
     *
     * GET /api/diagnostics/ml-health
     */
    public function mlHealth(): JsonResponse
    {
        Log::info('Testing ML service health...');

        try {
            $baseUrl = config('services.ml_service.url')
                ?: config('services.ai_service.url');

            Log::info("Connecting to ML service at: {$baseUrl}");

            $response = Http::timeout(5)
                ->connectTimeout(5)
                ->get("{$baseUrl}/health");

            if ($response->successful()) {
                $data = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => 'ML Service is healthy',
                    'status' => 'operational',
                    'service_url' => $baseUrl,
                    'response_code' => $response->status(),
                    'data' => $data,
                    'timestamp' => now()->toIso8601String(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'ML Service returned non-success status',
                    'status' => 'error',
                    'service_url' => $baseUrl,
                    'response_code' => $response->status(),
                    'error' => $response->json('detail') ?? $response->body(),
                    'timestamp' => now()->toIso8601String(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            Log::error('ML service health check failed', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to ML service',
                'status' => 'unavailable',
                'service_url' => config('services.ml_service.url'),
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }

    /**
     * Test Demand Prediction Endpoint
     *
     * GET /api/diagnostics/ml-test-demand
     *
     * Query parameters (optional):
     * - latitude: float (default: -1.9441)
     * - longitude: float (default: 30.0619)
     * - hour: int 0-23 (default: current hour)
     * - day_of_week: int 0-6 (default: current day)
     */
    public function testDemandPrediction(): JsonResponse
    {
        Log::info('Testing ML service demand prediction...');

        try {
            $payload = [
                'latitude' => request('latitude', -1.9441),
                'longitude' => request('longitude', 30.0619),
                'hour' => request('hour', (int) now('Africa/Kigali')->format('H')),
                'day_of_week' => request('day_of_week', (int) now('Africa/Kigali')->format('w')),
            ];

            Log::info('Demand prediction request payload', $payload);

            $response = $this->aiService->predictDemand($payload);

            if (isset($response['demand_level']) && ! isset($response['error'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Demand prediction retrieved successfully',
                    'request' => $payload,
                    'response' => $response,
                    'interpretation' => $this->interpretDemandPrediction($response),
                    'timestamp' => now()->toIso8601String(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get demand prediction',
                    'request' => $payload,
                    'response' => $response,
                    'error' => $response['error'] ?? 'Unknown error',
                    'timestamp' => now()->toIso8601String(),
                ], 502);
            }
        } catch (\Exception $e) {
            Log::error('Demand prediction test failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error testing demand prediction',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * Test Matching Endpoint
     *
     * GET /api/diagnostics/ml-test-matching
     */
    public function testMatchingPrediction(): JsonResponse
    {
        Log::info('Testing ML service matching prediction...');

        try {
            $payload = [
                'ride_request' => [
                    'pickup_latitude' => -1.9441,
                    'pickup_longitude' => 30.0619,
                    'destination_latitude' => -1.9536,
                    'destination_longitude' => 30.1044,
                    'requested_vehicle_type' => 'car',
                    'required_seats' => 3,
                ],
                'candidate_drivers' => [
                    [
                        'driver_id' => 1,
                        'distance_km' => 1.2,
                        'driver_rating' => 4.8,
                        'acceptance_rate' => 92,
                        'cancellation_rate' => 2,
                        'behavior_score' => 88,
                        'available_seats' => 4,
                        'traffic_level' => 0.3,
                        'direction_similarity' => 0.9,
                    ],
                    [
                        'driver_id' => 2,
                        'distance_km' => 2.1,
                        'driver_rating' => 4.4,
                        'acceptance_rate' => 87,
                        'cancellation_rate' => 4,
                        'behavior_score' => 80,
                        'available_seats' => 4,
                        'traffic_level' => 0.2,
                        'direction_similarity' => 0.7,
                    ],
                ],
            ];

            Log::info('Matching prediction request', ['drivers_count' => count($payload['candidate_drivers'])]);

            $response = $this->aiService->matchDriver($payload);

            if (isset($response['best_driver']) && ! isset($response['error'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Driver matching completed successfully',
                    'best_driver' => $response['best_driver'],
                    'ranked_drivers' => $response['ranked_drivers'] ?? [],
                    'timestamp' => now()->toIso8601String(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to perform driver matching',
                    'response' => $response,
                    'error' => $response['error'] ?? 'Unknown error',
                    'timestamp' => now()->toIso8601String(),
                ], 502);
            }
        } catch (\Exception $e) {
            Log::error('Matching prediction test failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error testing matching prediction',
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * Show ML Service Configuration
     *
     * GET /api/diagnostics/config
     */
    public function showConfig(): JsonResponse
    {
        return response()->json([
            'ml_service' => [
                'url' => config('services.ml_service.url'),
                'api_key_set' => ! empty(config('services.ml_service.api_key')),
                'timeout' => config('services.ml_service.timeout'),
            ],
            'ai_service' => [
                'url' => config('services.ai_service.url'),
                'api_key_set' => ! empty(config('services.ai_service.key')),
                'timeout' => config('services.ai_service.timeout'),
            ],
            'app_environment' => config('app.env'),
            'app_debug' => config('app.debug'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Interpret demand prediction response
     */
    private function interpretDemandPrediction(array $prediction): array
    {
        $demand_level = $prediction['demand_level'] ?? 0;
        $wait_time = $prediction['expected_wait_time_minutes'] ?? 0;
        $confidence = $prediction['confidence'] ?? 0;

        $demand_description = match (true) {
            $demand_level > 0.75 => 'Very High',
            $demand_level > 0.5 => 'High',
            $demand_level > 0.25 => 'Moderate',
            default => 'Low',
        };

        $wait_description = match (true) {
            $wait_time > 15 => 'Long wait expected',
            $wait_time > 8 => 'Moderate wait',
            default => 'Short wait',
        };

        $confidence_description = match (true) {
            $confidence > 0.8 => 'Very High',
            $confidence > 0.6 => 'Good',
            $confidence > 0.4 => 'Moderate',
            default => 'Low',
        };

        return [
            'demand' => $demand_description,
            'wait_time' => $wait_description,
            'confidence' => $confidence_description,
            'recommendation' => $this->getRecommendation($demand_level, $wait_time),
        ];
    }

    /**
     * Get operational recommendation based on prediction
     */
    private function getRecommendation(float $demand, float $wait_time): string
    {
        if ($demand > 0.75 && $wait_time > 10) {
            return 'High demand with long waits - Consider surge pricing or driver incentives';
        } elseif ($demand > 0.5) {
            return 'Moderate-to-high demand - Monitor driver availability';
        } elseif ($demand < 0.25) {
            return 'Low demand - Optimize driver incentives to maintain supply';
        } else {
            return 'Balanced demand and supply - Normal operations';
        }
    }
}
