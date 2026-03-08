<?php

use App\Filament\Widgets\Dashboard\AccountantRevenueSummary;
use App\Filament\Widgets\Dashboard\ActivityFeedWidget;
use App\Filament\Widgets\Dashboard\AdminOverviewStats;
use App\Filament\Widgets\Dashboard\CommissionOverviewWidget;
use App\Filament\Widgets\Dashboard\DailyCommissionChartWidget;
use App\Filament\Widgets\Dashboard\EscrowBalanceWidget;
use App\Filament\Widgets\Dashboard\FinanceExportActionsWidget;
use App\Filament\Widgets\Dashboard\FraudAlertsWidget;
use App\Filament\Widgets\Dashboard\MonthlyEarningsChartWidget;
use App\Filament\Widgets\Dashboard\NotificationsWidget;
use App\Filament\Widgets\Dashboard\OfficerOverviewStats;
use App\Filament\Widgets\Dashboard\SuperAdminOverviewStats;
use App\Filament\Widgets\Dashboard\SystemLogsWidget;
use App\Filament\Widgets\Dashboard\TransactionsTableWidget;
use App\Filament\Widgets\DemandHeatmapWidget;
use App\Filament\Widgets\DriverAvailabilityChart;
use App\Filament\Widgets\LatestRidesTable;
use App\Filament\Widgets\RideStatsOverview;

return [
    // Mobile-first dashboard column breakpoints.
    'default_columns' => [
        'default' => 1,
        'sm' => 1,
        'md' => 2,
        'xl' => 3,
    ],

    'realtime' => [
        'enabled' => env('DASHBOARD_REALTIME_ENABLED', true),
        'polling_interval' => env('DASHBOARD_POLLING_INTERVAL', '30s'),
    ],

    'roles' => [
        'SUPER_ADMIN' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                SuperAdminOverviewStats::class,
                RideStatsOverview::class,
                DriverAvailabilityChart::class,
                DemandHeatmapWidget::class,
                NotificationsWidget::class,
                ActivityFeedWidget::class,
                SystemLogsWidget::class,
            ],
        ],
        'ADMIN' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                AdminOverviewStats::class,
                RideStatsOverview::class,
                DriverAvailabilityChart::class,
                DemandHeatmapWidget::class,
                NotificationsWidget::class,
                LatestRidesTable::class,
                ActivityFeedWidget::class,
            ],
        ],
        'ACCOUNTANT' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                EscrowBalanceWidget::class,
                AccountantRevenueSummary::class,
                CommissionOverviewWidget::class,
                FraudAlertsWidget::class,
                DailyCommissionChartWidget::class,
                MonthlyEarningsChartWidget::class,
                TransactionsTableWidget::class,
                FinanceExportActionsWidget::class,
                NotificationsWidget::class,
            ],
        ],
        'OFFICER' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                OfficerOverviewStats::class,
                DriverAvailabilityChart::class,
                DemandHeatmapWidget::class,
                NotificationsWidget::class,
                LatestRidesTable::class,
                ActivityFeedWidget::class,
            ],
        ],
        'DRIVER' => [
            'columns' => ['default' => 1, 'md' => 2],
            'widgets' => [
                RideStatsOverview::class,
                NotificationsWidget::class,
                LatestRidesTable::class,
                ActivityFeedWidget::class,
            ],
        ],
        'PASSENGER' => [
            'columns' => ['default' => 1, 'md' => 2],
            'widgets' => [
                RideStatsOverview::class,
                NotificationsWidget::class,
                ActivityFeedWidget::class,
            ],
        ],
    ],
];
