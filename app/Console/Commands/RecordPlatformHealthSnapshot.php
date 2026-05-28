<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecordPlatformHealthSnapshot extends Command
{
    protected $signature = 'health:record-platform-snapshot';

    protected $description = 'Persist a platform health snapshot for dashboard metrics';

    public function handle(): int
    {
        if (! Schema::hasTable('platform_health_snapshots')) {
            $this->warn('platform_health_snapshots table is missing; run migrations first.');

            return self::SUCCESS;
        }

        $databaseConnections = $this->getDatabaseConnections();
        $queuePending = $this->getQueuePendingCount();
        $cacheStatus = $this->getCacheStatus();
        $predictionLatency = $this->getAveragePredictionLatency();

        $checks = array_filter([
            'database' => $databaseConnections !== null ? 'ok' : null,
            'queue' => $queuePending !== null ? 'ok' : null,
            'cache' => $cacheStatus,
            'ai_predictions' => $predictionLatency !== null ? 'ok' : null,
        ]);

        $successfulChecks = count(array_filter($checks, static fn (?string $status): bool => $status === 'ok'));
        $totalChecks = count($checks);

        DB::table('platform_health_snapshots')->insert([
            'snapshot_type' => 'platform',
            'overall_status' => $successfulChecks === $totalChecks ? 'healthy' : 'degraded',
            'database_status' => $databaseConnections !== null ? 'ok' : 'unavailable',
            'queue_status' => $queuePending !== null ? 'ok' : 'unavailable',
            'cache_status' => $cacheStatus,
            'queue_pending' => $queuePending,
            'database_connections' => $databaseConnections,
            'ai_prediction_response_time_ms' => $predictionLatency,
            'successful_checks' => $successfulChecks,
            'total_checks' => $totalChecks,
            'metadata' => json_encode([
                'recorded_at' => now()->toISOString(),
                'checks' => $checks,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        $this->info('Recorded platform health snapshot.');

        return self::SUCCESS;
    }

    private function getDatabaseConnections(): ?int
    {
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return null;
            }

            $count = DB::selectOne('SELECT count(*) as count FROM pg_stat_activity WHERE datname = current_database()');

            return (int) ($count?->count ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }

    private function getQueuePendingCount(): ?int
    {
        if (! Schema::hasTable('jobs')) {
            return null;
        }

        return DB::table('jobs')->count();
    }

    private function getCacheStatus(): ?string
    {
        try {
            Cache::put('_platform_health_snapshot', true, 5);

            return Cache::get('_platform_health_snapshot') ? 'ok' : 'miss';
        } catch (\Throwable) {
            return null;
        }
    }

    private function getAveragePredictionLatency(): ?int
    {
        if (! Schema::hasTable('ai_prediction_logs') || ! Schema::hasColumn('ai_prediction_logs', 'response_time_ms')) {
            return null;
        }

        $result = DB::table('ai_prediction_logs')
            ->where('requested_at', '>=', now()->subHour())
            ->whereNotNull('response_time_ms')
            ->avg('response_time_ms');

        return $result !== null ? (int) round((float) $result) : null;
    }
}