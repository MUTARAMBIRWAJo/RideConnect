<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class OfficerDashboardV2 extends Page
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/officer-dashboard-v2';

    protected static string $view = 'filament.pages.officer-dashboard-v2';

    public static function getNavigationLabel(): string
    {
        return 'Officer Dashboard V2';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $role = $user->role;
        if ($role instanceof UserRole) {
            return $role === UserRole::OFFICER;
        }

        return (string) $role === UserRole::OFFICER->value;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Officer Dashboard';
    }
}
