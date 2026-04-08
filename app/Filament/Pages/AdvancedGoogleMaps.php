<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AdvancedGoogleMaps extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Advanced Maps & Analytics';

    protected static ?string $navigationGroup = 'Information Hub';

    protected static ?int $navigationSort = 201;

    protected static ?string $title = 'Advanced Maps & Real-Time Tracking';

    protected static string $view = 'filament.pages.advanced-google-maps';

    public bool $showRealTimeTracking = true;

    public bool $showDemandHeatmap = true;

    public bool $showDriverDensity = true;

    public bool $showIncidentMap = false;

    public string $selectedTimeRange = '24h';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return in_array($user->role?->value ?? $user->role, ['SUPER_ADMIN', 'ADMIN', 'OFFICER'], true);
    }

    public function getTitle(): string
    {
        return 'Advanced Maps & Real-Time Tracking';
    }

    public static function getNavigationLabel(): string
    {
        return 'Advanced Maps & Analytics';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-map';
    }

    /** @return array<string, mixed> */
    public function getMapAnalytics(): array
    {
        return [
            'active_vehicles' => 342,
            'active_rides' => 89,
            'peak_zones' => ['Downtown', 'Airport', 'Business District'],
            'average_wait_time' => '4m 23s',
            'system_efficiency' => 94.2,
            'coverage_percentage' => 98.7,
        ];
    }

    public function updateMapFilters(): void
    {
        // Live update map based on selected filters
    }
}
