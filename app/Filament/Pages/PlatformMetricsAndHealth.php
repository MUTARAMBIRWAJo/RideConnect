<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformMetricsAndHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Platform Metrics';

    protected static ?string $navigationGroup = 'Information Hub';

    protected static ?int $navigationSort = 202;

    protected static ?string $title = 'Platform Health & Metrics';

    protected static string $view = 'filament.pages.platform-metrics-and-health';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT'], true);
    }

    public function getTitle(): string
    {
        return 'Platform Health & Metrics';
    }

    public static function getNavigationLabel(): string
    {
        return 'Platform Metrics';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-chart-bar';
    }

    /** @return array<string, mixed> */
    public function getSystemMetrics(): array
    {
        return [
            'uptime' => $this->calculateSystemUptime(),
            'api_response_time' => $this->getAverageApiResponseTime(),
            'database_connections' => $this->getDatabaseConnectionCount(),
            'queue_jobs_pending' => $this->getPendingQueueJobs(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'error_rate' => $this->getErrorRate(),
        ];
    }

    /** @return array<string, mixed> */
    public function getBusinessMetrics(): array
    {
        return [
            'total_rides_today' => $this->getTotalRidesToday(),
            'total_revenue' => $this->getTotalRevenueToday(),
            'active_drivers' => $this->getActiveDriversCount(),
            'active_passengers' => $this->getActivePassengersCount(),
            'average_rating' => $this->getAverageRating(),
            'completion_rate' => $this->getRideCompletionRate(),
        ];
    }

    /**
     * Calculate system uptime percentage
     */
    private function calculateSystemUptime(): ?float
    {
        if (Schema::hasTable('platform_health_snapshots')) {
            $snapshot = DB::table('platform_health_snapshots')
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('COALESCE(SUM(successful_checks), 0) as successful_checks')
                ->selectRaw('COALESCE(SUM(total_checks), 0) as total_checks')
                ->first();

            if (! $snapshot || (int) $snapshot->total_checks === 0) {
                return null;
            }

            return round(((int) $snapshot->successful_checks / (int) $snapshot->total_checks) * 100, 2);
        }

        return null;
    }

    /**
     * Get average API response time in milliseconds
     */
    private function getAverageApiResponseTime(): ?string
    {
        if (Schema::hasTable('ai_prediction_logs') && Schema::hasColumn('ai_prediction_logs', 'response_time_ms')) {
            $result = DB::table('ai_prediction_logs')
                ->where('requested_at', '>=', now()->subHours(1))
                ->whereNotNull('response_time_ms')
                ->avg('response_time_ms');

            if ($result !== null) {
                return (int) round((float) $result).'ms';
            }
        }

        return null;
    }

    /**
     * Get current database connection count
     */
    private function getDatabaseConnectionCount(): ?int
    {
        try {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $count = DB::selectOne('SELECT count(*) as count FROM pg_stat_activity WHERE datname = current_database()');

                return (int) ($count?->count ?? 0);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get count of pending queue jobs
     */
    private function getPendingQueueJobs(): int
    {
        if (Schema::hasTable('jobs')) {
            return DB::table('jobs')->count();
        }

        // Fallback to failed jobs table
        if (Schema::hasTable('failed_jobs')) {
            return DB::table('failed_jobs')->where('failed_at', '>=', now()->subHours(24))->count();
        }

        return 0;
    }

    /**
     * Get cache hit rate percentage
     */
    private function getCacheHitRate(): ?float
    {
        if (Schema::hasTable('platform_health_snapshots')) {
            $totalSnapshots = DB::table('platform_health_snapshots')
                ->where('created_at', '>=', now()->subHours(1))
                ->whereNotNull('cache_status')
                ->count();

            if ($totalSnapshots === 0) {
                return null;
            }

            $successful = DB::table('platform_health_snapshots')
                ->where('created_at', '>=', now()->subHours(1))
                ->where('cache_status', 'ok')
                ->count();

            return round(($successful / $totalSnapshots) * 100, 1);
        }

        return null;
    }

    /**
     * Get application error rate percentage
     */
    private function getErrorRate(): ?float
    {
        if (! Schema::hasTable('platform_health_snapshots')) {
            return null;
        }

        $totalSnapshots = DB::table('platform_health_snapshots')
            ->where('created_at', '>=', now()->subHours(24))
            ->whereNotNull('overall_status')
            ->count();

        if ($totalSnapshots === 0) {
            return null;
        }

        $failedSnapshots = DB::table('platform_health_snapshots')
            ->where('created_at', '>=', now()->subHours(24))
            ->where('overall_status', 'degraded')
            ->count();

        return round(($failedSnapshots / $totalSnapshots) * 100, 2);
    }

    /**
     * Get total number of rides completed today
     */
    private function getTotalRidesToday(): int
    {
        if (Schema::hasTable('rides')) {
            return DB::table('rides')
                ->where('created_at', '>=', today())
                ->where('status', 'COMPLETED')
                ->count();
        }

        if (Schema::hasTable('trips')) {
            return DB::table('trips')
                ->where('created_at', '>=', today())
                ->where('status', 'COMPLETED')
                ->count();
        }

        return 0;
    }

    /**
     * Get total revenue for today
     */
    private function getTotalRevenueToday(): string
    {
        $revenue = 0;

        // Get revenue from payments
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'amount')) {
            $payment = DB::table('payments')
                ->where('created_at', '>=', today())
                ->where('status', 'COMPLETED')
                ->sum('amount');

            $revenue += $payment ?? 0;
        }

        // Get revenue from trips
        if (Schema::hasTable('trips') && Schema::hasColumn('trips', 'total_fare')) {
            $trip = DB::table('trips')
                ->where('created_at', '>=', today())
                ->where('status', 'COMPLETED')
                ->sum('total_fare');

            $revenue += $trip ?? 0;
        }

        // Format the revenue
        $revenue = max(0, $revenue);

        return 'RWF '.number_format($revenue, 0);
    }

    /**
     * Get count of currently active drivers
     */
    private function getActiveDriversCount(): int
    {
        // Check for drivers with recent location updates
        if (Schema::hasTable('driver_locations') && Schema::hasColumn('driver_locations', 'updated_at')) {
            return DB::table('driver_locations')
                ->where('updated_at', '>=', now()->subMinutes(5))
                ->distinct('driver_id')
                ->count();
        }

        // Fallback: count drivers with online status
        if (Schema::hasTable('drivers')) {
            return DB::table('drivers')
                ->where('status', 'online')
                ->count();
        }

        return 0;
    }

    /**
     * Get count of currently active passengers
     */
    private function getActivePassengersCount(): int
    {
        // trips holds the passenger-user relationship; rides has no passenger field
        if (Schema::hasTable('trips') && Schema::hasColumn('trips', 'passenger_id')) {
            return DB::table('trips')
                ->whereIn('status', ['ACCEPTED', 'IN_PROGRESS', 'STARTED'])
                ->distinct('passenger_id')
                ->count();
        }

        return 0;
    }

    /**
     * Get average rating from reviews
     */
    private function getAverageRating(): ?float
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'rating')) {
            $avg = DB::table('reviews')
                ->where('created_at', '>=', now()->subDays(30))
                ->avg('rating');

            return $avg !== null ? round((float) $avg, 2) : null;
        }

        return null;
    }

    /**
     * Get ride completion rate percentage
     */
    private function getRideCompletionRate(): ?float
    {
        $table = Schema::hasTable('rides') ? 'rides' : (Schema::hasTable('trips') ? 'trips' : null);

        if (! $table) {
            return null;
        }

        $totalRides = DB::table($table)
            ->where('created_at', '>=', today())
            ->count();

        if ($totalRides === 0) {
            return null;
        }

        $completedRides = DB::table($table)
            ->where('created_at', '>=', today())
            ->where('status', 'COMPLETED')
            ->count();

        return round(($completedRides / $totalRides) * 100, 1);
    }

    /**
     * @return array{tone: string, label: string, message: string}
     */
    public function getPlatformStatus(): array
    {
        $systemMetrics = $this->getSystemMetrics();
        $businessMetrics = $this->getBusinessMetrics();

        if (! Schema::hasTable('platform_health_snapshots')) {
            return [
                'tone' => 'warning',
                'label' => 'Telemetry unavailable',
                'message' => 'Create the platform_health_snapshots table and run the health snapshot recorder.',
            ];
        }

        $latestSnapshot = DB::table('platform_health_snapshots')
            ->orderByDesc('created_at')
            ->first();

        if (! $latestSnapshot) {
            return [
                'tone' => 'warning',
                'label' => 'No snapshots yet',
                'message' => 'Run the health snapshot recorder to populate live metrics.',
            ];
        }

        $issues = [];

        if (($systemMetrics['uptime'] ?? null) === null) {
            $issues[] = 'Uptime snapshots are missing.';
        } elseif ($systemMetrics['uptime'] < 99) {
            $issues[] = 'Uptime is below target.';
        }

        if (($systemMetrics['api_response_time'] ?? null) === null) {
            $issues[] = 'Prediction latency has not been recorded yet.';
        }

        if (($systemMetrics['cache_hit_rate'] ?? null) === null) {
            $issues[] = 'Cache snapshots are missing.';
        }

        if (($systemMetrics['error_rate'] ?? null) === null) {
            $issues[] = 'Error snapshots are missing.';
        }

        if (($systemMetrics['queue_jobs_pending'] ?? 0) > 1000) {
            $issues[] = 'Queue backlog is high.';
        }

        if (($businessMetrics['completion_rate'] ?? null) !== null && $businessMetrics['completion_rate'] < 90) {
            $issues[] = 'Ride completion rate is below target.';
        } elseif (($businessMetrics['completion_rate'] ?? null) === null) {
            $missingTelemetry[] = 'ride completion data';
        }

        if (($businessMetrics['average_rating'] ?? null) === null) {
            $issues[] = 'Review data is unavailable.';
        }

        if ($issues !== []) {
            return [
                'tone' => $latestSnapshot->overall_status === 'healthy' ? 'warning' : 'danger',
                'label' => $latestSnapshot->overall_status === 'healthy' ? 'Operational with gaps' : 'Degraded',
                'message' => implode(' ', $issues),
            ];
        }

        return [
            'tone' => 'success',
            'label' => 'Operational',
            'message' => 'Latest database snapshot shows the platform operating normally.',
        ];
    }
}
