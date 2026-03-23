<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AiPredictionService
{
    private $client;
    private $apiKey;
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_service.url', 'https://rideconnect-ai.onrender.com');
        $this->apiKey = config('services.ai_service.key');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 10.0,
        ]);
    }


    public function predictPrice(array $payload)
    {
        return $this->post('/predict-price', $payload);
    }

    public function predictEta(array $payload)
    {
        return $this->post('/predict/eta', $payload);
    }

    public function predictSurge(array $payload)
    {
        return $this->post('/predict/surge-pricing', $payload);
    }

    public function predictDemand(array $payload)
    {
        return $this->post('/predict/demand', $payload);
    }

    public function demandHotspots(array $payload)
    {
        return $this->post('/predict/demand-hotspots', $payload);
    }

    public function matchDriver(array $payload)
    {
        return $this->post('/predict/match-driver', $payload);
    }

    private function post($uri, $payload)
    {
        try {
            $headers = [
                'Accept' => 'application/json',
            ];

            if (!empty($this->apiKey)) {
                $headers['X-API-Key'] = $this->apiKey;
            }

            $response = $this->client->post($uri, [
                'headers' => $headers,
                'json' => $payload,
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('AI Service error: ' . $e->getMessage());
            return null;
        }
    }
}
