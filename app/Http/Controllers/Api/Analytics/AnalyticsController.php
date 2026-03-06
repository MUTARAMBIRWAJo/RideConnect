<?php

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
    ) {}

    /**
     * GET /api/v1/analytics/revenue
     * Real-time revenue summary (today + 30-day trend).
     */
    public function revenue(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'today'          => $this->reportingService->getRevenueSummaryToday(),
                'monthly_growth' => $this->reportingService->getMonthlyGrowth(),
                'currency'       => 'RWF',
            ],
        ]);
    }

    /**
     * GET /api/v1/analytics/driver-performance
     */
    public function driverPerformance(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $days = min(max($days, 1), 365);

        return response()->json([
            'success' => true,
            'data'    => [
                'rankings'    => $this->reportingService->getDriverRankings(),
                'performance' => $this->reportingService->getDriverPerformance($days),
                'period_days' => $days,
            ],
        ]);
    }

    /**
     * GET /api/v1/analytics/commission-trend
     */
    public function commissionTrend(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $days = min(max($days, 1), 365);

        return response()->json([
            'success' => true,
            'data'    => $this->reportingService->getCommissionTrend($days),
        ]);
    }

    /**
     * GET /api/v1/analytics/fraud-risk
     */
    public function fraudRisk(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->reportingService->getFraudRisk(),
        ]);
    }
}
