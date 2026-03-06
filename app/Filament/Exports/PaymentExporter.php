<?php

namespace App\Filament\Exports;

use App\Models\Payment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PaymentExporter extends Exporter
{
    protected static ?string $model = Payment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('booking.id')->label('Booking ID'),
            ExportColumn::make('user.name')->label('User'),
            ExportColumn::make('amount')->label('Amount'),
            ExportColumn::make('platform_fee')->label('Platform Fee'),
            ExportColumn::make('driver_amount')->label('Driver Amount'),
            ExportColumn::make('currency')->label('Currency'),
            ExportColumn::make('payment_method')->label('Payment Method'),
            ExportColumn::make('transaction_id')->label('Transaction ID'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('paid_at')->label('Paid At'),
            ExportColumn::make('created_at')->label('Created At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $successfulRows = number_format($export->successful_rows);

        $body = "Your payment export has completed and {$successfulRows} row(s) were exported.";

        $failedRowsCount = $export->getFailedRowsCount();

        if ($failedRowsCount > 0) {
            $body .= ' ' . number_format($failedRowsCount) . ' row(s) failed to export.';
        }

        return $body;
    }
}
