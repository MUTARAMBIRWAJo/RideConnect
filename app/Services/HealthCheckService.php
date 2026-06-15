<?php

namespace App\Services;

use App\Services\Health\Checks\ApplicationHealthCheck;
use App\Services\Health\Checks\DatabaseHealthCheck;
use App\Services\Health\Checks\FirebaseHealthCheck;
use App\Services\Health\Checks\MlServiceHealthCheck;
use App\Services\Health\Checks\QueueHealthCheck;
use App\Services\Health\Checks\RedisHealthCheck;
use App\Services\Health\Checks\StorageHealthCheck;
use Illuminate\Support\Arr;

class HealthCheckService
{
    public function __construct(
        private readonly DatabaseHealthCheck $database,
        private readonly RedisHealthCheck $redis,
        private readonly FirebaseHealthCheck $firebase,
        private readonly MlServiceHealthCheck $mlService,
        private readonly QueueHealthCheck $queue,
        private readonly StorageHealthCheck $storage,
        private readonly ApplicationHealthCheck $application,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function live(): array
    {
        return [
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, http_status: int}
     */
    public function ready(): array
    {
        $checks = $this->runCoreChecks(includeOptional: true);

        $databaseOk = (bool) ($checks['database']['ok'] ?? false);
        $redisOk    = (bool) ($checks['redis']['ok'] ?? false);
        $firebaseOk = (bool) ($checks['firebase']['ok'] ?? false);
        $mlOk       = (bool) ($checks['ml_service']['ok'] ?? false);
        $queueOk    = (bool) ($checks['queue']['ok'] ?? false);

        // Firestore is permanently disabled — RTDB-only architecture.
        // Firebase readiness is based on RTDB connectivity, not Firestore.
        $rtdbConfigured = (bool) ($checks['firebase']['details']['realtime_database_configured'] ?? false);

        $required = config('health.ready_requires', ['database', 'redis', 'queue']);
        $requiredOk = collect($required)->every(
            fn (string $name) => (bool) ($checks[$name]['ok'] ?? false)
        );

        $payload = [
            'status'    => $requiredOk ? 'ready' : 'not_ready',
            'database'  => $databaseOk,
            'redis'     => $redisOk,
            'firebase'  => $firebaseOk,
            'rtdb'      => $rtdbConfigured,
            'firestore' => 'disabled',  // Permanently disabled — RTDB-only architecture
            'ml_service' => $mlOk,
            'queue'     => $queueOk,
            'timestamp' => now()->toIso8601String(),
        ];

        return [
            'payload'     => $payload,
            'http_status' => $requiredOk ? 200 : 503,
        ];
    }

    /**
     * @return array{payload: array<string, mixed>, http_status: int}
     */
    public function full(): array
    {
        $checks = $this->runCoreChecks(includeOptional: true, includeExtended: true);
        $summary = $this->summarize($checks);

        $payload = [
            'status' => $summary['status'],
            'environment' => app()->environment(),
            'version' => config('app.version', '1.0.0'),
            'checks' => $checks,
            'summary' => $summary,
            'timestamp' => now()->toIso8601String(),
        ];

        return [
            'payload' => $payload,
            'http_status' => $summary['http_status'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function runCoreChecks(bool $includeOptional = true, bool $includeExtended = false): array
    {
        $checks = [
            'database' => $this->database->check($includeExtended),
            'redis' => $this->redis->check($includeExtended),
            'queue' => $this->queue->check($includeExtended),
            'application' => $this->application->check($includeExtended),
            'storage' => $this->storage->check($includeExtended),
        ];

        if ($includeOptional) {
            $checks['firebase'] = $this->firebase->check($includeExtended);
            $checks['ml_service'] = $this->mlService->check($includeExtended);
        }

        return $checks;
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     * @return array{status: string, http_status: int, ok_count: int, total: int, failed: list<string>}
     */
    public function summarize(array $checks): array
    {
        $failed = [];

        foreach ($checks as $name => $check) {
            if (! ($check['ok'] ?? false)) {
                $failed[] = $name;
            }
        }

        $total = count($checks);
        $okCount = $total - count($failed);

        $required = config('health.ready_requires', ['database', 'queue']);
        $requiredFailed = array_intersect($failed, $required);

        if ($requiredFailed !== []) {
            $status = 'unhealthy';
            $httpStatus = 503;
        } elseif ($failed !== []) {
            $status = 'degraded';
            $httpStatus = 200;
        } else {
            $status = 'healthy';
            $httpStatus = 200;
        }

        return [
            'status' => $status,
            'http_status' => $httpStatus,
            'ok_count' => $okCount,
            'total' => $total,
            'failed' => array_values($failed),
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public static function timed(callable $callback, int $timeoutMs): array
    {
        $started = microtime(true);

        try {
            $result = $callback();
            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            return array_merge($result, [
                'latency_ms' => $latencyMs,
                'timed_out' => $latencyMs > $timeoutMs,
            ]);
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => 'error',
                'message' => $exception->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'timed_out' => false,
            ];
        }
    }
}
