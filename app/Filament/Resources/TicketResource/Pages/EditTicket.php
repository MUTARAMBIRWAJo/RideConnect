<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invoice_pdf')
                ->label('Invoice PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(fn () => TicketResource::downloadInvoicePdf($this->record)),
            Actions\Action::make('send_email')
                ->label('Send to Passenger Email')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => TicketResource::sendInvoiceToPassengerEmail($this->record)),
            Actions\Action::make('send_whatsapp')
                ->label('Send to Passenger WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => TicketResource::sendInvoiceToPassengerWhatsApp($this->record)),
            Actions\Action::make('send_sms')
                ->label('Send to Passenger Phone Message')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => TicketResource::sendInvoiceToPassengerSms($this->record)),
            Actions\DeleteAction::make(),
        ];
    }
}
