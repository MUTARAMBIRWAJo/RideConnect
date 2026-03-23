<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class AccountantDashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/accountant-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Accountant Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-banknotes';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('accountant');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('accountant');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('accountant');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Add accountant-specific widgets here
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Add accountant-specific widgets here
        ];
    }
}
