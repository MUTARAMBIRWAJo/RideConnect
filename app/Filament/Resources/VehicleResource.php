<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Vehicles';

    protected static ?string $navigationGroup = 'Fleet & Drivers';

    protected static ?string $modelLabel = 'Vehicle';

    protected static ?string $pluralModelLabel = 'Vehicles';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Vehicle Information')
                    ->schema([
                        Forms\Components\Select::make('driver_id')
                            ->relationship('driver', 'id')
                            ->label('Driver'),
                        Forms\Components\TextInput::make('make')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('model')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('year')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(date('Y') + 1),
                        Forms\Components\TextInput::make('license_plate')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('color')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('vin')
                            ->maxLength(50),
                    ])->columns(3),
                
                Forms\Components\Section::make('Capacity & Status')
                    ->schema([
                        Forms\Components\TextInput::make('seats')
                            ->numeric()
                            ->default(4),
                        Forms\Components\Select::make('vehicle_type')
                            ->options([
                                'sedan' => 'Sedan',
                                'suv' => 'SUV',
                                'van' => 'Van',
                                'pickup' => 'Pickup',
                                'minibus' => 'Minibus',
                            ]),
                        Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'in_use' => 'In Use',
                                'maintenance' => 'Maintenance',
                                'retired' => 'Retired',
                            ])
                            ->default('available'),
                    ])->columns(3),
                
                Forms\Components\Section::make('Features')
                    ->schema([
                        Forms\Components\Checkbox::make('air_conditioning'),
                        Forms\Components\Checkbox::make('wifi'),
                        Forms\Components\Checkbox::make('music_system'),
                        Forms\Components\Checkbox::make('leather_seats'),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('driver.user.name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('make')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('model')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year'),
                Tables\Columns\TextColumn::make('license_plate')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('color')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('seats')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicle_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'in_use' => 'warning',
                        'maintenance' => 'danger',
                        'retired' => 'gray',
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
                        'in_use' => 'In Use',
                        'maintenance' => 'Maintenance',
                        'retired' => 'Retired',
                    ]),
                Tables\Filters\SelectFilter::make('vehicle_type')
                    ->options([
                        'sedan' => 'Sedan',
                        'suv' => 'SUV',
                        'van' => 'Van',
                        'pickup' => 'Pickup',
                        'minibus' => 'Minibus',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Vehicle $record): bool => static::canEdit($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('manage rides') ?? false),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => static::canCreate()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'view' => Pages\ViewVehicle::route('/{record}'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
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
