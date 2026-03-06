<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $modelLabel = 'Booking';

    protected static ?string $pluralModelLabel = 'Bookings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\Select::make('ride_id')
                    ->relationship('ride', 'id')
                    ->required(),
                Forms\Components\TextInput::make('seats_booked')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('total_price')
                    ->required()
                    ->numeric()
                    ->step(0.01),
                Forms\Components\TextInput::make('currency')
                    ->required()
                    ->maxLength(3)
                    ->default('RWF'),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ])
                    ->default('pending')
                    ->required(),
                Forms\Components\TextInput::make('pickup_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('pickup_lat')
                    ->numeric(),
                Forms\Components\TextInput::make('pickup_lng')
                    ->numeric(),
                Forms\Components\TextInput::make('dropoff_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('dropoff_lat')
                    ->numeric(),
                Forms\Components\TextInput::make('dropoff_lng')
                    ->numeric(),
                Forms\Components\Textarea::make('special_requests'),
                Forms\Components\DateTimePicker::make('confirmed_at'),
                Forms\Components\DateTimePicker::make('cancelled_at'),
                Forms\Components\TextInput::make('cancellation_reason'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ride.origin_address')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('ride.destination_address')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('seats_booked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        'completed' => 'info',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Print Receipt')
                        ->color('success')
                        ->icon('heroicon-o-printer')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            $pdf = app(\App\Services\BookingReceiptDeliveryService::class)->generatePdfBinary($record);
                            return response()->streamDownload(fn () => print($pdf), "booking-receipt-{$record->id}.pdf");
                        }),

                    Tables\Actions\Action::make('email_receipt')
                        ->label('Email Receipt')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            try {
                                app(\App\Services\BookingReceiptDeliveryService::class)->sendToEmail($record);
                                \Filament\Notifications\Notification::make()->title('Email sent successfully')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Failed to send email')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('whatsapp_receipt')
                        ->label('WhatsApp Receipt')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            try {
                                app(\App\Services\BookingReceiptDeliveryService::class)->sendToWhatsApp($record);
                                \Filament\Notifications\Notification::make()->title('WhatsApp message sent successfully')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Failed to send WhatsApp')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('sms_receipt')
                        ->label('SMS Receipt')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color('primary')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            try {
                                app(\App\Services\BookingReceiptDeliveryService::class)->sendToSms($record);
                                \Filament\Notifications\Notification::make()->title('SMS sent successfully')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Failed to send SMS')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ])->label('Receipt')->icon('heroicon-o-document-text'),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Booking $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Booking $record): bool => static::canDelete($record)),
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
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
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
