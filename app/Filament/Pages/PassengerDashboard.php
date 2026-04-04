<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Illuminate\Contracts\Support\Htmlable;

class PassengerDashboard extends BaseDashboard
{
    protected static string $routePath = '/passenger-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::PASSENGER;
    }

    public static function getNavigationLabel(): string
    {
        return 'Passenger Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-user-circle';
    }
}
