<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Illuminate\Contracts\Support\Htmlable;

class AccountantDashboard extends BaseDashboard
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/accountant-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::ACCOUNTANT;
    }

    public static function getNavigationLabel(): string
    {
        return 'Accountant Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-banknotes';
    }
}
