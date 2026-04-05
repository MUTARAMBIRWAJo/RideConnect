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
use App\Filament\Widgets\RideMapWidget;
use App\Filament\Widgets\RideStatsOverview;
use App\Filament\Widgets\BI\CommissionTodayWidget;
use App\Filament\Widgets\BI\FraudRiskHeatmapWidget;
use App\Filament\Widgets\BI\LiveRevenueTickerWidget;
use App\Filament\Widgets\BI\RevenueOverTimeChartWidget;
use App\Filament\Widgets\BI\TopDriversLeaderboardWidget;

return [
    'super_dashboard_static_mode' => env('DASHBOARD_SUPER_STATIC_MODE', false),

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

    // Performance tuning knobs for slower devices/connections.
    'performance' => [
        'slow_mode' => env('DASHBOARD_SLOW_MODE', false),
        'polling' => [
            'default' => env('DASHBOARD_POLLING_DEFAULT', '90s'),
            'sections' => [
                'operational' => env('DASHBOARD_POLLING_OPERATIONAL', '60s'),
                'intelligence' => env('DASHBOARD_POLLING_INTELLIGENCE', '180s'),
            ],
            'widgets' => [
                SuperAdminOverviewStats::class => env('DASHBOARD_POLLING_SUPER_OVERVIEW', '60s'),
                RideStatsOverview::class => env('DASHBOARD_POLLING_RIDE_STATS', '90s'),
                DriverAvailabilityChart::class => env('DASHBOARD_POLLING_DRIVER_AVAILABILITY', '120s'),
                DemandHeatmapWidget::class => env('DASHBOARD_POLLING_DEMAND_HEATMAP', '180s'),
                NotificationsWidget::class => env('DASHBOARD_POLLING_NOTIFICATIONS', '75s'),
                ActivityFeedWidget::class => env('DASHBOARD_POLLING_ACTIVITY_FEED', '90s'),
                SystemLogsWidget::class => env('DASHBOARD_POLLING_SYSTEM_LOGS', '120s'),
                LiveRevenueTickerWidget::class => env('DASHBOARD_POLLING_BI_REVENUE_TICKER', '120s'),
                CommissionTodayWidget::class => env('DASHBOARD_POLLING_BI_COMMISSION', '180s'),
                RevenueOverTimeChartWidget::class => env('DASHBOARD_POLLING_BI_REVENUE_CHART', '600s'),
                FraudRiskHeatmapWidget::class => env('DASHBOARD_POLLING_BI_FRAUD', '300s'),
                TopDriversLeaderboardWidget::class => env('DASHBOARD_POLLING_BI_LEADERBOARD', '600s'),
            ],
        ],
        'slow_profile' => [
            'polling' => [
                'default' => env('DASHBOARD_SLOW_POLLING_DEFAULT', '240s'),
                'sections' => [
                    'operational' => env('DASHBOARD_SLOW_POLLING_OPERATIONAL', '180s'),
                    'intelligence' => env('DASHBOARD_SLOW_POLLING_INTELLIGENCE', '420s'),
                ],
                'widgets' => [
                    SuperAdminOverviewStats::class => env('DASHBOARD_SLOW_POLLING_SUPER_OVERVIEW', '180s'),
                    RideStatsOverview::class => env('DASHBOARD_SLOW_POLLING_RIDE_STATS', '240s'),
                    DriverAvailabilityChart::class => env('DASHBOARD_SLOW_POLLING_DRIVER_AVAILABILITY', '300s'),
                    DemandHeatmapWidget::class => env('DASHBOARD_SLOW_POLLING_DEMAND_HEATMAP', '420s'),
                    NotificationsWidget::class => env('DASHBOARD_SLOW_POLLING_NOTIFICATIONS', '240s'),
                    ActivityFeedWidget::class => env('DASHBOARD_SLOW_POLLING_ACTIVITY_FEED', '300s'),
                    SystemLogsWidget::class => env('DASHBOARD_SLOW_POLLING_SYSTEM_LOGS', '420s'),
                    LiveRevenueTickerWidget::class => env('DASHBOARD_SLOW_POLLING_BI_REVENUE_TICKER', '300s'),
                    CommissionTodayWidget::class => env('DASHBOARD_SLOW_POLLING_BI_COMMISSION', '420s'),
                    RevenueOverTimeChartWidget::class => env('DASHBOARD_SLOW_POLLING_BI_REVENUE_CHART', '900s'),
                    FraudRiskHeatmapWidget::class => env('DASHBOARD_SLOW_POLLING_BI_FRAUD', '720s'),
                    TopDriversLeaderboardWidget::class => env('DASHBOARD_SLOW_POLLING_BI_LEADERBOARD', '900s'),
                ],
            ],
            'lazy' => [
                // In slow mode defer almost everything until viewport interaction.
                'default' => true,
                'widgets' => [
                    SuperAdminOverviewStats::class => true,
                    RideStatsOverview::class => true,
                    DriverAvailabilityChart::class => true,
                    DemandHeatmapWidget::class => true,
                    NotificationsWidget::class => true,
                    ActivityFeedWidget::class => true,
                    SystemLogsWidget::class => true,
                    LiveRevenueTickerWidget::class => true,
                    CommissionTodayWidget::class => true,
                    RevenueOverTimeChartWidget::class => true,
                    FraudRiskHeatmapWidget::class => true,
                    TopDriversLeaderboardWidget::class => true,
                ],
            ],
        ],
        'lazy' => [
            'default' => true,
            'widgets' => [
                SuperAdminOverviewStats::class => false,
                RideStatsOverview::class => false,
                DriverAvailabilityChart::class => true,
                DemandHeatmapWidget::class => true,
                NotificationsWidget::class => true,
                ActivityFeedWidget::class => true,
                SystemLogsWidget::class => true,
                LiveRevenueTickerWidget::class => true,
                CommissionTodayWidget::class => true,
                RevenueOverTimeChartWidget::class => true,
                FraudRiskHeatmapWidget::class => true,
                TopDriversLeaderboardWidget::class => true,
            ],
        ],
    ],

    'roles' => [
        'SUPER_ADMIN' => [
            'columns' => ['default' => 1, 'sm' => 1, 'md' => 2, 'xl' => 4],
            'widgets' => [
                SuperAdminOverviewStats::class,
                RideStatsOverview::class,
                RideMapWidget::class,
                LiveRevenueTickerWidget::class,
                CommissionTodayWidget::class,
                DriverAvailabilityChart::class,
                DemandHeatmapWidget::class,
                NotificationsWidget::class,
                ActivityFeedWidget::class,
                SystemLogsWidget::class,
                RevenueOverTimeChartWidget::class,
                FraudRiskHeatmapWidget::class,
                TopDriversLeaderboardWidget::class,
            ],
            // Optional: map widget class => required permissions.
            // If empty, role alone controls visibility.
            'widget_permissions' => [
                SuperAdminOverviewStats::class => ['view users'],
                RideStatsOverview::class => ['view rides'],
                RideMapWidget::class => ['view rides'],
                LiveRevenueTickerWidget::class => ['view finances'],
                CommissionTodayWidget::class => ['view finances'],
                DriverAvailabilityChart::class => ['view rides'],
                DemandHeatmapWidget::class => ['view demand forecasts'],
                NotificationsWidget::class => ['view users'],
                ActivityFeedWidget::class => ['view users'],
                SystemLogsWidget::class => ['view users'],
                RevenueOverTimeChartWidget::class => ['view performance metrics'],
                FraudRiskHeatmapWidget::class => ['view performance metrics'],
                TopDriversLeaderboardWidget::class => ['view performance metrics'],
            ],
            // false = user needs any listed permission, true = user needs all.
            'widget_permissions_require_all' => false,
        ],
        'ADMIN' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                AdminOverviewStats::class,
                RideStatsOverview::class,
                RideMapWidget::class,
                DriverAvailabilityChart::class,
                DemandHeatmapWidget::class,
                NotificationsWidget::class,
                LatestRidesTable::class,
                ActivityFeedWidget::class,
            ],
            'widget_permissions' => [
                AdminOverviewStats::class => ['view users'],
                RideStatsOverview::class => ['view rides'],
                RideMapWidget::class => ['view rides'],
                DriverAvailabilityChart::class => ['view rides'],
                DemandHeatmapWidget::class => ['view demand forecasts'],
                NotificationsWidget::class => ['manage tickets'],
                LatestRidesTable::class => ['view rides'],
                ActivityFeedWidget::class => ['view users'],
            ],
            'widget_permissions_require_all' => false,
        ],
        'ACCOUNTANT' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                EscrowBalanceWidget::class,
                AccountantRevenueSummary::class,
                RideMapWidget::class,
                DemandHeatmapWidget::class,
                CommissionOverviewWidget::class,
                FraudAlertsWidget::class,
                DailyCommissionChartWidget::class,
                MonthlyEarningsChartWidget::class,
                TransactionsTableWidget::class,
                FinanceExportActionsWidget::class,
                NotificationsWidget::class,
            ],
            'widget_permissions' => [
                EscrowBalanceWidget::class => ['view finances'],
                AccountantRevenueSummary::class => ['view finances'],
                RideMapWidget::class => ['view rides'],
                DemandHeatmapWidget::class => ['view demand forecasts'],
                CommissionOverviewWidget::class => ['view finances'],
                FraudAlertsWidget::class => ['view finances'],
                DailyCommissionChartWidget::class => ['view finances'],
                MonthlyEarningsChartWidget::class => ['view finances'],
                TransactionsTableWidget::class => ['view finances'],
                FinanceExportActionsWidget::class => ['export finances'],
                NotificationsWidget::class => ['view finances'],
            ],
            'widget_permissions_require_all' => false,
        ],
        'OFFICER' => [
            'columns' => ['default' => 1, 'md' => 2, 'xl' => 3],
            'widgets' => [
                OfficerOverviewStats::class,
                RideMapWidget::class,
                DriverAvailabilityChart::class,
                DemandHeatmapWidget::class,
                NotificationsWidget::class,
                LatestRidesTable::class,
                ActivityFeedWidget::class,
            ],
            'widget_permissions' => [
                OfficerOverviewStats::class => ['view users'],
                RideMapWidget::class => ['view rides'],
                DriverAvailabilityChart::class => ['view rides'],
                DemandHeatmapWidget::class => ['view demand forecasts'],
                NotificationsWidget::class => ['manage tickets'],
                LatestRidesTable::class => ['view rides'],
                ActivityFeedWidget::class => ['view users'],
            ],
            'widget_permissions_require_all' => false,
        ],
        'DRIVER' => [
            'columns' => ['default' => 1, 'md' => 2],
            'widgets' => [
                DemandHeatmapWidget::class,
                RideStatsOverview::class,
                NotificationsWidget::class,
                LatestRidesTable::class,
                ActivityFeedWidget::class,
            ],
            'widget_permissions' => [],
            'widget_permissions_require_all' => false,
        ],
        'PASSENGER' => [
            'columns' => ['default' => 1, 'md' => 2],
            'widgets' => [
                DemandHeatmapWidget::class,
                RideStatsOverview::class,
                NotificationsWidget::class,
                ActivityFeedWidget::class,
            ],
            'widget_permissions' => [],
            'widget_permissions_require_all' => false,
        ],
    ],
];
