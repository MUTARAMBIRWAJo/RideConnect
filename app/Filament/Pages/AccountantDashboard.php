<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AccountantDashboard extends Page
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/accountant-dashboard';

    protected static string $view = 'filament.pages.accountant-dashboard-static';

    public static function getNavigationLabel(): string
    {
        return 'Accountant Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-banknotes';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $role = $user->role;
        if ($role instanceof UserRole) {
            return $role === UserRole::ACCOUNTANT;
        }

        return (string) $role === UserRole::ACCOUNTANT->value;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Accountant Dashboard';
    }

}
