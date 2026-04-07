<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Models\Ride;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OfficerActiveRidesWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, false);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '45s');
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && (method_exists($user, 'can') && ($user->can('view rides') || $user->can('manage rides')));
    }

    protected function getStats(): array
    {
        $inProgress = Ride::whereIn('status', ['in_progress', 'IN_PROGRESS', 'accepted', 'ACCEPTED'])->count();
        $pending = Ride::whereIn('status', ['pending', 'PENDING', 'confirmed', 'CONFIRMED'])->count();
        $cancelled = Ride::whereDate('updated_at', now()->toDateString())
            ->whereIn('status', ['cancelled', 'CANCELLED'])
            ->count();
        $completed = Ride::whereDate('completed_at', now()->toDateString())
            ->whereIn('status', ['completed', 'COMPLETED'])
            ->count();

        return [
            Stat::make('Active Rides', number_format($inProgress))
                ->description('Currently in progress')
                ->icon('heroicon-o-bolt')
                ->color('success'),
            Stat::make('Pending Assignments', number_format($pending))
                ->description('Awaiting driver assignment')
                ->icon('heroicon-o-clock')
                ->color('warning'),
            Stat::make('Completed Today', number_format($completed))
                ->description('Successfully finished')
                ->icon('heroicon-o-check-circle')
                ->color('info'),
            Stat::make('Cancelled Today', number_format($cancelled))
                ->description('Cancellation rate tracking')
                ->icon('heroicon-o-x-circle')
                ->color('danger'),
        ];
    }
}
