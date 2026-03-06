<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use Filament\Resources\Resource;
use App\Filament\Resources\DemandForecastResource\Pages;
use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ride;

class DemandForecastResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Demand Forecast';

    protected static ?string $navigationGroup = 'AI & Analytics';

    protected static ?string $modelLabel = 'Demand Forecast';

    protected static ?string $pluralModelLabel = 'Demand Forecasts';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('bookings'))
            ->defaultSort('departure_time', 'asc')
            ->columns([
                TextColumn::make('departure_time')
                    ->label('Departure')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->summarize([
                        Count::make()->label('Total rides'),
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower(trim($state))) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        'scheduled' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('bookings_count')
                    ->label('Booked Seats')
                    ->sortable()
                    ->summarize([
                        Sum::make()->label('Total booked'),
                        Average::make()->label('Avg booked / ride')->numeric(decimalPlaces: 1),
                    ]),
                TextColumn::make('available_seats')
                    ->label('Seat Capacity')
                    ->sortable()
                    ->summarize([
                        Sum::make()->label('Total capacity'),
                        Average::make()->label('Avg capacity / ride')->numeric(decimalPlaces: 1),
                    ]),
                TextColumn::make('demand_fill_rate')
                    ->label('Fill Rate')
                    ->state(function (Ride $record): float {
                        if ($record->available_seats <= 0) {
                            return 0;
                        }

                        return round(($record->bookings_count / $record->available_seats) * 100, 1);
                    })
                    ->suffix('%')
                    ->color(fn (float $state): string => match (true) {
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('price_per_seat')
                    ->label('Avg Ticket Price')
                    ->formatStateUsing(fn ($state, Ride $record): string => number_format((float) $state, 2) . ' ' . strtoupper((string) ($record->currency ?? 'RWF')))
                    ->sortable()
                    ->summarize([
                        Average::make()->label('Avg ticket price')->numeric(decimalPlaces: 2),
                    ]),
                TextColumn::make('expected_revenue')
                    ->label('Projected Revenue')
                    ->state(fn (Ride $record): float => round(((float) $record->price_per_seat) * (int) $record->bookings_count, 2))
                    ->formatStateUsing(fn ($state, Ride $record): string => number_format((float) $state, 2) . ' ' . strtoupper((string) ($record->currency ?? 'RWF'))),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('departure_window')
                    ->label('Departure Window')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('departure_time', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('departure_time', '<=', $date),
                            );
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemandForecasts::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        $role = auth()->user()?->role;

        if ($role instanceof UserRole) {
            return in_array($role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true);
        }

        return in_array((string) $role, [UserRole::SUPER_ADMIN->value, UserRole::ADMIN->value], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereDate('departure_time', '>=', now()->toDateString());
    }
}
