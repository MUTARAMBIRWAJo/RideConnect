<?php

namespace App\Filament\Resources\PerformanceMetricsResource\Pages;

use App\Filament\Resources\PerformanceMetricsResource;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceMetrics extends ListRecords
{
    protected static string $resource = PerformanceMetricsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Performance Metrics';
    }

    public function getSubheading(): ?string
    {
        return 'Trip response times, durations, and fare performance for recent operations.';
    }
}
