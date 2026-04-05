<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Illuminate\Contracts\Support\Htmlable;

class OfficerDashboardV2 extends BaseDashboard
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/officer-dashboard-v2';

    protected static string $view = 'filament.pages.officer-dashboard-v2';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::OFFICER;
    }

    public static function getNavigationLabel(): string
    {
        return 'Officer Dashboard V2';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }
}
