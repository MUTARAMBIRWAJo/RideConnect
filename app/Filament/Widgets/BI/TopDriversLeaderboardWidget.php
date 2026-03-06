<?php

namespace App\Filament\Widgets\BI;

use App\Modules\Reporting\Services\ReportingService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopDriversLeaderboardWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '300s';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Top Drivers — This Month';

    public function table(Table $table): Table
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $rankings  = $reporting->getDriverRankings();

        $rows = collect($rankings)->map(fn ($r, $i) => [
            'rank'         => $i + 1,
            'driver_id'    => $r['driver_id'] ?? 'N/A',
            'driver_name'  => $r['driver_name'] ?? 'Unknown',
            'total_rides'  => number_format($r['total_rides'] ?? 0),
            'total_earned' => 'RWF ' . number_format($r['total_earned'] ?? 0),
            'avg_rating'   => number_format($r['avg_rating'] ?? 0, 2),
        ]);

        return $table
            ->query(
                // Use a raw in-memory approach; suppress actual DB query.
                \App\Models\Driver::query()->whereRaw('1=0')
            )
            ->columns([
                Tables\Columns\TextColumn::make('rank')->label('#'),
                Tables\Columns\TextColumn::make('driver_name')->label('Driver'),
                Tables\Columns\TextColumn::make('total_rides')->label('Rides'),
                Tables\Columns\TextColumn::make('total_earned')->label('Earned'),
                Tables\Columns\TextColumn::make('avg_rating')->label('Avg Rating'),
            ])
            ->emptyStateHeading('No ranking data yet')
            ->emptyStateDescription('Run the nightly ETL job to populate driver rankings.');
    }

    public function getTableRecords(): \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Database\Eloquent\Collection
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $rankings  = $reporting->getDriverRankings();

        return collect($rankings)->values();
    }
}
