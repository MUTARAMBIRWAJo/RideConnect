<?php

namespace App\Filament\Pages\Accountant;

use App\Enums\UserRole;
use App\Models\Notification as UserNotification;
use App\Services\ActionAuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ReportsPage extends Page
{
    protected static ?string $navigationGroup = 'Reporting & Analytics';

    protected static string $view = 'filament.pages.accountant.reports';

    public string $reportType = 'daily';

    public string $exportFormat = 'none';

    public array $availableReports = [];

    /** @var array<int, array{name:string,size_label:string,generated_at:string,download_url:string}> */
    public array $downloadHistory = [];

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
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ($user->role === UserRole::ACCOUNTANT)
            || $user->hasAnyRole(['Accountant', 'accountant', 'ACCOUNTANT']);
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

        $this->loadDownloadHistory();
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

        $result = $this->exportReportFile($type, 'csv');

        app(ActionAuditLogger::class)->log(
            'report.export_csv',
            'Accountant exported '.$type.' report as CSV',
            [
                'report_type' => $type,
                'format' => 'csv',
                'filename' => $result['filename'],
                'download_url' => $result['download_url'],
            ],
        );

        $this->sendExportNotifications($type, 'csv', $result);
        $this->loadDownloadHistory();
    }

    public function exportPDF(string $type): void
    {
        if (!auth()->user()->can('view finances')) {
            abort(403);
        }

        $this->reportType = $type;
        $this->exportFormat = 'pdf';

        $result = $this->exportReportFile($type, 'pdf');

        app(ActionAuditLogger::class)->log(
            'report.export_pdf',
            'Accountant exported '.$type.' report as PDF',
            [
                'report_type' => $type,
                'format' => 'pdf',
                'filename' => $result['filename'],
                'download_url' => $result['download_url'],
            ],
        );

        $this->sendExportNotifications($type, 'pdf', $result);
        $this->loadDownloadHistory();
    }

    private function loadDownloadHistory(): void
    {
        $userId = (int) auth()->id();
        if ($userId <= 0) {
            $this->downloadHistory = [];

            return;
        }

        $directory = 'accountant-reports/'.$userId;
        if (! Storage::disk('local')->exists($directory)) {
            $this->downloadHistory = [];

            return;
        }

        $history = collect(Storage::disk('local')->files($directory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.csv') || str_ends_with($path, '.pdf'))
            ->map(function (string $path): array {
                $timestamp = Storage::disk('local')->lastModified($path);
                $size = Storage::disk('local')->size($path);

                return [
                    'name' => basename($path),
                    'size_label' => $this->formatFileSize((int) $size),
                    'generated_at' => now()->setTimestamp((int) $timestamp)->toDateTimeString(),
                    'download_url' => URL::temporarySignedRoute(
                        'accountant.reports.download',
                        now()->addHours(24),
                        ['file' => $path]
                    ),
                    '_sort' => (int) $timestamp,
                ];
            })
            ->sortByDesc('_sort')
            ->take(10)
            ->map(function (array $item): array {
                unset($item['_sort']);

                return $item;
            })
            ->values()
            ->all();

        $this->downloadHistory = $history;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }

    /**
     * @return array{filename:string,file_path:string,download_url:string,records:int}
     */
    private function exportReportFile(string $type, string $format): array
    {
        $rows = $this->buildReportRows($type);
        $headers = $this->resolveHeaders($rows);
        $timestamp = now()->format('Ymd-His');
        $userId = (int) auth()->id();
        $safeType = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($type)) ?: 'report';
        $filename = sprintf('%s-%s-%d.%s', $safeType, $timestamp, $userId, $format);
        $relativePath = 'accountant-reports/'.$userId.'/'.$filename;

        if ($format === 'csv') {
            Storage::disk('local')->put($relativePath, $this->buildCsvContent($headers, $rows));
        } else {
            $pdf = Pdf::loadHTML($this->buildPdfHtml($type, $headers, $rows))->setPaper('a4', 'landscape');
            Storage::disk('local')->put($relativePath, $pdf->output());
        }

        $downloadUrl = URL::temporarySignedRoute(
            'accountant.reports.download',
            now()->addHours(24),
            ['file' => $relativePath]
        );

        return [
            'filename' => $filename,
            'file_path' => $relativePath,
            'download_url' => $downloadUrl,
            'records' => count($rows),
        ];
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    private function buildReportRows(string $type): array
    {
        return match (strtolower($type)) {
            'daily' => $this->buildDailyRows(),
            'monthly' => $this->buildMonthlyRows(),
            'settlement' => $this->buildSettlementRows(),
            'tax' => $this->buildTaxRows(),
            default => [
                ['Message' => 'Unknown report type requested.'],
            ],
        };
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    private function buildDailyRows(): array
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'created_at')) {
            return [['Message' => 'Payments table is not available.']];
        }

        $today = now()->toDateString();

        $rows = DB::table('payments')
            ->whereDate('created_at', $today)
            ->select([
                'id',
                DB::raw("COALESCE(status, 'unknown') as status"),
                DB::raw('COALESCE(amount, 0) as amount'),
                DB::raw('COALESCE(created_at::text, \''.$today.'\') as created_at'),
            ])
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn ($row): array => [
                'Payment ID' => $row->id,
                'Status' => $row->status,
                'Amount' => (float) $row->amount,
                'Created At' => $row->created_at,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [['Message' => 'No payment records found for today.']];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    private function buildMonthlyRows(): array
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'created_at')) {
            return [['Message' => 'Payments table is not available.']];
        }

        $rows = DB::table('payments')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->select([
                DB::raw('DATE(created_at) as report_date'),
                DB::raw('COUNT(*) as payments_count'),
                DB::raw('SUM(COALESCE(amount, 0)) as total_amount'),
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('report_date')
            ->get()
            ->map(fn ($row): array => [
                'Date' => (string) $row->report_date,
                'Payments Count' => (int) $row->payments_count,
                'Total Amount' => (float) $row->total_amount,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [['Message' => 'No payment records found for this month.']];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    private function buildSettlementRows(): array
    {
        if (!Schema::hasTable('driver_payouts')) {
            return [['Message' => 'Driver payouts table is not available.']];
        }

        $rows = DB::table('driver_payouts')
            ->select([
                'driver_id',
                DB::raw('COUNT(*) as payout_count'),
                DB::raw('SUM(COALESCE(amount, 0)) as total_payout'),
                DB::raw('SUM(COALESCE(commission_deducted, 0)) as total_commission'),
            ])
            ->groupBy('driver_id')
            ->orderByDesc('total_payout')
            ->limit(500)
            ->get()
            ->map(fn ($row): array => [
                'Driver ID' => (int) $row->driver_id,
                'Payout Count' => (int) $row->payout_count,
                'Total Payout' => (float) $row->total_payout,
                'Total Commission' => (float) $row->total_commission,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [['Message' => 'No driver payout records found.']];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    private function buildTaxRows(): array
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'created_at')) {
            return [['Message' => 'Payments table is not available.']];
        }

        $rows = DB::table('payments')
            ->whereYear('created_at', now()->year)
            ->whereIn('status', ['completed', 'COMPLETED'])
            ->select([
                DB::raw('DATE_TRUNC(\'month\', created_at) as tax_month'),
                DB::raw('SUM(COALESCE(amount, 0)) as gross_revenue'),
                DB::raw('SUM(COALESCE(amount, 0)) * 0.18 as estimated_tax_18pct'),
            ])
            ->groupBy(DB::raw('DATE_TRUNC(\'month\', created_at)'))
            ->orderBy('tax_month')
            ->get()
            ->map(fn ($row): array => [
                'Month' => (string) $row->tax_month,
                'Gross Revenue' => (float) $row->gross_revenue,
                'Estimated Tax (18%)' => (float) $row->estimated_tax_18pct,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [['Message' => 'No completed payment records found for tax summary.']];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, scalar|null>> $rows
     * @return array<int, string>
     */
    private function resolveHeaders(array $rows): array
    {
        $firstRow = $rows[0] ?? ['Message' => 'No data available.'];

        return array_keys($firstRow);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, scalar|null>> $rows
     */
    private function buildCsvContent(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return "Message\n\"Unable to generate CSV output.\"\n";
        }

        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($stream, $line);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<string, scalar|null>> $rows
     */
    private function buildPdfHtml(string $type, array $headers, array $rows): string
    {
        $html = '<h2>Financial Report: '.e(strtoupper($type)).'</h2>';
        $html .= '<p>Generated at: '.e(now()->toDateTimeString()).'</p>';
        $html .= '<table border="1" cellpadding="6" cellspacing="0" width="100%"><thead><tr>';

        foreach ($headers as $header) {
            $html .= '<th>'.e($header).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $html .= '<td>'.e((string) ($row[$header] ?? '')).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param array{filename:string,file_path:string,download_url:string,records:int} $result
     */
    private function sendExportNotifications(string $type, string $format, array $result): void
    {
        $userId = (int) auth()->id();

        $downloadAction = Action::make('download_report')
            ->label('Download File')
            ->button()
            ->url($result['download_url'], shouldOpenInNewTab: true);

        Notification::make()
            ->title(strtoupper($format).' report ready: '.strtoupper($type))
            ->body('File '.$result['filename'].' is ready. Use the Download File button.')
            ->success()
            ->actions([$downloadAction])
            ->send();

        Notification::make()
            ->title('Report download ready')
            ->body(strtoupper($type).' ('.strtoupper($format).') is ready to download.')
            ->success()
            ->actions([
                Action::make('download_report_database')
                    ->label('Download File')
                    ->button()
                    ->url($result['download_url'], shouldOpenInNewTab: true),
            ])
            ->sendToDatabase(auth()->user());

        UserNotification::query()->create([
            'user_id' => $userId,
            'type' => 'report_export_ready',
            'title' => 'Report download ready',
            'message' => strtoupper($type).' ('.strtoupper($format).') is ready. Tap to download.',
            'data' => [
                'report_type' => $type,
                'format' => $format,
                'filename' => $result['filename'],
                'status' => 'completed',
                'download_url' => $result['download_url'],
                'action_url' => $result['download_url'],
                'expires_at' => now()->addHours(24)->toIso8601String(),
            ],
            'is_read' => false,
        ]);
    }
}
