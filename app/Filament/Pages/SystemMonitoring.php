<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\SuperAdmin\SystemHealthWidget;
use Illuminate\Contracts\Support\Htmlable;

class SystemMonitoring extends BaseDashboard
{
    protected static string $routePath = '/system-monitoring';

    protected static string $view = 'filament.pages.system-monitoring';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::SUPER_ADMIN;
    }

    public static function getNavigationLabel(): string
    {
        return 'System Monitoring';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cpu-chip';
    }

    public function getWidgets(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SystemHealthWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 1,
        ];
    }
}
