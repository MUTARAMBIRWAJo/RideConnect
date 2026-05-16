<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RouteResource\Pages;
use App\Models\Corridor;
use App\Models\TransportRoute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RouteResource extends Resource
{
    protected static ?string $model = TransportRoute::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?string $navigationLabel = 'Routes';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('corridor_id')
                ->required()
                ->searchable()
                ->options(fn (): array => Corridor::query()
                    ->orderBy('code')
                    ->get()
                    ->mapWithKeys(fn (Corridor $corridor): array => [
                        $corridor->id => trim('Corridor '.($corridor->code ?? $corridor->id).' - '.$corridor->name),
                    ])
                    ->all()),
            Forms\Components\TextInput::make('route_code')
                ->required()
                ->maxLength(50),
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('via')
                ->maxLength(255),
            Forms\Components\TextInput::make('origin')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('destination')
                ->required()
                ->maxLength(255),
            Forms\Components\Toggle::make('is_active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('route_code')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('name')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('corridor.code')
                ->label('Corridor')
                ->formatStateUsing(fn ($state) => $state ? 'Corridor '.$state : 'Unknown'),
            Tables\Columns\IconColumn::make('is_active')
                ->boolean()
                ->label('Active'),
            Tables\Columns\TextColumn::make('origin')
                ->toggleable(),
            Tables\Columns\TextColumn::make('destination')
                ->toggleable(),
            Tables\Columns\TextColumn::make('via')
                ->toggleable(),
        ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoutes::route('/'),
            'create' => Pages\CreateRoute::route('/create'),
            'edit' => Pages\EditRoute::route('/{record}/edit'),
        ];
    }
}
