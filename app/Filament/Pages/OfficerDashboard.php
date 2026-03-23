<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class OfficerDashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/officer-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Officer Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('officer');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('officer');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('officer');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Add officer-specific widgets here
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            // Add officer-specific widgets here
        ];
    }
}
