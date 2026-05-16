<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountantPayoutPipelineWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '120s');
    }

    protected function getStats(): array
    {
        if (! Schema::hasTable('driver_payouts') || ! Schema::hasColumn('driver_payouts', 'status')) {
            return [
                Stat::make('Pending Payouts', '0')->description('Awaiting processing')->color('warning'),
                Stat::make('Processed Today', '0')->description('Completed today')->color('success'),
                Stat::make('Failed / Rejected', '0')->description('Needs review')->color('danger'),
                Stat::make('Total Payout Records', '0')->description('Pipeline volume')->color('info'),
            ];
        }

        $pending = (int) DB::table('driver_payouts')
            ->whereIn('status', ['pending', 'PENDING'])
            ->count();

        $processedTodayQuery = DB::table('driver_payouts')
            ->whereIn('status', ['processed', 'PROCESSED']);

        if (Schema::hasColumn('driver_payouts', 'processed_at')) {
            $processedTodayQuery->whereDate('processed_at', now()->toDateString());
        } elseif (Schema::hasColumn('driver_payouts', 'updated_at')) {
            $processedTodayQuery->whereDate('updated_at', now()->toDateString());
        }

        $processedToday = (int) $processedTodayQuery->count();

        $failed = (int) DB::table('driver_payouts')
            ->whereIn('status', ['failed', 'FAILED', 'rejected', 'REJECTED'])
            ->count();

        $total = (int) DB::table('driver_payouts')->count();

        return [
            Stat::make('Pending Payouts', number_format($pending))
                ->description('Awaiting processing')
                ->color('warning'),
            Stat::make('Processed Today', number_format($processedToday))
                ->description('Completed today')
                ->color('success'),
            Stat::make('Failed / Rejected', number_format($failed))
                ->description('Needs review')
                ->color('danger'),
            Stat::make('Total Payout Records', number_format($total))
                ->description('Pipeline volume')
                ->color('info'),
        ];
    }
}
