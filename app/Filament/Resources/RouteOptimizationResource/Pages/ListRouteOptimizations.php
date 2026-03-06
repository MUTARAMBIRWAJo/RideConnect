<?php

namespace App\Filament\Resources\RouteOptimizationResource\Pages;

use App\Filament\Resources\RouteOptimizationResource;
use Filament\Resources\Pages\ListRecords;

class ListRouteOptimizations extends ListRecords
{
    protected static string $resource = RouteOptimizationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Route Optimization';
    }

    public function getSubheading(): ?string
    {
        return 'Upcoming route planning signals based on bookings, capacity, and utilization.';
    }
}
