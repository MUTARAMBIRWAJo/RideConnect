<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Ride;
use Illuminate\Support\Str;

class LatestRidesTable extends Widget
{
    protected static string $view = 'filament.widgets.latest-rides-table';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        try {
            $rides = Ride::with(['driver', 'passenger'])->latest('created_at')->take(8)->get();
        } catch (\Throwable $e) {
            try {
                $rides = Ride::latest('created_at')->take(8)->get();
            } catch (\Throwable $e) {
                $rides = collect();
            }
        }

        return compact('rides');
    }
}
