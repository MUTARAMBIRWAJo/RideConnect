<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class MlService
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function predictFare(array $features): array
    {
        return $this->post('/ml/predict-fare', [
            'features' => $features,
        ]);
    }

    public function rankDrivers(array $features): array
    {
        return $this->post('/ml/rank-drivers', [
            'features' => $features,
        ]);
    }

    public function predictDemand(array $features): array
    {
        return $this->post('/ml/predict-demand', [
            'features' => $features,
        ]);
    }

    public function health(): array
    {
        return $this->get('/ml/health');
    }

    public function reloadModels(): array
    {
        return $this->post('/ml/reload-models', []);
    }

    private function post(string $uri, array $payload): array
    {
        $response = $this->client()->post($uri, $payload);

        return $this->normalize($response);
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
