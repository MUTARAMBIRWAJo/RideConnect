<?php

namespace App\Filament\Resources\DemandForecastResource\Pages;

use App\Filament\Resources\DemandForecastResource;
use Filament\Resources\Pages\ListRecords;

class ListDemandForecasts extends ListRecords
{
    protected static string $resource = DemandForecastResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Demand Forecast';
    }

    public function getSubheading(): ?string
    {
        return 'Upcoming rides demand outlook based on bookings, seat capacity, and projected revenue.';
    }
}
