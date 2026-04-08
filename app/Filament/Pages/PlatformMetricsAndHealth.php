<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class PlatformMetricsAndHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Platform Metrics';

    protected static ?string $navigationGroup = 'Information Hub';

    protected static ?int $navigationSort = 202;

    protected static ?string $title = 'Platform Health & Metrics';

    protected static string $view = 'filament.pages.platform-metrics-and-health';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN', 'ACCOUNTANT'], true);
    }

    public function getTitle(): string
    {
        return 'Platform Health & Metrics';
    }

    public static function getNavigationLabel(): string
    {
        return 'Platform Metrics';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-chart-bar';
    }

    /** @return array<string, mixed> */
    public function getSystemMetrics(): array
    {
        return [
            'uptime' => 99.98,
            'api_response_time' => '145ms',
            'database_connections' => 42,
            'queue_jobs_pending' => 234,
            'cache_hit_rate' => 94.3,
            'error_rate' => 0.02,
        ];
    }

    /** @return array<string, mixed> */
    public function getBusinessMetrics(): array
    {
        return [
            'total_rides_today' => 3847,
            'total_revenue' => 'RWF 18,920,450',
            'active_drivers' => 892,
            'active_passengers' => 5423,
            'average_rating' => 4.75,
            'completion_rate' => 97.2,
        ];
    }
}
