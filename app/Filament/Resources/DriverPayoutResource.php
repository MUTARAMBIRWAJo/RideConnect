<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\DriverPayoutResource\Pages;
use App\Models\DriverPayout;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverPayoutResource extends Resource
{
    protected static ?string $model = DriverPayout::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Driver Payouts';

    protected static ?string $navigationGroup = 'Passengers';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('driver_id')
                ->relationship('driver', 'id')
                ->required()
                ->disabled(),
            Forms\Components\DatePicker::make('payout_date')
                ->required()
                ->disabled(),
            Forms\Components\TextInput::make('total_income')
                ->numeric()
                ->prefix('RWF')
                ->required()
                ->disabled(),
            Forms\Components\TextInput::make('commission_amount')
                ->numeric()
                ->prefix('RWF')
                ->required()
                ->disabled(),
            Forms\Components\TextInput::make('payout_amount')
                ->numeric()
                ->prefix('RWF')
                ->required()
                ->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'processed' => 'Processed',
                ])
                ->required(),
            Forms\Components\DateTimePicker::make('processed_at')
                ->disabled(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('driver.user.name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payout_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_income')
                    ->label('Total Income')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('Commission (8%)')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payout_amount')
                    ->label('Net Payout (92%)')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'processed' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('processor.name')
                    ->label('Processed By')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processed' => 'Processed',
                    ]),
                Tables\Filters\Filter::make('payout_date')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('payout_date', '>=', $date))
                            ->when($data['to'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('payout_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('mark_processed')
                    ->label('Mark Processed')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (DriverPayout $record): bool => $record->status !== 'processed')
                    ->requiresConfirmation()
                    ->action(function (DriverPayout $record): void {
                        $record->update([
                            'status' => 'processed',
                            'processed_at' => now(),
                            'processed_by' => auth()->id(),
                        ]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_selected_processed')
                        ->label('Mark Selected Processed')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            DriverPayout::query()
                                ->whereIn('id', $records->pluck('id'))
                                ->where('status', '!=', 'processed')
                                ->update([
                                    'status' => 'processed',
                                    'processed_at' => now(),
                                    'processed_by' => auth()->id(),
                                ]);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverPayouts::route('/'),
            'view' => Pages\ViewDriverPayout::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return (auth()->user()?->role?->value ?? auth()->user()?->role) === UserRole::ACCOUNTANT->value;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
