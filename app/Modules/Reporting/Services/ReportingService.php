<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Contracts\ReportingRepositoryInterface;
use App\Modules\Reporting\DTOs\ReportSummaryDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * ReportingService — real-time and historical BI data service.
 *
 * Reads from the data warehouse (dw_* tables) and materialized views.
 * Results are cached in Redis for BI dashboard performance.
 * Cache TTL is configurable per report type.
 */
class ReportingService
{
    private const CACHE_TTL = [
        'revenue_today'      => 60,      // 1 minute (live)
        'commission_trend'   => 300,     // 5 minutes
        'driver_performance' => 300,
        'fraud_risk'         => 120,
        'monthly_growth'     => 3600,    // 1 hour
        'driver_rankings'    => 600,     // 10 minutes
    ];

    public function __construct(
        private readonly ReportingRepositoryInterface $reportingRepo,
    ) {}

    public function getRevenueSummaryToday(): array
    {
        return Cache::remember(
            'bi:revenue:today',
            self::CACHE_TTL['revenue_today'],
            fn () => $this->reportingRepo->getDailyRevenueSummary(now()->toDateString())
        );
    }

    public function getCommissionTrend(int $days = 30): array
    {
        $cacheKey = "bi:commission_trend:{$days}d";

        return Cache::remember($cacheKey, self::CACHE_TTL['commission_trend'], function () use ($days) {
            $from = now()->subDays($days)->toDateString();
            $to   = now()->toDateString();
            return $this->reportingRepo->getCommissionBreakdown($from, $to);
        });
    }

    public function getDriverPerformance(int $days = 30): array
    {
        $cacheKey = "bi:driver_performance:{$days}d";

        return Cache::remember($cacheKey, self::CACHE_TTL['driver_performance'], function () use ($days) {
            $from = now()->subDays($days)->toDateString();
            $to   = now()->toDateString();
            return $this->reportingRepo->getDriverEarningsSummary($from, $to);
        });
    }

    public function getFraudRisk(): array
    {
        return Cache::remember('bi:fraud_risk', self::CACHE_TTL['fraud_risk'], function () {
            $from = now()->subDays(30)->toDateString();
            $to   = now()->toDateString();
            return $this->reportingRepo->getFraudIncidentReport($from, $to);
        });
    }

    public function getDriverRankings(): array
    {
        return Cache::remember('bi:driver_rankings', self::CACHE_TTL['driver_rankings'], function () {
            return \Illuminate\Support\Facades\DB::table('mv_driver_rankings')
                ->orderBy('earnings_rank')
                ->limit(20)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        });
    }

    public function getMonthlyGrowth(): array
    {
        return Cache::remember('bi:monthly_growth', self::CACHE_TTL['monthly_growth'], function () {
            return \Illuminate\Support\Facades\DB::table('mv_monthly_growth')
                ->orderByDesc('month')
                ->limit(12)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        });
    }

    /**
     * Invalidate all BI caches (call after ETL job or settlement).
     */
    public function invalidateCache(): void
    {
        $keys = ['bi:revenue:today', 'bi:fraud_risk', 'bi:driver_rankings', 'bi:monthly_growth'];
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        // Pattern-clear with Redis
        try {
            $redis   = Redis::connection();
            $prefix  = config('cache.prefix');
            $pattern = "{$prefix}bi:commission_trend:*";
            $keys    = $redis->keys($pattern);
            if ($keys) $redis->del($keys);
        } catch (\Throwable) {
            // Redis unavailable — skip pattern clear gracefully
        }
    }

    public function buildReportSummary(string $from, string $to): ReportSummaryDTO
    {
        $revenue    = $this->reportingRepo->getDailyRevenueSummary($to);
        $commission = $this->reportingRepo->getCommissionBreakdown($from, $to);
        $tax        = $this->reportingRepo->getTaxPayableSummary($from, $to);

        $totalCommission = array_sum(array_column($commission, 'commission'));
        $totalTax        = array_sum(array_column($tax, 'tax_collected'));

        return new ReportSummaryDTO(
            reportType:        'combined',
            period:            "{$from} – {$to}",
            totalRevenue:      $revenue['total_gross'] ?? 0,
            totalCommission:   $totalCommission,
            totalTax:          $totalTax,
            totalPayouts:      $revenue['total_driver_payout'] ?? 0,
            totalTransactions: $revenue['transaction_count'] ?? 0,
            totalRides:        0,
            breakdown:         compact('commission', 'tax'),
        );
    }
}
