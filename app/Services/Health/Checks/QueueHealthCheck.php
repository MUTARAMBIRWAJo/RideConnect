<?php

namespace App\Services\Health\Checks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class QueueHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        $timeoutMs = (int) config('health.timeouts.queue_ms', 2000);
        $driver = (string) config('queue.default', 'sync');

        return \App\Services\HealthCheckService::timed(function () use ($extended, $driver) {
            $connection = config("queue.connections.{$driver}");

            if ($connection === null) {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => "Queue connection [{$driver}] is not configured",
                    'details' => ['driver' => $driver],
                ];
            }

            $details = [
                'driver' => $driver,
                'connection' => $connection['connection'] ?? $driver,
            ];

            if ($driver === 'database') {
                if (! Schema::hasTable('jobs')) {
                    return [
                        'ok' => false,
                        'status' => 'error',
                        'message' => 'Queue jobs table missing',
                        'details' => $details,
                    ];
                }

                $details['pending_jobs'] = (int) DB::table('jobs')->count();
            } else {
                Queue::connection($driver);
                $details['pending_jobs'] = null;
            }

            if ($extended && Schema::hasTable('failed_jobs')) {
                $details['failed_jobs'] = (int) DB::table('failed_jobs')->count();
            }

            $failedJobs = (int) ($details['failed_jobs'] ?? 0);
            $pendingJobs = (int) ($details['pending_jobs'] ?? 0);

            if ($failedJobs > 1000) {
                return [
                    'ok' => false,
                    'status' => 'degraded',
                    'message' => 'High failed job count',
                    'details' => $details,
                ];
            }

            return [
                'ok' => true,
                'status' => 'ok',
                'message' => 'Queue connection healthy',
                'details' => $details,
            ];
        }, $timeoutMs);
    }
}
