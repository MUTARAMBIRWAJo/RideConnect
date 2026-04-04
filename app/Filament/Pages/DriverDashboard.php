<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Illuminate\Contracts\Support\Htmlable;

class DriverDashboard extends BaseDashboard
{
    protected static string $routePath = '/driver-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::DRIVER;
    }

    public static function getNavigationLabel(): string
    {
        return 'Driver Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-truck';
    }
}
