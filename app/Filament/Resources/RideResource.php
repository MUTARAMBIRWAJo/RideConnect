<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RideResource\Pages;
use App\Models\Ride;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?string $navigationLabel = 'Rides';

    protected static ?string $modelLabel = 'Ride';

    protected static ?string $pluralModelLabel = 'Rides';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('driver_id')
                    ->relationship('driver', 'id')
                    ->required(),
                Forms\Components\Select::make('vehicle_id')
                    ->relationship('vehicle', 'id'),
                Forms\Components\TextInput::make('origin_address')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('origin_lat')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('origin_lng')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('destination_address')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('destination_lat')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('destination_lng')
                    ->required()
                    ->numeric(),
                Forms\Components\DateTimePicker::make('departure_time')
                    ->required(),
                Forms\Components\DateTimePicker::make('arrival_time_estimated'),
                Forms\Components\TextInput::make('available_seats')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('price_per_seat')
                    ->required()
                    ->numeric()
                    ->step(0.01),
                Forms\Components\TextInput::make('currency')
                    ->required()
                    ->maxLength(3)
                    ->default('RWF'),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Select::make('status')
                    ->options([
                        'available' => 'Available',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('available')
                    ->required(),
                Forms\Components\Select::make('ride_type')
                    ->options([
                        ' intercity' => 'Intercity',
                        'local' => 'Local',
                    ]),
                Forms\Components\Checkbox::make('luggage_allowed'),
                Forms\Components\Checkbox::make('pets_allowed'),
                Forms\Components\Checkbox::make('smoking_allowed'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver.user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('origin_address')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('destination_address')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('departure_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_seats')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_seat')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'in_progress' => 'warning',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('ride_type')
                    ->options([
                        'intercity' => 'Intercity',
                        'local' => 'Local',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Ride $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Ride $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('manage rides') ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRides::route('/'),
            'create' => Pages\CreateRide::route('/create'),
            'edit' => Pages\EditRide::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view rides') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage rides') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage rides') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage rides') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
