<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\TaxRuleResource\Pages;
use App\Models\TaxRule;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxRuleResource extends Resource
{
    protected static ?string $model          = TaxRule::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $label          = 'Tax Rule';
    protected static ?int    $navigationSort = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(UserRole::SUPER_ADMIN->value) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('applies_to')
                ->label('Applies To')
                ->options([
                    'ride'       => 'Ride Fare',
                    'commission' => 'Commission',
                    'payout'     => 'Driver Payout',
                ])
                ->required(),

            TextInput::make('tax_name')
                ->label('Tax Name')
                ->required()
                ->maxLength(100),

            TextInput::make('tax_code')
                ->label('Tax Code')
                ->required()
                ->maxLength(20)
                ->placeholder('VAT, WHT_COMMISSION, WHT_PAYOUT'),

            TextInput::make('rate')
                ->label('Rate (%)')
                ->numeric()
                ->step(0.01)
                ->minValue(0)
                ->maxValue(100)
                ->required(),

            TextInput::make('jurisdiction')
                ->label('Jurisdiction')
                ->default('RW')
                ->required(),

            DatePicker::make('effective_from')
                ->label('Effective From')
                ->required(),

            DatePicker::make('effective_to')
                ->label('Effective To')
                ->nullable(),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tax_name')->label('Name')->searchable(),
                TextColumn::make('tax_code')->label('Code')->badge(),
                TextColumn::make('applies_to')->label('Applies To')->badge(),
                TextColumn::make('rate')
                    ->label('Rate')
                    ->formatStateUsing(fn ($state) => $state . '%'),
                TextColumn::make('jurisdiction')->label('Jurisdiction'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('effective_from')->label('From')->date(),
                TextColumn::make('effective_to')->label('To')->date()->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('applies_to')
                    ->options([
                        'ride'       => 'Ride',
                        'commission' => 'Commission',
                        'payout'     => 'Payout',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('applies_to');
    }

    public static function getRelationManagers(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTaxRules::route('/'),
            'create' => Pages\CreateTaxRule::route('/create'),
            'edit'   => Pages\EditTaxRule::route('/{record}/edit'),
        ];
    }
}
