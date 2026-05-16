<?php

namespace App\Services;

use App\Services\Ml\DemandHeuristicModelV1;
use App\Services\Ml\MlPredictionLogger;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class MlService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly MlPredictionLogger $predictionLogger,
        private readonly DemandHeuristicModelV1 $demandModel,
    ) {}

    public function predictFare(array $features): array
    {
        return $this->post('/ml/predict-fare', [
            'features' => $features,
        ], 'FareModel', null);
    }

    public function rankDrivers(array $features): array
    {
        return $this->post('/ml/rank-drivers', [
            'features' => $features,
        ], 'DriverRanker', null);
    }

    public function predictDemand(array $payload): array
    {
        return $this->post(
            DemandHeuristicModelV1::ENDPOINT,
            $this->demandModel->payload($payload),
            DemandHeuristicModelV1::MODEL_NAME,
            DemandHeuristicModelV1::MODEL_VERSION,
            $payload['trip_id'] ?? null,
        );
    }

    public function detectAnomaly(array $payload, ?int $tripId = null): array
    {
        return $this->post('/ml/detect-anomaly', $payload, 'BehaviorAnomalyDetector', null, $tripId);
    }

    public function health(): array
    {
        return $this->get('/ml/health');
    }

    public function reloadModels(): array
    {
        return $this->post('/ml/reload-models', [], 'MlServiceControl', null);
    }

    public function triggerRetrain(array $models = []): array
    {
        return $this->post('/retrain', [
            'models' => array_values(array_filter($models)),
        ], 'MlTrainingTrigger', null);
    }

    private function post(
        string $uri,
        array $payload,
        string $modelName,
        ?string $modelVersion,
        mixed $tripId = null,
    ): array {
        $startedAt = microtime(true);
        $response = $this->client()->post($uri, $payload);
        $result = $this->normalize($response);

        $this->predictionLogger->log(
            modelName: $modelName,
            modelVersion: $modelVersion,
            endpoint: $uri,
            inputPayload: $payload,
            outputPayload: $result['data'] ?? $result,
            latencyMs: $this->predictionLogger->latencyMs($startedAt),
            tripId: is_numeric($tripId) ? (int) $tripId : null,
        );

        return $result;
    }

    private function get(string $uri): array
    {
        $response = $this->client()->get($uri);

        return $this->normalize($response);
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim($this->resolveBaseUrl(), '/');
        $apiKey = (string) config('services.ml_service.api_key', '');
        $timeout = (int) config('services.ml_service.timeout', 10);

        $client = $this->http
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->retry(2, 200, throw: false);

        if ($apiKey !== '') {
            $client = $client->withHeader('X-API-Key', $apiKey);
        }

        return $client;
    }

    private function resolveBaseUrl(): string
    {
        $configuredUrl = (string) config('services.ml_service.url', '');

        if ($configuredUrl === '') {
            return 'https://ml-service-j72g.onrender.com';
        }

        return $configuredUrl;
    }

    private function normalize(Response $response): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        }

        return [
            'success' => false,
            'status' => $response->status(),
            'error' => $response->json('detail') ?? $response->body(),
            'data' => $response->json(),
        ];
    }
}
