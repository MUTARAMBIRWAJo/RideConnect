<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Ride;
use App\Models\Driver;
use Illuminate\Support\Facades\Schema;

class RideStatsOverview extends Widget
{
    protected static string $view = 'filament.widgets.ride-stats-overview';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $activeRides = 0;
        $driversOnline = 0;
        $ridesToday = 0;
        $avgWait = null;
        $sparkline = [];
        $delta = 0;

        try {
            $activeRides = Ride::whereIn('status', ['in_progress', 'accepted'])->count();
        } catch (\Throwable $e) {
            $activeRides = 0;
        }

        try {
            $driversOnline = Schema::hasColumn('drivers', 'is_online')
                ? Driver::where('is_online', true)->count()
                : Driver::whereIn('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])->count();
        } catch (\Throwable $e) {
            $driversOnline = 0;
        }

        try {
            $ridesToday = Ride::whereDate('created_at', now()->toDateString())->count();
        } catch (\Throwable $e) {
            $ridesToday = 0;
        }

        try {
            $avgWait = Ride::whereNotNull('accepted_at')
                ->whereNotNull('requested_at')
                ->get()
                ->map(fn($r) => optional($r->accepted_at)->diffInMinutes($r->requested_at) ?? 0)
                ->avg() ?: null;
        } catch (\Throwable $e) {
            $avgWait = null;
        }

        for ($i = 6; $i >= 0; $i--) {
            try {
                $date = now()->subDays($i)->toDateString();
                $sparkline[] = Ride::whereDate('created_at', $date)->count();
            } catch (\Throwable $e) {
                $sparkline[] = 0;
            }
        }

        try {
            $yesterday = now()->subDay()->toDateString();
            $dayBefore = now()->subDays(2)->toDateString();
            $yCount = Ride::whereDate('created_at', $yesterday)->count();
            $pCount = Ride::whereDate('created_at', $dayBefore)->count();
            $delta = $pCount > 0 ? (int) round((($yCount - $pCount) / max($pCount, 1)) * 100) : ($yCount > 0 ? 100 : 0);
        } catch (\Throwable $e) {
            $delta = 0;
        }

        return compact('activeRides', 'driversOnline', 'avgWait', 'ridesToday', 'sparkline', 'delta');
    }
}
