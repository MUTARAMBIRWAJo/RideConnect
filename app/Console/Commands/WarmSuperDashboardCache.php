<?php

namespace App\Console\Commands;

use App\Services\Dashboard\SuperDashboardMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WarmSuperDashboardCache extends Command
{
    protected $signature = 'dashboard:warm-cache {--days=7 : Days for ride analytics warmup (3-31)} {--clear : Clear known dashboard cache keys before warmup}';

    protected $description = 'Warm and validate Super Dashboard metrics cache';

    public function handle(SuperDashboardMetricsService $metrics): int
    {
        $days = max(3, min((int) $this->option('days'), 31));

        if ((bool) $this->option('clear')) {
            foreach ($this->knownCacheKeys($days) as $key) {
                Cache::forget($key);
            }

            $this->line('Cleared dashboard cache keys.');
        }

        $start = microtime(true);

        $overview = $metrics->getOverviewStats();
        $analytics = $metrics->getRideAnalytics($days);
        $revenue = $metrics->resolveTotalRevenue();

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->info('Super dashboard cache warmup completed.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total users', (string) ((int) ($overview['total_users'] ?? 0))],
                ['Pending approvals', (string) ((int) ($overview['pending_users'] ?? 0))],
                ['Active rides', (string) ((int) ($overview['active_rides'] ?? 0))],
                ['Total revenue', number_format((float) $revenue, 2)],
                ['Analytics points', (string) count($analytics['totals'] ?? [])],
                ['Warmup time (ms)', (string) $durationMs],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function knownCacheKeys(int $days): array
    {
        return [
            'admin.stats.payment_source',
            'admin.stats.total_revenue',
            'admin.stats.overview',
            "admin.stats.ride_analytics.{$days}",
        ];
    }
}