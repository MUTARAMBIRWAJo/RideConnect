<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AiPredictionService
{
    private $client;

    private $apiKey;

    private $baseUrl;

    public function __construct()
    {
        // Use the deployed ML service URL first, but tolerate stale cached config values.
        $configuredUrl = config('services.ml_service.url')
            ?: config('services.ai_service.url', 'https://ml-service-j72g.onrender.com');

        if (empty($configuredUrl) || ! str_contains($configuredUrl, 'ml-service-j72g.onrender.com')) {
            $configuredUrl = 'https://ml-service-j72g.onrender.com';
        }

        $this->baseUrl = $configuredUrl;
        $this->apiKey = config('services.ml_service.api_key')
            ?: config('services.ai_service.key');

        $this->client = new Client([
            'base_uri' => rtrim($this->baseUrl, '/'),
            'timeout' => config('services.ml_service.timeout', 10.0),
            'verify' => false, // Allow self-signed certs for development
        ]);
    }

    /**
     * Predict price for a ride
     */
    public function predictPrice(array $payload)
    {
        return $this->post('/predict/fare', $payload);
    }

    /**
     * Predict ETA for a trip
     */
    public function predictEta(array $payload)
    {
        return $this->post('/predict/eta', $payload);
    }

    /**
     * Predict surge pricing
     */
    public function predictSurge(array $payload)
    {
        return $this->post('/predict/surge', $payload);
    }

    /**
     * Predict demand for a location
     *
     * Request format:
     * {
     *   "latitude": -1.9441,
     *   "longitude": 30.0619,
     *   "hour": 14,
     *   "day_of_week": 2
     * }
     *
     * Response format:
     * {
     *   "demand_level": 0.75,
     *   "expected_wait_time_minutes": 8,
     *   "confidence": 0.92
     * }
     */
    public function predictDemand(array $payload)
    {
        Log::debug('Calling ML service for demand prediction', [
            'url' => $this->baseUrl,
            'payload' => $payload,
        ]);

        $response = $this->post('/ml/predict-demand', [
            'latitude' => (float) ($payload['latitude'] ?? $payload['lat'] ?? -1.944),
            'longitude' => (float) ($payload['longitude'] ?? $payload['lng'] ?? 30.061),
            'hour' => (int) ($payload['hour'] ?? $payload['time_of_day'] ?? now()->hour),
            'day_of_week' => (int) ($payload['day_of_week'] ?? now()->dayOfWeek),
        ]);

        Log::debug('ML service demand prediction response', [
            'response' => $response,
        ]);

        return $response;
    }

    /**
     * Get demand hotspots
     */
    public function demandHotspots(array $payload)
    {
        return $this->post('/predict/demand-hotspots', $payload);
    }

    /**
     * Match driver to ride
     *
     * Request format:
     * {
     *   "ride_request": {...},
     *   "candidate_drivers": [...]
     * }
     */
    public function matchDriver(array $payload)
    {
        return $this->post('/predict/match-driver', $payload);
    }

    /**
     * Health check - verify ML service is running
     */
    public function health()
    {
        try {
            $response = $this->client->get('/health');
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info('ML service health check successful', $data);

            return [
                'success' => true,
                'status' => 'healthy',
                'data' => $data,
            ];
        } catch (RequestException $e) {
            Log::error('ML service health check failed', [
                'error' => $e->getMessage(),
                'url' => $this->baseUrl,
            ]);

            return [
                'success' => false,
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make a POST request to the ML service
     */
    private function post($uri, $payload)
    {
        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            if (! empty($this->apiKey)) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            Log::debug('ML Service POST Request', [
                'uri' => $uri,
                'baseUrl' => $this->baseUrl,
                'payload' => $payload,
            ]);

            $response = $this->client->post($uri, [
                'headers' => $headers,
                'json' => $payload,
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            Log::debug('ML Service POST Response', [
                'uri' => $uri,
                'status' => $response->getStatusCode(),
                'body' => $body,
            ]);

            return $body;
        } catch (RequestException $e) {
            $error_message = $e->getMessage();
            $status_code = $e->getResponse()?->getStatusCode() ?? 502;

            try {
                $error_body = json_decode($e->getResponse()?->getBody(), true);
                $error_message = $error_body['detail'] ?? $error_message;
            } catch (\Exception $parseError) {
                // Response body is not JSON
            }

            Log::error('ML Service error', [
                'uri' => $uri,
                'status' => $status_code,
                'error' => $error_message,
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $error_message,
                'status' => $status_code,
            ];
        }
    }
}
