<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\FinanceResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FinanceResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Finance';

    protected static ?string $modelLabel = 'Payment';

    protected static ?string $pluralModelLabel = 'Finance & Payments';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationGroup = 'Passengers';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\Select::make('booking_id')
                            ->relationship('booking', 'id'),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name'),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('RWF '),
                        Forms\Components\TextInput::make('platform_fee')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('RWF '),
                        Forms\Components\TextInput::make('driver_amount')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('RWF '),
                    ])->columns(3),
                
                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\TextInput::make('currency')
                            ->maxLength(3)
                            ->default('RWF'),
                        Forms\Components\TextInput::make('payment_method')
                            ->maxLength(50),
                        Forms\Components\TextInput::make('transaction_id')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('supabase_payment_id')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ]),
                    ])->columns(3),
                
                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\DateTimePicker::make('paid_at'),
                        Forms\Components\DateTimePicker::make('refunded_at'),
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
                Tables\Columns\TextColumn::make('booking.id')
                    ->label('Booking ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('RWF')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('platform_fee')
                    ->money('RWF')
                    ->label('Platform Fee')
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver_amount')
                    ->money('RWF')
                    ->label('Driver Amount')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable(),
                Tables\Columns\TextColumn::make('transaction_id')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                    }),
                Tables\Columns\TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\Filter::make('paid_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('paid_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Payment $record): bool => static::canEdit($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(PaymentExporter::class),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('export finances') ?? false),
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $role = auth()->user()?->role;

        if ($role instanceof UserRole) {
            return in_array($role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT], true);
        }

        return in_array((string) $role, [UserRole::SUPER_ADMIN->value, UserRole::ACCOUNTANT->value], true);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
