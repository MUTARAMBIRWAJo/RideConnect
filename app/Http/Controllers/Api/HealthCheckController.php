<?php

namespace App\Http\Controllers\Api;

use App\Modules\Finance\Services\FinanceService;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\Settlement\Services\SettlementService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class HealthCheckController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService,
    ) {}

    /**
     * GET /api/v1/health/finance
     */
    public function finance(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Database connectivity
        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Throwable $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        // Ledger accounts present
        try {
            $count = DB::table('ledger_accounts')->count();
            $checks['ledger_accounts'] = $count > 0 ? 'ok (count: ' . $count . ')' : 'warning: no accounts seeded';
        } catch (\Throwable $e) {
            $checks['ledger_accounts'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        // Outbox queue depth
        try {
            $pending = DB::table('event_outbox')->where('status', 'pending')->count();
            $checks['outbox_pending'] = $pending;
            if ($pending > 1000) {
                $checks['outbox_warning'] = 'high outbox depth: ' . $pending;
            }
        } catch (\Throwable $e) {
            $checks['outbox_pending'] = 'error: ' . $e->getMessage();
        }

        return response()->json([
            'module'  => 'finance',
            'status'  => $healthy ? 'healthy' : 'degraded',
            'checks'  => $checks,
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    /**
     * GET /api/v1/health/settlement
     */
    public function settlement(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Driver payouts table
        try {
            $recent = DB::table('driver_payouts')
                ->where('created_at', '>=', now()->subDay())
                ->count();
            $checks['recent_payouts_24h'] = $recent;
        } catch (\Throwable $e) {
            $checks['driver_payouts'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        // Wallet consistency (pending should not be negative)
        try {
            $negative = DB::table('driver_wallets')
                ->whereRaw('pending_balance < 0')
                ->count();
            $checks['negative_pending_wallets'] = $negative;
            if ($negative > 0) {
                $healthy = false;
                $checks['wallet_warning'] = 'negative pending balances detected';
            }
        } catch (\Throwable $e) {
            $checks['wallets'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        return response()->json([
            'module'  => 'settlement',
            'status'  => $healthy ? 'healthy' : 'degraded',
            'checks'  => $checks,
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    /**
     * GET /api/v1/health/warehouse
     */
    public function warehouse(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Check materialized views are populated
        $views = ['mv_daily_revenue', 'mv_monthly_growth', 'mv_driver_rankings'];
        foreach ($views as $view) {
            try {
                $count = DB::selectOne("SELECT COUNT(*) AS cnt FROM {$view}");
                $checks["view_{$view}"] = 'ok (rows: ' . $count->cnt . ')';
            } catch (\Throwable $e) {
                $checks["view_{$view}"] = 'error: ' . $e->getMessage();
                $healthy = false;
            }
        }

        // Last ETL run
        try {
            $latestFact = DB::table('dw_fact_transactions')
                ->max('loaded_at');
            $checks['last_etl_load'] = $latestFact ?? 'never';
            if ($latestFact && now()->diffInHours($latestFact) > 26) {
                $checks['etl_warning'] = 'ETL may be stale (>26h since last load)';
            }
        } catch (\Throwable $e) {
            $checks['last_etl_load'] = 'error: ' . $e->getMessage();
        }

        // Cache connectivity
        try {
            Cache::put('_health_check', true, 5);
            $checks['redis_cache'] = Cache::get('_health_check') ? 'ok' : 'miss';
        } catch (\Throwable $e) {
            $checks['redis_cache'] = 'error: ' . $e->getMessage();
        }

        return response()->json([
            'module'  => 'warehouse',
            'status'  => $healthy ? 'healthy' : 'degraded',
            'checks'  => $checks,
            'timestamp' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }
}
