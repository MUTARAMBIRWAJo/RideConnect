<?php

namespace App\Services\Health\Checks;

use Illuminate\Support\Facades\Http;

class MlServiceHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        $timeoutMs = (int) config('health.timeouts.ml_service_ms', 5000);
        $timeoutSeconds = max(1, (int) ceil($timeoutMs / 1000));

        return \App\Services\HealthCheckService::timed(function () use ($extended, $timeoutSeconds) {
            $baseUrl = rtrim((string) config('health.ml_service.url'), '/');
            $healthPath = (string) config('health.ml_service.health_path', '/health');
            $healthUrl = $baseUrl.$healthPath;

            $started = microtime(true);
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->get($healthUrl);
            $healthLatencyMs = (int) round((microtime(true) - $started) * 1000);

            $details = [
                'url' => $healthUrl,
                'http_status' => $response->status(),
                'response_time_ms' => $healthLatencyMs,
                'body' => $response->json() ?? $response->body(),
            ];

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'ML service health endpoint unreachable',
                    'details' => $details,
                ];
            }

            if ($extended) {
                $details['prediction_probe'] = $this->probePredictionEndpoint($baseUrl, $timeoutSeconds);
            }

            return [
                'ok' => true,
                'status' => 'ok',
                'message' => 'ML service reachable',
                'details' => $details,
            ];
        }, $timeoutMs);
    }

    /**
     * @return array<string, mixed>
     */
    private function probePredictionEndpoint(string $baseUrl, int $timeoutSeconds): array
    {
        $path = (string) config('health.ml_service.prediction_probe_path', '/rank-drivers');
        $url = $baseUrl.$path;

        $started = microtime(true);

        try {
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'trip_id' => 0,
                    'transport_type' => 'moto',
                    'pickup_lat' => 0,
                    'pickup_lng' => 0,
                    'candidates' => [],
                ]);

            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return [
                'url' => $url,
                'accessible' => in_array($response->status(), [200, 422], true),
                'http_status' => $response->status(),
                'response_time_ms' => $latencyMs,
                'note' => '422 is acceptable for empty candidate probe',
            ];
        } catch (\Throwable $exception) {
            return [
                'url' => $url,
                'accessible' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}
