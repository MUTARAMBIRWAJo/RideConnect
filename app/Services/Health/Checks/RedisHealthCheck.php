<?php

namespace App\Services\Health\Checks;

use Illuminate\Support\Facades\Redis;

class RedisHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        $timeoutMs = (int) config('health.timeouts.redis_ms', 2000);

        return \App\Services\HealthCheckService::timed(function () {
            try {
                $redis = Redis::connection();
                $redis->ping();

                return [
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Redis connection healthy',
                    'details' => [],
                ];
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Redis connection failed: ' . $e->getMessage(),
                    'details' => [],
                ];
            }
        }, $timeoutMs);
    }
}
