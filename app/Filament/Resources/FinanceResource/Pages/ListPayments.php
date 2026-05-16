<?php

namespace App\Filament\Resources\FinanceResource\Pages;

use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\FinanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = FinanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(PaymentExporter::class),
        ];
    }
}
