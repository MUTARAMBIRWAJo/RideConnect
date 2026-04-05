<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\Dashboard\OperationalDashboardService;
use Filament\Widgets\Widget;

class RecentActivityWidget extends Widget
{
    protected static string $view = 'filament.widgets.super-admin.recent-activity-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'items' => app(OperationalDashboardService::class)->recentActivity(20),
        ];
    }
}
