<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Models\Ride;
use App\Models\Ticket;
use Filament\Widgets\Widget;

class OfficerSupportCasesTableWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.officer-support-cases-table-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '75s');
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
            return $user->can('manage tickets') || $user->can('view rides');
        }

        return false;
    }

    protected function getViewData(): array
    {
        $openTickets = Ticket::query()
            ->with('trip:id,passenger_id,pickup_location,dropoff_location')
            ->whereIn('status', ['OPEN', 'open', 'PENDING', 'pending'])
            ->latest('id')
            ->limit(10)
            ->get();

        $cancelledRides = Ride::query()
            ->with(['driver.user:id,name'])
            ->whereIn('status', ['cancelled', 'CANCELLED'])
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'openTickets' => $openTickets,
            'cancelledRides' => $cancelledRides,
        ];
    }
}
