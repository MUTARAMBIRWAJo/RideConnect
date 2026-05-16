<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CorridorResource\Pages;
use App\Models\Corridor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CorridorResource extends Resource
{
    protected static ?string $model = Corridor::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?string $navigationLabel = 'Corridors';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(20),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('kinyarwanda_name')
                ->maxLength(255),
            Forms\Components\TextInput::make('start_zone_id')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('end_zone_id')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('base_fare')
                ->numeric()
                ->default(0),
            Forms\Components\TextInput::make('price_per_km')
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('name')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('kinyarwanda_name')
                ->label('Kinyarwanda')
                ->toggleable(),
            Tables\Columns\TextColumn::make('routes_count')
                ->counts('routes')
                ->label('Routes'),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->toggleable(isToggledHiddenByDefault: true),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCorridors::route('/'),
            'create' => Pages\CreateCorridor::route('/create'),
            'edit' => Pages\EditCorridor::route('/{record}/edit'),
        ];
    }
}
