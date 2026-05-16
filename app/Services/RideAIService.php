<?php

namespace App\Services;

use App\Services\Ml\DemandHeuristicModelV1;
use App\Services\Ml\MlPredictionLogger;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RideAIService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MlPredictionLogger $predictionLogger,
        private readonly DemandHeuristicModelV1 $demandModel,
    ) {}

    public function matchDriver(array $payload): array
    {
        $request = [
            'pickup_lat' => (float) ($payload['lat'] ?? $payload['pickup_lat'] ?? 0),
            'pickup_lng' => (float) ($payload['lng'] ?? $payload['pickup_lng'] ?? 0),
            'ride_type' => (string) ($payload['ride_type'] ?? 'standard'),
            'traffic_level' => $this->normalizeTrafficLevel($payload['traffic_level'] ?? null),
            'max_results' => (int) ($payload['max_results'] ?? 5),
        ];

        return $this->postJson('/match-driver', $request, 'match_driver', $payload);
    }

    public function predictETA(array $payload): array
    {
        $distanceKm = isset($payload['distance_km'])
            ? (float) $payload['distance_km']
            : $this->haversineDistanceKm(
                (float) ($payload['origin_lat'] ?? 0),
                (float) ($payload['origin_lng'] ?? 0),
                (float) ($payload['destination_lat'] ?? 0),
                (float) ($payload['destination_lng'] ?? 0),
            );

        $request = [
            'distance_km' => max(0.1, $distanceKm),
            'traffic_level' => $this->normalizeTrafficLevel($payload['traffic_level'] ?? null),
            'road_type' => (string) ($payload['road_type'] ?? 'main_road'),
            'weather' => (string) ($payload['weather'] ?? 'clear'),
            'hour' => (int) ($payload['time_of_day'] ?? now()->hour),
            'day_of_week' => (int) ($payload['day_of_week'] ?? now()->dayOfWeek),
        ];

        return $this->postJson('/estimate-arrival', $request, 'eta_prediction', $payload);
    }

    public function predictDemand(array $payload): array
    {
        $request = $this->demandModel->payload($payload);

        return $this->postJson(DemandHeuristicModelV1::ENDPOINT, $request, DemandHeuristicModelV1::MODEL_NAME);
    }

    public function calculateSurge(array $payload): array
    {
        $request = [
            'distance_km' => max(0.1, (float) ($payload['distance'] ?? 1.0)),
            'demand_level' => $this->normalizeDemandLevel($payload['demand_density'] ?? null),
            'traffic_level' => $this->normalizeTrafficLevel($payload['traffic_level'] ?? null),
            'ride_type' => (string) ($payload['ride_type'] ?? 'standard'),
        ];

        $result = $this->postJson('/predict-price', $request, 'surge_pricing', $payload);

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $price = (float) Arr::get($result, 'data.recommended_price', 0);
        $baseline = max(1.0, (float) ($payload['distance'] ?? 1.0) * 300);

        $result['data'] = [
            'recommended_price' => $price,
            'currency' => Arr::get($result, 'data.currency', 'RWF'),
            'surge_multiplier' => round(max(1.0, $price / $baseline), 2),
            'model_used' => Arr::get($result, 'data.model_used', false),
            'cached' => Arr::get($result, 'data.cached', false),
        ];

        return $result;
    }

    public function triggerRetrain(array $payload = []): array
    {
        $request = [
            'models' => array_values(array_filter((array) ($payload['models'] ?? []))),
        ];

        return $this->postJson('/retrain', $request, 'retraining');
    }

    public function optimizeRoute(array $payload): array
    {
        return $this->postJson('/optimize-route', $payload, 'route_optimization');
    }

    public function analyzeDriver(array $payload): array
    {
        return $this->postJson('/analyze-driver', $payload, 'driver_behavior');
    }

    public function detectFareAnomaly(array $payload): array
    {
        return $this->postJson('/detect-fare-anomaly', $payload, 'fare_anomaly');
    }

    public function driverRedistribution(array $query = []): array
    {
        return $this->getJson('/ai/driver-redistribution', $query, 'driver_redistribution');
    }

    public function routeMonitor(array $payload): array
    {
        return $this->postJson('/ai/route-monitor', $payload, 'route_monitor');
    }

    public function driverIdle(array $query = []): array
    {
        return $this->getJson('/ai/driver-idle', $query, 'driver_idle');
    }

    public function cancellationAnomalies(array $query = []): array
    {
        return $this->getJson('/ai/cancellation-anomalies', $query, 'cancellation_anomalies');
    }

    public function systemHealth(): array
    {
        return $this->getJson('/analytics/system-health', [], 'ai_system_health');
    }

    private function postJson(string $uri, array $payload, string $predictionType, ?array $originalPayload = null): array
    {
        $startedAt = microtime(true);
        $response = $this->client()->post($uri, $payload);

        return $this->normalizeResponse(
            $uri,
            $response,
            $originalPayload ?? $payload,
            $predictionType,
            $this->toResponseTimeMs($startedAt)
        );
    }

    private function getJson(string $uri, array $query, string $predictionType): array
    {
        $startedAt = microtime(true);
        $response = $this->client()->get($uri, $query);

        return $this->normalizeResponse($uri, $response, $query, $predictionType, $this->toResponseTimeMs($startedAt));
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.ride_ai.base_url', 'http://ai-service:8001'), '/');
        $apiKey = (string) config('services.ride_ai.api_key', '');
        $timeout = (int) config('services.ride_ai.timeout', 5);

        $client = $this->http
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->retry(2, 200);

        if ($apiKey !== '') {
            $client = $client->withHeader('X-API-Key', $apiKey);
        }

        return $client;
    }

    private function normalizeResponse(
        string $uri,
        Response $response,
        array $payload,
        string $predictionType,
        int $responseTimeMs,
    ): array {
        if ($response->successful()) {
            $result = [
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json(),
            ];

            $this->logPrediction($predictionType, $uri, $payload, $result['data'], true, $responseTimeMs);

            return $result;
        }

        Log::warning('RideAIService request failed', [
            'uri' => $uri,
            'status' => $response->status(),
            'response' => Arr::wrap($response->json()),
            'payload_keys' => array_keys($payload),
        ]);

        $result = [
            'success' => false,
            'status' => $response->status(),
            'error' => $response->json('detail') ?? $response->body(),
        ];

        $this->logPrediction($predictionType, $uri, $payload, ['error' => $result['error']], false, $responseTimeMs);

        return $result;
    }

    private function normalizeTrafficLevel(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 3;
        }

        $numeric = (float) $value;

        if ($numeric <= 1) {
            return (int) max(1, min(5, round($numeric * 4 + 1)));
        }

        return (int) max(1, min(5, round($numeric)));
    }

    private function normalizeDemandLevel(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 3;
        }

        $numeric = (float) $value;

        if ($numeric <= 1) {
            return (int) max(1, min(5, round($numeric * 4 + 1)));
        }

        return (int) max(1, min(5, round($numeric)));
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        if ($lat1 === 0.0 && $lng1 === 0.0 && $lat2 === 0.0 && $lng2 === 0.0) {
            return 1.0;
        }

        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function toResponseTimeMs(float $startedAt): int
    {
        return (int) max(1, round((microtime(true) - $startedAt) * 1000));
    }

    private function logPrediction(
        string $predictionType,
        string $uri,
        array $requestPayload,
        mixed $responsePayload,
        bool $success,
        int $responseTimeMs,
    ): void {
        $this->predictionLogger->log(
            modelName: $predictionType,
            modelVersion: $predictionType === DemandHeuristicModelV1::MODEL_NAME ? DemandHeuristicModelV1::MODEL_VERSION : null,
            endpoint: $uri,
            inputPayload: $requestPayload,
            outputPayload: $responsePayload,
            latencyMs: $responseTimeMs,
            tripId: isset($requestPayload['trip_id']) ? (int) $requestPayload['trip_id'] : null,
        );

        if (! Schema::hasTable('ai_prediction_logs')) {
            return;
        }

        try {
            DB::table('ai_prediction_logs')->insert([
                'prediction_type' => $predictionType,
                'trip_id' => isset($requestPayload['trip_id']) ? (int) $requestPayload['trip_id'] : null,
                'request_payload' => json_encode($requestPayload, JSON_THROW_ON_ERROR),
                'response_payload' => json_encode($responsePayload, JSON_THROW_ON_ERROR),
                'response_time_ms' => max(1, min(65000, $responseTimeMs)),
                'success' => $success,
                'requested_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::debug('RideAIService prediction log write failed', ['error' => $e->getMessage()]);
        }
    }
}
