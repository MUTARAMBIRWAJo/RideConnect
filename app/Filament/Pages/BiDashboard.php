<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\BI\CommissionTodayWidget;
use App\Filament\Widgets\BI\FraudRiskHeatmapWidget;
use App\Filament\Widgets\BI\LiveRevenueTickerWidget;
use App\Filament\Widgets\BI\RevenueOverTimeChartWidget;
use App\Filament\Widgets\BI\TopDriversLeaderboardWidget;
use Filament\Pages\Page;

class BiDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?string $navigationGroup = 'AI & Analytics';
    protected static ?string $title           = 'Business Intelligence Dashboard';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.bi-dashboard';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ACCOUNTANT->value,
        ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            LiveRevenueTickerWidget::class,
            CommissionTodayWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RevenueOverTimeChartWidget::class,
            FraudRiskHeatmapWidget::class,
            TopDriversLeaderboardWidget::class,
        ];
    }
}
