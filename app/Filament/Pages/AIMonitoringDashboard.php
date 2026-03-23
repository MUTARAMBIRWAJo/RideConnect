<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class AIMonitoringDashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;
    protected static ?string $navigationGroup = 'AI & Analytics';

    protected static ?int $navigationSort = 2;

    protected static string $routePath = '/ai-monitoring';

    public static function getNavigationLabel(): string
    {
        return 'AI Monitoring Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cpu-chip';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user || !isset($user->role)) {
            return false;
        }

        $role = $user->role;
        $value = is_object($role) && isset($role->value) ? strtolower((string) $role->value) : strtolower((string) $role);
        $name = is_object($role) && isset($role->name) ? strtolower((string) $role->name) : $value;

        return in_array($value, ['super_admin', 'admin'], true)
            || in_array($name, ['super_admin', 'admin'], true);
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\AIModelAccuracyWidget::class,
            \App\Filament\Widgets\AIDemandHeatmapWidget::class,
            \App\Filament\Widgets\AIDriverDistributionWidget::class,
            \App\Filament\Widgets\AIPredictionLogsWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
