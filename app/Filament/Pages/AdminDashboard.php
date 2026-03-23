<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use Illuminate\Contracts\Support\Htmlable;

class AdminDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

    protected static string $routePath = '/admin-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Admin Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cog-8-tooth';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::userHasRole($user, 'Admin', UserRole::ADMIN);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return RoleDashboardConfig::widgetsForRole(UserRole::ADMIN->value);
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(UserRole::ADMIN->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return [
            'riderStats' => $this->getOptimizedRiderStats(),
        ];
    }

    /**
     * Optimized single query for rider stats to avoid multiple DB hits
     */
    private function getOptimizedRiderStats(): array
    {
        $stats = \DB::selectOne("
            SELECT
                COUNT(*) FILTER (WHERE status IN ('in_progress', 'accepted')) as active_rides,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE) as rides_today,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE - INTERVAL '1 day') as rides_yesterday,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE - INTERVAL '2 days') as rides_day_before,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE - INTERVAL '3 days') as rides_three_days_ago,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE - INTERVAL '4 days') as rides_four_days_ago,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE - INTERVAL '5 days') as rides_five_days_ago,
                COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE - INTERVAL '6 days') as rides_six_days_ago
            FROM rides
        ");

        return [
            'active_rides' => (int) $stats->active_rides,
            'rides_today' => (int) $stats->rides_today,
            'rides_yesterday' => (int) $stats->rides_yesterday,
            'rides_day_before' => (int) $stats->rides_day_before,
            'rides_three_days_ago' => (int) $stats->rides_three_days_ago,
            'rides_four_days_ago' => (int) $stats->rides_four_days_ago,
            'rides_five_days_ago' => (int) $stats->rides_five_days_ago,
            'rides_six_days_ago' => (int) $stats->rides_six_days_ago,
        ];
    }
}
