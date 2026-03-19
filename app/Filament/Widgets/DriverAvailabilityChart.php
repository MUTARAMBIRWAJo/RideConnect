<?php

namespace App\Filament\Widgets;

use App\Filament\Support\RoleDashboardConfig;
use Filament\Widgets\Widget;
use App\Models\Driver;
use Illuminate\Support\Facades\Schema;

class DriverAvailabilityChart extends Widget
{
    protected static string $view = 'filament.widgets.driver-availability-chart';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '120s');
    }

    protected function getViewData(): array
    {
        try {
            // Prefer explicit status columns if present
            if (Schema::hasColumn('drivers', 'status') && Driver::where('status', 'available')->exists()) {
                $available = Driver::where('status', 'available')->count();
                $busy = Driver::where('status', 'busy')->count();
                $offline = Driver::where('status', 'offline')->count();
                $availableDrivers = Driver::with(['user', 'vehicles'])
                    ->where('status', 'available')
                    ->get();
            } elseif (Schema::hasColumn('drivers', 'is_available') && Schema::hasColumn('drivers', 'is_online')) {
                $available = Driver::where('is_available', true)->count();
                $offline = Driver::where('is_online', false)->count();
                $busy = max(0, Driver::count() - $available - $offline);
                $availableDrivers = Driver::with(['user', 'vehicles'])
                    ->where('is_available', true)
                    ->get();
            } else {
                $available = Driver::whereIn('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])->count();
                $busy = 0;
                $offline = Driver::whereIn('status', ['pending', 'PENDING', 'suspended', 'SUSPENDED'])->count();
                $availableDrivers = Driver::with(['user', 'vehicles'])
                    ->whereIn('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])
                    ->get();
            }
        } catch (\Throwable $e) {
            $available = $busy = $offline = 0;
            $availableDrivers = collect();
        }

        return compact('available', 'busy', 'offline', 'availableDrivers');
    }
}
