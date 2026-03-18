<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsIntelligenceDataService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(?string $fromDate = null, ?string $toDate = null): array
    {
        $to = $toDate ? Carbon::parse($toDate) : now();
        $from = $fromDate ? Carbon::parse($fromDate) : $to->copy()->subDays(6);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'kpis' => $this->kpis(),
            'daily_trend' => $this->dailyTrend($from, $to),
            'status_mix' => $this->statusMix($from, $to),
            'top_routes' => $this->topRoutes($from, $to),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    public function kpis(): array
    {
        $ridesInProgress = 0;
        $ridesCompletedToday = 0;
        $bookingsPending = 0;
        $onlineDrivers = 0;

        if (Schema::hasTable('rides')) {
            $ridesInProgress = (int) DB::table('rides')
                ->whereIn('status', ['in_progress', 'IN_PROGRESS', 'accepted', 'ACCEPTED'])
                ->count();

            $ridesCompletedToday = (int) DB::table('rides')
                ->whereDate('created_at', now()->toDateString())
                ->whereIn('status', ['completed', 'COMPLETED'])
                ->count();
        }

        if (Schema::hasTable('bookings')) {
            $bookingsPending = (int) DB::table('bookings')
                ->whereIn('status', ['pending', 'PENDING'])
                ->count();
        }

        if (Schema::hasTable('drivers')) {
            if (Schema::hasColumn('drivers', 'availability_status')) {
                $onlineDrivers = (int) DB::table('drivers')
                    ->where('availability_status', 'online')
                    ->count();
            } elseif (Schema::hasColumn('drivers', 'is_online')) {
                $onlineDrivers = (int) DB::table('drivers')
                    ->where('is_online', true)
                    ->count();
            }
        }

        return [
            'rides_in_progress' => $ridesInProgress,
            'rides_completed_today' => $ridesCompletedToday,
            'bookings_pending' => $bookingsPending,
            'drivers_online' => $onlineDrivers,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function dailyTrend(?Carbon $from = null, ?Carbon $to = null): array
    {
        $to = $to ? $to->copy() : now();
        $from = $from ? $from->copy() : $to->copy()->subDays(6);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $days = (int) $from->diffInDays($to);
        if ($days > 60) {
            $from = $to->copy()->subDays(60);
        }

        $labels = [];
        $values = [];

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $date = $cursor->copy();
            $labels[] = $date->format('D');

            if (!Schema::hasTable('rides')) {
                $values[] = 0;
                continue;
            }

            $values[] = (int) DB::table('rides')
                ->whereDate('created_at', $date->toDateString())
                ->count();

            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function statusMix(?Carbon $from = null, ?Carbon $to = null): array
    {
        if (!Schema::hasTable('rides')) {
            return [
                'labels' => ['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'],
                'values' => [0, 0, 0, 0, 0],
            ];
        }

        $query = DB::table('rides')
            ->selectRaw('LOWER(COALESCE(status, ?)) AS normalized_status, COUNT(*) AS aggregate', ['unknown'])
            ->groupBy('normalized_status');

        if ($from) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }

        $grouped = $query->pluck('aggregate', 'normalized_status')->all();

        $keys = ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'];

        return [
            'labels' => ['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'],
            'values' => array_map(fn (string $key): int => (int) ($grouped[$key] ?? 0), $keys),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function topRoutes(?Carbon $from = null, ?Carbon $to = null): array
    {
        if (!Schema::hasTable('trips')) {
            return [];
        }

        $hasPickup = Schema::hasColumn('trips', 'pickup_location');
        $hasDropoff = Schema::hasColumn('trips', 'dropoff_location');

        if (!$hasPickup || !$hasDropoff) {
            return [];
        }

        $query = DB::table('trips')
            ->selectRaw('pickup_location, dropoff_location, COUNT(*) as total')
            ->whereNotNull('pickup_location')
            ->whereNotNull('dropoff_location')
            ->groupBy('pickup_location', 'dropoff_location')
            ->orderByDesc('total')
            ->limit(12);

        if ($from) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }

        return $query->get()
            ->map(fn ($row) => [
                'from' => (string) $row->pickup_location,
                'to' => (string) $row->dropoff_location,
                'total' => (int) $row->total,
            ])
            ->all();
    }
}
