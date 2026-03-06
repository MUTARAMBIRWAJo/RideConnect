<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TripResource\Pages;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Trips';

    protected static ?string $modelLabel = 'Trip';

    protected static ?string $pluralModelLabel = 'Trips';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Trip Details')
                    ->schema([
                        Forms\Components\Select::make('passenger_id')
                            ->relationship('passenger', 'first_name')
                            ->label('Passenger'),
                        Forms\Components\TextInput::make('driver_id')
                            ->numeric(),
                        Forms\Components\TextInput::make('pickup_location')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('dropoff_location')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'PENDING' => 'Pending',
                                'ACCEPTED' => 'Accepted',
                                'STARTED' => 'Started',
                                'COMPLETED' => 'Completed',
                                'CANCELLED' => 'Cancelled',
                            ])
                            ->required(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Timing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('requested_at'),
                        Forms\Components\DateTimePicker::make('started_at'),
                        Forms\Components\DateTimePicker::make('completed_at'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('passenger.full_name')
                    ->label('Passenger')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('mobile_users as passengers', 'trips.passenger_id', '=', 'passengers.id')
                            ->orderBy('passengers.first_name', $direction)
                            ->orderBy('passengers.last_name', $direction)
                            ->select('trips.*');
                    }),
                Tables\Columns\TextColumn::make('driver_id')
                    ->label('Driver ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('pickup_location')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('dropoff_location')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower(trim($state))) {
                        'completed' => 'success',
                        'started' => 'warning',
                        'in_progress' => 'warning',
                        'cancelled' => 'danger',
                        'pending' => 'info',
                        'accepted' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'view' => Pages\ViewTrip::route('/{record}'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
