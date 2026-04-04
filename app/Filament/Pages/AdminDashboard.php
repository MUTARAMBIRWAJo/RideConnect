<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Illuminate\Contracts\Support\Htmlable;

class AdminDashboard extends BaseDashboard
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/admin-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::ADMIN;
    }

    public static function getNavigationLabel(): string
    {
        return 'Admin Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cog-8-tooth';
    }
}
