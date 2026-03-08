<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityFeedWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.activity-feed-widget';

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md' => 1,
        'xl' => 1,
    ];

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingInterval();
    }

    protected function getViewData(): array
    {
        return [
            'activities' => $this->latestActivity(),
        ];
    }

    private function latestActivity(): Collection
    {
        $items = collect();

        if (Schema::hasTable('rides')) {
            $rideItems = DB::table('rides')
                ->select(['id', 'status', 'created_at'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($ride) => [
                    'title' => 'Ride #' . $ride->id,
                    'description' => 'Status: ' . ($ride->status ?? 'unknown'),
                    'time' => $ride->created_at ? Carbon::parse($ride->created_at)->diffForHumans() : 'just now',
                ]);

            $items = $items->merge($rideItems);
        }

        if (Schema::hasTable('bookings')) {
            $bookingItems = DB::table('bookings')
                ->select(['id', 'status', 'created_at'])
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($booking) => [
                    'title' => 'Booking #' . $booking->id,
                    'description' => 'Status: ' . ($booking->status ?? 'unknown'),
                    'time' => $booking->created_at ? Carbon::parse($booking->created_at)->diffForHumans() : 'just now',
                ]);

            $items = $items->merge($bookingItems);
        }

        return $items->take(8)->values();
    }
}
