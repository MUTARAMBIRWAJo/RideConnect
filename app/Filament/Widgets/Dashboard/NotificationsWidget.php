<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationsWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.notifications-widget';

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
            'items' => [
                [
                    'label' => 'Pending Bookings',
                    'value' => $this->countByStatus('bookings', ['pending', 'PENDING']),
                ],
                [
                    'label' => 'Open Tickets',
                    'value' => $this->countByStatus('tickets', ['open', 'OPEN']),
                ],
                [
                    'label' => 'Pending Trips',
                    'value' => $this->countByStatus('trips', ['pending', 'PENDING']),
                ],
            ],
        ];
    }

    private function countByStatus(string $table, array $statuses): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'status')) {
            return 0;
        }

        return DB::table($table)->whereIn('status', $statuses)->count();
    }
}
