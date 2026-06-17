<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TfliteMatchingService
{
    private string $endpoint;

    private int $timeoutSeconds;

    public function __construct()
    {
        $this->endpoint = rtrim((string) config('services.tflite.endpoint'), '/');
        $this->timeoutSeconds = 5;
    }

    public function warmUp(): void
    {
        if (Cache::get('tflite_warmed')) {
            return;
        }

        try {
            Http::timeout(5)->get("{$this->endpoint}/health");
            Cache::put('tflite_warmed', true, now()->addMinutes(10));
        } catch (\Throwable) {
            // Warmup failure is non-fatal.
        }
    }

    public function rankDrivers(
        int $tripId,
        string $transportType,
        float $pickupLat,
        float $pickupLng,
        array $candidates
    ): array {
        $payload = [
            'trip_id' => $tripId,
            'transport_type' => $transportType,
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'candidates' => $candidates,
        ];

        $start = microtime(true);

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->retry(1, 500)
                ->post("{$this->endpoint}/rank-drivers", $payload);

            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                Log::error('[TFLite] Service returned error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'trip_id' => $tripId,
                ]);

                return $this->distanceFallback($candidates, $latencyMs);
            }

            $result = $response->json();
            $result['latency_ms'] = $latencyMs;

            Log::info('[TFLite] Ranked drivers', [
                'trip_id' => $tripId,
                'model_version' => $result['model_version'] ?? 'unknown',
                'latency_ms' => $latencyMs,
                'candidate_count' => count($candidates),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $start) * 1000);
            Log::error('[TFLite] Service unreachable', [
                'error' => $e->getMessage(),
                'trip_id' => $tripId,
                'endpoint' => $this->endpoint,
            ]);

            return $this->distanceFallback($candidates, $latencyMs);
        }
    }

    private function distanceFallback(array $candidates, int $latencyMs): array
    {
        usort($candidates, fn ($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return [
            'ranked_drivers' => array_map(function (array $candidate): array {
                $score = $candidate['distance_km'] > 0 ? round(1.0 / $candidate['distance_km'], 6) : 1.0;

                return [
                    'driver_id' => $candidate['driver_id'],
                    'score' => $score,
                    'score_breakdown' => array_merge($candidate, [
                        'fallback' => true,
                        'raw_score' => $score,
                    ]),
                ];
            }, $candidates),
            'model_version' => 'distance_fallback',
            'backend' => 'fallback',
            'latency_ms' => $latencyMs,
        ];
    }
}
