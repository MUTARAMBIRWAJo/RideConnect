<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Trip;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Schema;

class OfficerMatchingQueueTableWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.officer-matching-queue-table-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '60s');
    }

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if (method_exists($user, 'can')) {
            return $user->can('view rides') || $user->can('manage rides');
        }

        return false;
    }

    protected function getViewData(): array
    {
        $pendingBookings = Booking::query()
            ->with([
                'user:id,name',
                'ride:id,origin_address,destination_address,departure_time,driver_id,status',
            ])
            ->whereIn('status', ['pending', 'confirmed'])
            ->latest('id')
            ->limit(10)
            ->get();

        $pendingTrips = Trip::query()
            ->with('passenger:id,first_name,last_name')
            ->whereIn('status', ['PENDING', 'pending'])
            ->latest('id')
            ->limit(10)
            ->get();

        $activeDriverCount = $this->activeDriverCount();

        return [
            'pendingBookings' => $pendingBookings,
            'pendingTrips' => $pendingTrips,
            'activeDriverCount' => $activeDriverCount,
        ];
    }

    private function activeDriverCount(): int
    {
        if (Schema::hasColumn('drivers', 'is_online')) {
            return Driver::where('is_online', true)->count();
        }

        return Driver::whereIn('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])->count();
    }
}
