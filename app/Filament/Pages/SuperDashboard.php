<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\BI\CommissionTodayWidget;
use App\Filament\Widgets\BI\FraudRiskHeatmapWidget;
use App\Filament\Widgets\BI\LiveRevenueTickerWidget;
use App\Filament\Widgets\BI\RevenueOverTimeChartWidget;
use App\Filament\Widgets\BI\TopDriversLeaderboardWidget;
use App\Filament\Widgets\Dashboard\ActivityFeedWidget;
use App\Filament\Widgets\Dashboard\FinancialMatrixWidget;
use App\Filament\Widgets\Dashboard\NotificationsWidget;
use App\Filament\Widgets\Dashboard\OperationsIntelligenceWidget;
use App\Filament\Widgets\Dashboard\SuperAdminOverviewStats;
use App\Filament\Widgets\Dashboard\SystemLogsWidget;
use App\Filament\Widgets\Dashboard\TransactionsTableWidget;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Widgets\DemandHeatmapWidget;
use App\Filament\Widgets\DriverAvailabilityChart;
use App\Filament\Widgets\LatestRidesTable;
use App\Filament\Widgets\RideMapWidget;
use App\Filament\Widgets\RideStatsOverview;
use App\Models\User;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class SuperDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static string $routePath = '/super-dashboard';

    protected static string $view = 'filament.pages.super-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Super Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::userHasRole($user, 'Super_admin', UserRole::SUPER_ADMIN);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
            '2xl' => 4,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getExecutiveWidgets(): array
    {
        return [
            SuperAdminOverviewStats::class,
            LiveRevenueTickerWidget::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getMapWidgets(): array
    {
        return [
            RideMapWidget::class,
            DemandHeatmapWidget::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getChartWidgets(): array
    {
        return [
            FinancialMatrixWidget::class,
            RideStatsOverview::class,
            DriverAvailabilityChart::class,
            OperationsIntelligenceWidget::class,
            RevenueOverTimeChartWidget::class,
            FraudRiskHeatmapWidget::class,
            CommissionTodayWidget::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getOperationalTableWidgets(): array
    {
        return [
            LatestRidesTable::class,
            TransactionsTableWidget::class,
            TopDriversLeaderboardWidget::class,
            ActivityFeedWidget::class,
            NotificationsWidget::class,
            SystemLogsWidget::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getIntelligenceWidgets(): array
    {
        return [
            RevenueOverTimeChartWidget::class,
            FraudRiskHeatmapWidget::class,
            TopDriversLeaderboardWidget::class,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getIntelligenceColumns(): array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getExecutiveColumns(): array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
            '2xl' => 4,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getMapColumns(): array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 1,
            'lg' => 1,
            'xl' => 1,
            '2xl' => 1,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getChartColumns(): array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
            '2xl' => 4,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getOperationalTableColumns(): array
    {
        return [
            'default' => 1,
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 3,
            '2xl' => 4,
        ];
    }

    public function canManageUsers(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $isSuperAdminByRoleEnum = ($user->role?->value ?? $user->role) === UserRole::SUPER_ADMIN->value;
        $isSuperAdminBySpatie = method_exists($user, 'hasRole')
            ? ($user->hasRole('Super_admin') || $user->hasRole('SUPER_ADMIN'))
            : false;

        return $isSuperAdminByRoleEnum || $isSuperAdminBySpatie || ($user->can('view users') ?? false);
    }

    /**
     * @return array<string, int>
     */
    public function getUserManagementStats(): array
    {
        return [
            'total' => (int) User::query()->count(),
            'pending' => (int) User::query()->where('is_approved', false)->count(),
            'managers' => (int) User::query()->whereIn('role', UserRole::managerRoles())->count(),
            'mobile' => (int) User::query()->whereIn('role', UserRole::mobileUserRoles())->count(),
        ];
    }
}
