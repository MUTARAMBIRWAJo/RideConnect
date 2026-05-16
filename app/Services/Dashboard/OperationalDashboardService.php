<?php

namespace App\Services\Dashboard;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\Ride;
use App\Models\Trip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalDashboardService
{
    /**
     * @return array<string, int|float>
     */
    public function getMainKpis(): array
    {
        return $this->remember('dashboard.kpis.main', 60, function (): array {
            $todayStart = now()->startOfDay();

            $totalRidesToday = Schema::hasTable('rides')
                ? (int) Ride::query()->where('created_at', '>=', $todayStart)->count()
                : 0;

            $activeDrivers = Schema::hasTable('drivers')
                ? (int) Driver::query()
                    ->where(function ($query): void {
                        $query->where('status', 'active')
                            ->orWhere('status', 'ACTIVE')
                            ->orWhere('availability_status', 'online')
                            ->orWhere('availability_status', 'ONLINE');
                    })
                    ->count()
                : 0;

            $activeTrips = Schema::hasTable('trips')
                ? (int) Trip::query()
                    ->whereIn('status', ['PENDING', 'ACCEPTED', 'STARTED', 'pending', 'accepted', 'started'])
                    ->count()
                : 0;

            $revenueToday = $this->resolveRevenueToday();

            $cancelledTrips = Schema::hasTable('trips')
                ? (int) Trip::query()
                    ->where('created_at', '>=', $todayStart)
                    ->whereIn('status', ['CANCELLED', 'cancelled'])
                    ->count()
                : 0;

            $totalTripsToday = Schema::hasTable('trips')
                ? (int) Trip::query()->where('created_at', '>=', $todayStart)->count()
                : 0;

            $cancellationRate = $totalTripsToday > 0
                ? round(($cancelledTrips / $totalTripsToday) * 100, 2)
                : 0.0;

            return [
                'total_rides_today' => $totalRidesToday,
                'active_drivers' => $activeDrivers,
                'active_trips' => $activeTrips,
                'revenue_today' => $revenueToday,
                'cancellation_rate' => $cancellationRate,
            ];
        }, [
            'total_rides_today' => 0,
            'active_drivers' => 0,
            'active_trips' => 0,
            'revenue_today' => 0.0,
            'cancellation_rate' => 0.0,
        ]);
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function ridesPerHour(): array
    {
        return $this->remember('dashboard.charts.rides_per_hour', 120, function (): array {
            $labels = collect(range(0, 23))->map(fn (int $hour): string => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00');

            if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'created_at')) {
                return ['labels' => $labels->all(), 'data' => array_fill(0, 24, 0)];
            }

            $rows = Ride::query()
                ->selectRaw('EXTRACT(HOUR FROM created_at) as hour_bucket, COUNT(*) as total')
                ->whereDate('created_at', now()->toDateString())
                ->groupBy('hour_bucket')
                ->orderBy('hour_bucket')
                ->get();

            $grouped = $rows->mapWithKeys(fn ($row) => [(int) $row->hour_bucket => (int) $row->total]);
            $data = collect(range(0, 23))->map(fn (int $hour) => (int) ($grouped[$hour] ?? 0))->all();

            return ['labels' => $labels->all(), 'data' => $data];
        }, ['labels' => [], 'data' => []]);
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public function revenueTrend(int $days = 7): array
    {
        $days = max(7, min($days, 30));

        return $this->remember("dashboard.charts.revenue_trend.{$days}", 180, function () use ($days): array {
            [$table, $amountColumn] = $this->resolvePaymentsSource();
            $labels = collect(range($days - 1, 0))->map(fn (int $offset): string => now()->subDays($offset)->format('M d'));
            $labels->push(now()->format('M d'));

            if (! $table || ! $amountColumn || ! Schema::hasColumn($table, 'created_at')) {
                return ['labels' => $labels->all(), 'data' => array_fill(0, $labels->count(), 0)];
            }

            $rows = DB::table($table)
                ->selectRaw('DATE(created_at) as day, SUM('.$amountColumn.') as total')
                ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $grouped = $rows->mapWithKeys(fn ($row): array => [(string) $row->day => (float) $row->total]);

            $data = collect(range($days - 1, 0))
                ->map(function (int $offset) use ($grouped): float {
                    $key = now()->subDays($offset)->toDateString();

                    return round((float) ($grouped[$key] ?? 0), 2);
                })
                ->push(round((float) ($grouped[now()->toDateString()] ?? 0), 2))
                ->all();

            return ['labels' => $labels->all(), 'data' => $data];
        }, ['labels' => [], 'data' => []]);
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function driverActivity(int $hours = 12): array
    {
        $hours = max(6, min($hours, 24));

        return $this->remember("dashboard.charts.driver_activity.{$hours}", 120, function () use ($hours): array {
            $start = now()->subHours($hours - 1)->startOfHour();
            $labels = collect(range(0, $hours - 1))->map(fn (int $index): string => $start->copy()->addHours($index)->format('H:i'));

            if (! Schema::hasTable('driver_locations') || ! Schema::hasColumn('driver_locations', 'updated_at')) {
                return ['labels' => $labels->all(), 'data' => array_fill(0, $hours, 0)];
            }

            $rows = DriverLocation::query()
                ->selectRaw('DATE_TRUNC(\'hour\', updated_at) as bucket, COUNT(DISTINCT driver_id) as total')
                ->where('updated_at', '>=', $start)
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            $grouped = $rows->mapWithKeys(function ($row): array {
                $key = $row->bucket instanceof \DateTimeInterface
                    ? $row->bucket->format('Y-m-d H:00:00')
                    : (string) $row->bucket;

                return [$key => (int) $row->total];
            });

            $data = collect(range(0, $hours - 1))->map(function (int $index) use ($grouped, $start): int {
                $key = $start->copy()->addHours($index)->format('Y-m-d H:00:00');

                return (int) ($grouped[$key] ?? 0);
            })->all();

            return ['labels' => $labels->all(), 'data' => $data];
        }, ['labels' => [], 'data' => []]);
    }

    /**
     * @return array{bookings: int, trips: int}
     */
    public function bookingTripRatio(): array
    {
        return $this->remember('dashboard.charts.booking_trip_ratio', 120, function (): array {
            $today = now()->toDateString();

            $bookings = Schema::hasTable('bookings')
                ? (int) Booking::query()->whereDate('created_at', $today)->count()
                : 0;

            $trips = Schema::hasTable('trips')
                ? (int) Trip::query()->whereDate('created_at', $today)->count()
                : 0;

            return ['bookings' => $bookings, 'trips' => $trips];
        }, ['bookings' => 0, 'trips' => 0]);
    }

    /**
     * @return array<int, array{type: string, id: int, status: string, created_at: string}>
     */
    public function recentActivity(int $limit = 20): array
    {
        $limit = max(10, min($limit, 50));

        return $this->remember("dashboard.activity.recent.{$limit}", 60, function () use ($limit): array {
            $items = collect();

            if (Schema::hasTable('rides')) {
                $items = $items->merge(
                    Ride::query()->latest('id')->limit($limit)->get(['id', 'status', 'created_at'])
                        ->map(fn (Ride $ride): array => [
                            'type' => 'Ride',
                            'id' => (int) $ride->id,
                            'status' => (string) ($ride->status ?? 'unknown'),
                            'created_at' => optional($ride->created_at)->toDateTimeString() ?? now()->toDateTimeString(),
                        ])
                );
            }

            if (Schema::hasTable('bookings')) {
                $items = $items->merge(
                    Booking::query()->latest('id')->limit($limit)->get(['id', 'status', 'created_at'])
                        ->map(fn (Booking $booking): array => [
                            'type' => 'Booking',
                            'id' => (int) $booking->id,
                            'status' => (string) ($booking->status ?? 'unknown'),
                            'created_at' => optional($booking->created_at)->toDateTimeString() ?? now()->toDateTimeString(),
                        ])
                );
            }

            if (Schema::hasTable('trips')) {
                $items = $items->merge(
                    Trip::query()
                        ->whereIn('status', ['CANCELLED', 'cancelled'])
                        ->latest('id')
                        ->limit($limit)
                        ->get(['id', 'status', 'created_at'])
                        ->map(fn (Trip $trip): array => [
                            'type' => 'Cancellation',
                            'id' => (int) $trip->id,
                            'status' => (string) ($trip->status ?? 'cancelled'),
                            'created_at' => optional($trip->created_at)->toDateTimeString() ?? now()->toDateTimeString(),
                        ])
                );
            }

            return $items
                ->sortByDesc('created_at')
                ->take($limit)
                ->values()
                ->all();
        }, []);
    }

    private function resolveRevenueToday(): float
    {
        [$table, $amountColumn] = $this->resolvePaymentsSource();

        if (! $table || ! $amountColumn || ! Schema::hasColumn($table, 'created_at')) {
            return 0.0;
        }

        return (float) DB::table($table)
            ->whereDate('created_at', now()->toDateString())
            ->sum($amountColumn);
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolvePaymentsSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    return [$table, $column];
                }
            }
        }

        return [null, null];
    }

    /**
     * @template T
     *
     * @param  T  $fallback
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
