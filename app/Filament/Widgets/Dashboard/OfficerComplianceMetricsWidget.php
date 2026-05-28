<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Models\Driver;
use App\Models\Ride;
use Filament\Widgets\Widget;

class OfficerComplianceMetricsWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.officer-compliance-metrics-widget';

    protected int|string|array $columnSpan = [
        'default' => 1,
        'md' => 2,
    ];

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '90s');
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && (method_exists($user, 'can') && ($user->can('view rides') || $user->can('manage rides')));
    }

    protected function getViewData(): array
    {
        $totalRides = Ride::whereDate('created_at', now()->subDays(30)->toDateString())->count();
        $cancellations = Ride::whereDate('created_at', now()->subDays(30)->toDateString())
            ->whereIn('status', ['cancelled', 'CANCELLED'])
            ->count();
        $cancellationRate = $totalRides > 0 ? round(($cancellations / $totalRides) * 100, 2) : 0;

        $totalCompletedRides = Ride::whereDate('completed_at', now()->subDays(30)->toDateString())
            ->whereIn('status', ['completed', 'COMPLETED'])
            ->count();

        // Get average driver rating
        $avgDriverRating = Driver::query()
            ->whereNotNull('rating')
            ->avg('rating');

        return [
            'cancellationRate' => $cancellationRate,
            'totalRides' => $totalRides,
            'completedRides' => $totalCompletedRides,
            'avgDriverRating' => $avgDriverRating ? round($avgDriverRating, 2) : null,
            'complianceScore' => max(0, 100 - $cancellationRate),
        ];
    }
}
