<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\BI\CommissionTodayWidget;
use App\Filament\Widgets\BI\FraudRiskHeatmapWidget;
use App\Filament\Widgets\BI\LiveRevenueTickerWidget;
use App\Filament\Widgets\BI\RevenueOverTimeChartWidget;
use App\Filament\Widgets\BI\TopDriversLeaderboardWidget;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class SuperDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

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

    public function getWidgets(): array
    {
        return RoleDashboardConfig::widgetsForRole(UserRole::SUPER_ADMIN->value);
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * @return array<class-string>
     */
    public function getOperationalWidgets(): array
    {
        $intelligence = $this->getIntelligenceWidgets();

        return array_values(array_filter(
            $this->getWidgets(),
            static fn (string $widget): bool => !in_array($widget, $intelligence, true)
        ));
    }

    /**
     * @return array<class-string>
     */
    public function getIntelligenceWidgets(): array
    {
        return [
            LiveRevenueTickerWidget::class,
            CommissionTodayWidget::class,
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
            'md' => 2,
            'xl' => 2,
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
