<?php

namespace App\Filament\Resources\LedgerEntryResource\Pages;

use App\Filament\Resources\LedgerEntryResource;
use App\Services\FinancialStatementPdfService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListLedgerEntries extends ListRecords
{
    protected static string $resource = LedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_balance_sheet')
                ->label('Print Balance Sheet')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->button()
                ->extraAttributes(['style' => 'font-weight: 700;'])
                ->form($this->reportDateForm())
                ->action(fn (array $data) => $this->downloadPdfReport('balance_sheet', $data)),

            Action::make('print_cashflow')
                ->label('Print Cashflow')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->button()
                ->extraAttributes(['style' => 'font-weight: 700;'])
                ->form($this->reportDateForm())
                ->action(fn (array $data) => $this->downloadPdfReport('cashflow', $data)),

            Action::make('print_income_statement')
                ->label('Print Income Statement')
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->button()
                ->extraAttributes(['style' => 'font-weight: 700;'])
                ->form($this->reportDateForm())
                ->action(fn (array $data) => $this->downloadPdfReport('income_statement', $data)),

            Action::make('print_cashbook')
                ->label('Print Cashbook')
                ->icon('heroicon-o-document-text')
                ->color('danger')
                ->button()
                ->extraAttributes(['style' => 'font-weight: 700;'])
                ->form($this->reportDateForm())
                ->action(fn (array $data) => $this->downloadPdfReport('cashbook', $data)),

            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button()
                ->extraAttributes(['style' => 'font-weight: 700;'])
                ->action(fn () => $this->exportCsv()),
        ];
    }

    private function reportDateForm(): array
    {
        return [
            DatePicker::make('from')
                ->label('From')
                ->default(now()->startOfMonth()->toDateString())
                ->required(),
            DatePicker::make('to')
                ->label('To')
                ->default(now()->toDateString())
                ->required()
                ->afterOrEqual('from'),
        ];
    }

    public function downloadPdfReport(string $reportType, array $data): StreamedResponse
    {
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        $printedBy = auth()->user()?->name ?? 'System';

        /** @var FinancialStatementPdfService $service */
        $service = app(FinancialStatementPdfService::class);

        $report = match ($reportType) {
            'balance_sheet' => $service->generateBalanceSheet($from, $to, $printedBy),
            'cashflow' => $service->generateCashFlow($from, $to, $printedBy),
            'income_statement' => $service->generateIncomeStatement($from, $to, $printedBy),
            'cashbook' => $service->generateCashbook($from, $to, $printedBy),
            default => throw new \InvalidArgumentException('Unknown report type.'),
        };

        return response()->streamDownload(
            function () use ($report): void {
                echo $report['content'];
            },
            $report['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function exportCsv(): StreamedResponse
    {
        $entries = $this->getFilteredTableQuery()->with(['account', 'transaction'])->get();

        $filename = 'ledger-entries-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($entries) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID', 'Date', 'Transaction UUID', 'Account', 'Account Type',
                'Debit (RWF)', 'Credit (RWF)', 'Reference Type', 'Reference ID', 'Description',
            ]);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->id,
                    $entry->created_at?->format('Y-m-d H:i:s'),
                    $entry->transaction?->uuid,
                    $entry->account?->name,
                    $entry->account?->type,
                    number_format((float) $entry->debit, 2, '.', ''),
                    number_format((float) $entry->credit, 2, '.', ''),
                    $entry->reference_type,
                    $entry->reference_id,
                    $entry->description,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
