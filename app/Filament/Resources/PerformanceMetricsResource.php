<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use Filament\Resources\Resource;
use App\Filament\Resources\PerformanceMetricsResource\Pages;
use Filament\Tables;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PerformanceMetricsResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Performance Metrics';

    protected static ?string $navigationGroup = 'AI & Analytics';

    protected static ?string $modelLabel = 'Performance Metric';

    protected static ?string $pluralModelLabel = 'Performance Metrics';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->summarize([
                        Count::make()->label('Total trips'),
                    ]),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower(trim($state))) {
                        'completed' => 'success',
                        'started' => 'warning',
                        'accepted' => 'info',
                        'cancelled' => 'danger',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('passenger_id')
                    ->label('Passenger ID')
                    ->sortable(),
                TextColumn::make('driver_id')
                    ->label('Driver ID')
                    ->sortable(),
                TextColumn::make('response_time_minutes')
                    ->label('Response Time')
                    ->state(function (Trip $record): ?int {
                        if (!$record->requested_at || !$record->started_at) {
                            return null;
                        }

                        return Carbon::parse($record->started_at)->diffInMinutes(Carbon::parse($record->requested_at));
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? "{$state} min" : '—'),
                TextColumn::make('trip_duration_minutes')
                    ->label('Trip Duration')
                    ->state(function (Trip $record): ?int {
                        if (!$record->started_at || !$record->completed_at) {
                            return null;
                        }

                        return Carbon::parse($record->completed_at)->diffInMinutes(Carbon::parse($record->started_at));
                    })
                    ->formatStateUsing(fn ($state): string => $state !== null ? "{$state} min" : '—'),
                TextColumn::make('fare')
                    ->label('Fare')
                    ->money('RWF')
                    ->sortable()
                    ->summarize([
                        Sum::make()->label('Total fare')->money('RWF'),
                        Average::make()->label('Avg fare')->money('RWF'),
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'ACCEPTED' => 'Accepted',
                        'STARTED' => 'Started',
                        'COMPLETED' => 'Completed',
                        'CANCELLED' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('requested_window')
                    ->label('Requested Window')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPerformanceMetrics::route('/'),
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
            ->whereDate('requested_at', '>=', now()->subDays(90)->toDateString());
    }
}
