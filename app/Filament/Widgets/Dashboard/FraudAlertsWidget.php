<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\FraudFlag;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class FraudAlertsWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        if (! Schema::hasTable('fraud_flags')) {
            return $this->emptyStats();
        }

        $highUnresolved   = FraudFlag::query()->where('severity', 'high')->where('resolved', false)->count();
        $mediumUnresolved = FraudFlag::query()->where('severity', 'medium')->where('resolved', false)->count();
        $resolvedToday    = FraudFlag::query()->where('resolved', true)->whereDate('resolved_at', now())->count();
        $totalActive      = $highUnresolved + $mediumUnresolved
            + FraudFlag::query()->where('severity', 'low')->where('resolved', false)->count();

        return [
            Stat::make('High-Severity Flags', (string) $highUnresolved)
                ->description('Unresolved — payouts blocked')
                ->color($highUnresolved > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-shield-exclamation'),

            Stat::make('Medium-Severity Flags', (string) $mediumUnresolved)
                ->description('Unresolved — under review')
                ->color($mediumUnresolved > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),

            Stat::make('Total Active Flags', (string) $totalActive)
                ->description('All unresolved fraud flags')
                ->color($totalActive > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-flag'),

            Stat::make('Resolved Today', (string) $resolvedToday)
                ->description('Flags cleared today')
                ->color('info')
                ->icon('heroicon-o-check-circle'),
        ];
    }

    private function emptyStats(): array
    {
        return [
            Stat::make('High-Severity Flags', '0')->description('Awaiting migrations')->color('gray'),
            Stat::make('Medium-Severity Flags', '0')->description('Awaiting migrations')->color('gray'),
            Stat::make('Total Active Flags', '0')->description('Awaiting migrations')->color('gray'),
            Stat::make('Resolved Today', '0')->description('Awaiting migrations')->color('gray'),
        ];
    }
}
