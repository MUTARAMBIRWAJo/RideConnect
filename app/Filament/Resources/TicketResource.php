<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use App\Services\TicketInvoiceDeliveryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ticket Details')
                    ->schema([
                        Forms\Components\Select::make('trip_id')
                            ->relationship('trip', 'id')
                            ->label('Trip'),
                        Forms\Components\Select::make('issued_by')
                            ->relationship('issuer', 'name')
                            ->label('Issued By'),
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->rows(3),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->step(0.01)
                            ->prefix('RWF ')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'OPEN' => 'Open',
                                'PENDING' => 'Pending',
                                'PAID' => 'Paid',
                                'CANCELLED' => 'Cancelled',
                            ])
                            ->required(),
                    ])->columns(2),
                
                Forms\Components\Section::make('Timestamps')
                    ->schema([
                        Forms\Components\DateTimePicker::make('issued_at'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('trip.id')
                    ->label('Trip ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('issuer.name')
                    ->label('Issued By')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('amount')
                    ->money('RWF')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower(trim($state))) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'open' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('issued_at')
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
                        'OPEN' => 'Open',
                        'PENDING' => 'Pending',
                        'PAID' => 'Paid',
                        'CANCELLED' => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('issued_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('to'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('issued_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Ticket $record): bool => static::canEdit($record)),
                Tables\Actions\Action::make('invoice_pdf')
                    ->label('Invoice PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn (): bool => auth()->user()?->can('manage tickets') ?? false)
                    ->action(fn (Ticket $record): StreamedResponse => static::downloadInvoicePdf($record)),
                Tables\Actions\Action::make('send_email')
                    ->label('Send to Passenger Email')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->can('manage tickets') ?? false)
                    ->requiresConfirmation()
                    ->action(fn (Ticket $record) => static::sendInvoiceToPassengerEmail($record)),
                Tables\Actions\Action::make('send_whatsapp')
                    ->label('Send to Passenger WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (): bool => auth()->user()?->can('manage tickets') ?? false)
                    ->requiresConfirmation()
                    ->action(fn (Ticket $record) => static::sendInvoiceToPassengerWhatsApp($record)),
                Tables\Actions\Action::make('send_sms')
                    ->label('Send to Passenger Phone Message')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->can('manage tickets') ?? false)
                    ->requiresConfirmation()
                    ->action(fn (Ticket $record) => static::sendInvoiceToPassengerSms($record)),
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Ticket $record): bool => (auth()->user()?->can('manage tickets') ?? false)
                        && in_array(strtolower(trim((string) $record->status)), ['pending', 'open'], true))
                    ->requiresConfirmation()
                    ->action(function (Ticket $record) {
                        $record->update(['status' => 'PAID']);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('manage tickets') ?? false),
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
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('manage tickets') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage tickets') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('manage tickets') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage tickets') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function downloadInvoicePdf(Ticket $ticket): StreamedResponse
    {
        $ticket->loadMissing(['trip.passenger', 'issuer']);

        $pdf = Pdf::loadView('pdf.ticket-invoice', [
            'ticket' => $ticket,
            'trip' => $ticket->trip,
            'passenger' => $ticket->trip?->passenger,
            'issuer' => $ticket->issuer,
        ])->setPaper('a4');

        return response()->streamDownload(
            fn () => print($pdf->output()),
            'ticket-invoice-' . $ticket->id . '.pdf'
        );
    }

    public static function sendInvoiceToPassengerEmail(Ticket $ticket): void
    {
        try {
            app(TicketInvoiceDeliveryService::class)->sendToEmail($ticket);

            Notification::make()
                ->title('Invoice sent by email')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Email delivery failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function sendInvoiceToPassengerWhatsApp(Ticket $ticket): void
    {
        try {
            app(TicketInvoiceDeliveryService::class)->sendToWhatsApp($ticket);

            Notification::make()
                ->title('Invoice sent by WhatsApp')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('WhatsApp delivery failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function sendInvoiceToPassengerSms(Ticket $ticket): void
    {
        try {
            app(TicketInvoiceDeliveryService::class)->sendToSms($ticket);

            Notification::make()
                ->title('Invoice sent by phone message')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Phone message delivery failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
