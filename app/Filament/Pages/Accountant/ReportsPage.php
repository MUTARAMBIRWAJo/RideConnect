<?php

namespace App\Filament\Pages\Accountant;

use App\Services\ActionAuditLogger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ReportsPage extends Page
{
    protected static ?string $navigationGroup = 'Reporting & Analytics';

    protected static string $view = 'filament.pages.accountant.reports';

    public string $reportType = 'daily';

    public string $exportFormat = 'none';

    public array $availableReports = [];

    public static function getNavigationLabel(): string
    {
        return 'Reports & Export';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-document-text';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('accountant') || auth()->user()->hasRole('ACCOUNTANT'));
    }

    public function getTitle(): string
    {
        return 'Financial Reports & Export';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->availableReports = [
            [
                'name' => 'Daily Report',
                'description' => 'Transaction summary, revenue, and commission for today',
                'icon' => 'heroicon-o-calendar',
                'action' => 'daily',
            ],
            [
                'name' => 'Monthly Report',
                'description' => 'Comprehensive monthly financial overview',
                'icon' => 'heroicon-o-calendar-days',
                'action' => 'monthly',
            ],
            [
                'name' => 'Driver Settlement Report',
                'description' => 'Detailed breakdown of driver payouts and commissions',
                'icon' => 'heroicon-o-users',
                'action' => 'settlement',
            ],
            [
                'name' => 'Tax Summary',
                'description' => 'Tax-relevant transactions and adjustments',
                'icon' => 'heroicon-o-receipt-percent',
                'action' => 'tax',
            ],
        ];
    }

    public function generateReport(string $type): void
    {
        if (!auth()->user()->can('view finances')) {
            abort(403);
        }

        $this->reportType = $type;

        app(ActionAuditLogger::class)->log(
            'report.generate',
            'Accountant generated '.$type.' report',
            ['report_type' => $type],
        );

        Notification::make()
            ->title('Report generated: '.strtoupper($type))
            ->success()
            ->send();
    }

    public function exportCSV(string $type): void
    {
        if (!auth()->user()->can('view finances')) {
            abort(403);
        }

        $this->reportType = $type;
        $this->exportFormat = 'csv';

        app(ActionAuditLogger::class)->log(
            'report.export_csv',
            'Accountant exported '.$type.' report as CSV',
            ['report_type' => $type, 'format' => 'csv'],
        );

        Notification::make()
            ->title('CSV export requested: '.strtoupper($type))
            ->success()
            ->send();
    }

    public function exportPDF(string $type): void
    {
        if (!auth()->user()->can('view finances')) {
            abort(403);
        }

        $this->reportType = $type;
        $this->exportFormat = 'pdf';

        app(ActionAuditLogger::class)->log(
            'report.export_pdf',
            'Accountant exported '.$type.' report as PDF',
            ['report_type' => $type, 'format' => 'pdf'],
        );

        Notification::make()
            ->title('PDF export requested: '.strtoupper($type))
            ->success()
            ->send();
    }
}
