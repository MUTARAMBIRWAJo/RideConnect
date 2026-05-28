<?php

namespace App\Filament\Widgets\BI;

use App\Filament\Support\RoleDashboardConfig;
use App\Models\Driver;
use App\Modules\Reporting\Services\ReportingService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class TopDriversLeaderboardWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Drivers — This Month';

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '600s');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Use a raw in-memory approach; suppress actual DB query.
                Driver::query()->whereRaw('1=0')
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

    public function getTableRecords(): Paginator|EloquentCollection
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $rankings = $reporting->getDriverRankings();

        $records = collect($rankings)
            ->values()
            ->map(function (array $row, int $index): Driver {
                $model = new Driver;

                // Feed table rows from warehouse projections while honoring Eloquent return type.
                $model->forceFill([
                    'rank' => $index + 1,
                    'driver_id' => $row['driver_id'] ?? '—',
                    'driver_name' => $row['driver_name'] ?? 'Unknown',
                    'total_rides' => number_format((int) ($row['total_rides'] ?? 0)),
                    'total_earned' => 'RWF '.number_format((float) ($row['total_earned'] ?? 0)),
                    'avg_rating' => number_format((float) ($row['avg_rating'] ?? 0), 2),
                ]);

                return $model;
            })
            ->all();

        return new EloquentCollection($records);
    }
}
