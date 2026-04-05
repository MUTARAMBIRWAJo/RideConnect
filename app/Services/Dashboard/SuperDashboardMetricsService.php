<?php

namespace App\Services\Dashboard;

use App\Models\Ride;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperDashboardMetricsService
{
    /**
     * @return array<string, int|float>
     */
    public function getOverviewStats(): array
    {
        return $this->remember('admin.stats.overview', 60, function (): array {
            return [
                'total_users' => (int) User::query()->count(),
                'pending_users' => (int) User::query()->where('is_approved', false)->count(),
                'active_rides' => (int) Ride::query()
                    ->whereIn('status', ['in_progress', 'accepted', 'IN_PROGRESS', 'ACCEPTED'])
                    ->count(),
                'total_revenue' => $this->resolveTotalRevenue(),
            ];
        }, [
            'total_users' => 0,
            'pending_users' => 0,
            'active_rides' => 0,
            'total_revenue' => 0.0,
        ]);
    }

    /**
     * @return array{labels: array<int, string>, totals: array<int, int>}
     */
    public function getRideAnalytics(int $days = 7): array
    {
        $days = max(3, min($days, 31));

        return $this->remember("admin.stats.ride_analytics.{$days}", 120, function () use ($days): array {
            $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
            $end = CarbonImmutable::now()->endOfDay();

            if (!Schema::hasTable('rides') || !Schema::hasColumn('rides', 'created_at')) {
                $labels = collect(range(0, $days - 1))
                    ->map(fn (int $offset) => $start->addDays($offset)->format('D'))
                    ->all();

                return [
                    'labels' => $labels,
                    'totals' => array_fill(0, $days, 0),
                ];
            }

            $rows = DB::table('rides')
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $indexed = $rows
                ->mapWithKeys(fn (object $row): array => [(string) $row->day => (int) $row->total]);

            $labels = [];
            $totals = [];

            foreach (range(0, $days - 1) as $offset) {
                $day = $start->addDays($offset);
                $dayKey = $day->toDateString();

                $labels[] = $day->format('D');
                $totals[] = (int) ($indexed[$dayKey] ?? 0);
            }

            return compact('labels', 'totals');
        }, [
            'labels' => [],
            'totals' => [],
        ]);
    }

    /**
     * @return float
     */
    public function resolveTotalRevenue(): float
    {
        return (float) $this->remember('admin.stats.total_revenue', 60, function (): float {
            [$table, $amountColumn] = $this->resolvePaymentSource();

            if (!$table || !$amountColumn) {
                return 0.0;
            }

            return (float) DB::table($table)->sum($amountColumn);
        }, 0.0);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    public function resolvePaymentSource(): array
    {
        $cached = Cache::remember('admin.stats.payment_source', 300, function (): array {
            foreach (['payments_v2', 'payments'] as $table) {
                if (!Schema::hasTable($table)) {
                    continue;
                }

                foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        return [$table, $column];
                    }
                }
            }

            return [null, null];
        });

        return [
            is_string($cached[0] ?? null) ? $cached[0] : null,
            is_string($cached[1] ?? null) ? $cached[1] : null,
        ];
    }

    /**
     * @template T
     * @param T $fallback
     * @return T
     */
    private function remember(string $key, int $seconds, callable $callback, mixed $fallback): mixed
    {
        try {
            return Cache::remember($key, $seconds, $callback);
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }
}