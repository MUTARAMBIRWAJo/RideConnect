<?php

namespace App\Filament\Widgets\Dashboard;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemLogsWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.system-logs-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        if (!Schema::hasTable('activity_logs')) {
            return ['logs' => collect()];
        }

        $query = DB::table('activity_logs')->latest('id')->limit(8);

        $columns = collect(['action', 'description', 'created_at'])
            ->filter(fn (string $column) => Schema::hasColumn('activity_logs', $column))
            ->values()
            ->all();

        if (empty($columns)) {
            $columns = ['id'];
        }

        $logs = $query->get($columns);

        return compact('logs');
    }
}
