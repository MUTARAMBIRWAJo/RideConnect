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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

class ReportsPage extends Page
{
    protected static ?string $navigationGroup = 'Reporting & Analytics';

    protected static string $view = 'filament.pages.accountant.reports';

    public string $reportType = 'daily';

    public string $exportFormat = 'none';

    public array $availableReports = [];

    /** @var array<int, array{name:string,size_label:string,generated_at:string,download_url:string,disk:string}> */
    public array $downloadHistory = [];

    /** @var array<int, string> */
    public array $reportPreviewHeaders = [];

    /** @var array<int, array<string, scalar|null>> */
    public array $reportPreviewRows = [];

    public ?string $previewReportTitle = null;

    public ?string $previewGeneratedAt = null;

    public static function getNavigationLabel(): string
    {
        return 'Reports & Export';
    }

    public static function getNavigationIcon(): string|Htmlable|null
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
        $this->loadPreview('daily');
    }

    public function generateReport(string $type): void
    {
        $this->ensureFinanceAccess();

        $this->reportType = $type;
        $this->loadPreview($type);

        app(ActionAuditLogger::class)->log(
            'report.generate',
            'Accountant generated '.$type.' report preview',
            ['report_type' => $type],
        );

        Notification::make()
            ->title('Report preview ready: '.strtoupper($type))
            ->success()
            ->send();
    }

    public function exportCSV(string $type): void
    {
        $this->ensureFinanceAccess();

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
                'disk' => $result['disk'],
            ],
        );

        $this->sendExportNotifications($type, 'csv', $result);
        $this->loadDownloadHistory();
        $this->loadPreview($type);
    }

    public function exportPDF(string $type): void
    {
        $this->ensureFinanceAccess();

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
                'disk' => $result['disk'],
            ],
        );

        $this->sendExportNotifications($type, 'pdf', $result);
        $this->loadDownloadHistory();
        $this->loadPreview($type);
    }

    private function ensureFinanceAccess(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        if (! $user->can('view finances') && ! $user->can('manage finances')) {
            abort(403);
        }
    }

    private function loadPreview(string $type): void
    {
        $rows = $this->buildReportRows($type);

        $this->previewReportTitle = $this->reportDisplayName($type);
        $this->previewGeneratedAt = now()->toDateTimeString();
        $this->reportPreviewHeaders = $this->resolveHeaders($rows);
        $this->reportPreviewRows = array_slice($rows, 0, 20);
    }

    private function loadDownloadHistory(): void
    {
        $userId = (int) auth()->id();
        if ($userId <= 0) {
            $this->downloadHistory = [];

            return;
        }

        $directory = 'accountant-reports/'.$userId;

        $history = $this->collectHistoryFromDisk('local', $directory)
            ->concat($this->collectHistoryFromDisk('public', $directory))
            ->concat($this->collectHistoryFromTemp($directory))
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

    /**
     * @return Collection<int, array{name:string,size_label:string,generated_at:string,download_url:string,disk:string,_sort:int}>
     */
    private function collectHistoryFromDisk(string $disk, string $directory): Collection
    {
        if (! Storage::disk($disk)->exists($directory)) {
            return collect();
        }

        return collect(Storage::disk($disk)->files($directory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.csv') || str_ends_with($path, '.pdf'))
            ->map(function (string $path) use ($disk): array {
                $timestamp = Storage::disk($disk)->lastModified($path);
                $size = Storage::disk($disk)->size($path);

                return [
                    'name' => basename($path),
                    'size_label' => $this->formatFileSize((int) $size),
                    'generated_at' => now()->setTimestamp((int) $timestamp)->toDateTimeString(),
                    'download_url' => URL::temporarySignedRoute(
                        'accountant.reports.download',
                        now()->addHours(24),
                        ['disk' => $disk, 'file' => $path]
                    ),
                    'disk' => $disk,
                    '_sort' => (int) $timestamp,
                ];
            });
    }

    /**
     * @return Collection<int, array{name:string,size_label:string,generated_at:string,download_url:string,disk:string,_sort:int}>
     */
    private function collectHistoryFromTemp(string $directory): Collection
    {
        $baseTemp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'rideconnect-reports';
        $absoluteDirectory = $baseTemp.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

        if (! is_dir($absoluteDirectory)) {
            return collect();
        }

        $files = glob($absoluteDirectory.DIRECTORY_SEPARATOR.'*') ?: [];

        return collect($files)
            ->filter(fn (string $absolutePath): bool => is_file($absolutePath) && (str_ends_with($absolutePath, '.csv') || str_ends_with($absolutePath, '.pdf')))
            ->map(function (string $absolutePath) use ($directory): array {
                $timestamp = @filemtime($absolutePath) ?: time();
                $size = @filesize($absolutePath) ?: 0;
                $basename = basename($absolutePath);
                $relativePath = trim($directory, '/').'/'.$basename;

                return [
                    'name' => $basename,
                    'size_label' => $this->formatFileSize((int) $size),
                    'generated_at' => now()->setTimestamp((int) $timestamp)->toDateTimeString(),
                    'download_url' => URL::temporarySignedRoute(
                        'accountant.reports.download',
                        now()->addHours(24),
                        ['disk' => 'temp', 'file' => $relativePath]
                    ),
                    'disk' => 'temp',
                    '_sort' => (int) $timestamp,
                ];
            });
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
     * @return array{filename:string,file_path:string,disk:string,download_url:string,records:int}
     */
    private function exportReportFile(string $type, string $format): array
    {
        $rows = $this->buildReportRows($type);
        $headers = $this->resolveHeaders($rows);
        $meta = $this->buildDocumentMeta($type, $format);

        $timestamp = now()->format('Ymd-His');
        $userId = (int) auth()->id();
        $safeType = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($type)) ?: 'report';
        $filename = sprintf('%s-%s-%d.%s', $safeType, $timestamp, $userId, $format);
        $relativePath = 'accountant-reports/'.$userId.'/'.$filename;

        $payload = $format === 'csv'
            ? $this->buildCsvContent($headers, $rows, $meta)
            : Pdf::loadHTML($this->buildPdfHtml($headers, $rows, $meta))->setPaper('a4', 'portrait')->output();

        $disk = $this->storeReportPayload($relativePath, $payload);

        $downloadUrl = URL::temporarySignedRoute(
            'accountant.reports.download',
            now()->addHours(24),
            ['disk' => $disk, 'file' => $relativePath]
        );

        return [
            'filename' => $filename,
            'file_path' => $relativePath,
            'disk' => $disk,
            'download_url' => $downloadUrl,
            'records' => count($rows),
        ];
    }

    private function storeReportPayload(string $relativePath, string $payload): string
    {
        foreach (['local', 'public'] as $disk) {
            try {
                Storage::disk($disk)->put($relativePath, $payload);

                return $disk;
            } catch (Throwable $e) {
                report($e);
            }
        }

        try {
            $absolutePath = $this->resolveTempAbsolutePath($relativePath);
            $directory = dirname($absolutePath);

            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            if (is_dir($directory) && @file_put_contents($absolutePath, $payload) !== false) {
                return 'temp';
            }
        } catch (Throwable $e) {
            report($e);
        }

        throw new \RuntimeException('Unable to store report file on available disks.');
    }

    private function resolveTempAbsolutePath(string $relativePath): string
    {
        $baseTemp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'rideconnect-reports';

        return $baseTemp.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
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
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'created_at')) {
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
                'Amount (RWF)' => (float) $row->amount,
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
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'created_at')) {
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
                'Total Amount (RWF)' => (float) $row->total_amount,
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
        if (! Schema::hasTable('driver_payouts') || ! Schema::hasColumn('driver_payouts', 'driver_id')) {
            return [['Message' => 'Driver payouts table is not available.']];
        }

        $candidateColumns = ['driver_id', 'amount', 'payout_amount', 'commission_deducted', 'commission'];
        $selectColumns = collect($candidateColumns)
            ->filter(fn (string $column): bool => Schema::hasColumn('driver_payouts', $column))
            ->values()
            ->all();

        if (! in_array('driver_id', $selectColumns, true)) {
            return [['Message' => 'Driver ID column is missing in payouts table.']];
        }

        $rawRows = DB::table('driver_payouts')
            ->select($selectColumns)
            ->limit(10000)
            ->get();

        $rows = $rawRows
            ->groupBy('driver_id')
            ->map(function ($group, $driverId): array {
                $payout = (float) $group->sum(function ($row): float {
                    $row = (array) $row;

                    return (float) ($row['amount'] ?? $row['payout_amount'] ?? 0);
                });

                $commission = (float) $group->sum(function ($row): float {
                    $row = (array) $row;

                    return (float) ($row['commission_deducted'] ?? $row['commission'] ?? 0);
                });

                return [
                    'Driver ID' => (int) $driverId,
                    'Payout Count' => (int) $group->count(),
                    'Total Payout (RWF)' => $payout,
                    'Total Commission (RWF)' => $commission,
                ];
            })
            ->sortByDesc('Total Payout (RWF)')
            ->take(500)
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
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'created_at')) {
            return [['Message' => 'Payments table is not available.']];
        }

        $rows = DB::table('payments')
            ->whereYear('created_at', now()->year)
            ->whereIn('status', ['completed', 'COMPLETED'])
            ->select([
                DB::raw("DATE_TRUNC('month', created_at) as tax_month"),
                DB::raw('SUM(COALESCE(amount, 0)) as gross_revenue'),
                DB::raw('SUM(COALESCE(amount, 0)) * 0.18 as estimated_tax_18pct'),
            ])
            ->groupBy(DB::raw("DATE_TRUNC('month', created_at)"))
            ->orderBy('tax_month')
            ->get()
            ->map(fn ($row): array => [
                'Month' => (string) $row->tax_month,
                'Gross Revenue (RWF)' => (float) $row->gross_revenue,
                'Estimated Tax 18% (RWF)' => (float) $row->estimated_tax_18pct,
            ])
            ->values()
            ->all();

        if ($rows === []) {
            return [['Message' => 'No completed payment records found for tax summary.']];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, scalar|null>>  $rows
     * @return array<int, string>
     */
    private function resolveHeaders(array $rows): array
    {
        $firstRow = $rows[0] ?? ['Message' => 'No data available.'];

        return array_keys($firstRow);
    }

    private function reportDisplayName(string $type): string
    {
        return match (strtolower($type)) {
            'daily' => 'Daily Financial Report',
            'monthly' => 'Monthly Financial Report',
            'settlement' => 'Driver Settlement Report',
            'tax' => 'Tax Summary Report',
            default => 'Financial Report',
        };
    }

    private function reportPeriodLabel(string $type): string
    {
        return match (strtolower($type)) {
            'daily' => now()->toDateString(),
            'monthly' => now()->format('F Y'),
            'settlement' => 'All available payout history',
            'tax' => 'Tax year '.now()->year,
            default => 'N/A',
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildDocumentMeta(string $type, string $format): array
    {
        $user = auth()->user();
        $company = (string) config('app.name', 'RideConnect');
        $url = (string) config('app.url', url('/'));
        $logoUrl = rtrim($url, '/').'/images/logo.svg';
        $documentNo = sprintf('RC-FIN-%s-%04d', now()->format('YmdHis'), (int) auth()->id());

        return [
            'company_name' => $company,
            'company_url' => $url,
            'logo_url' => $logoUrl,
            'report_title' => $this->reportDisplayName($type),
            'report_period' => $this->reportPeriodLabel($type),
            'document_no' => $documentNo,
            'generated_at' => now()->toDateTimeString(),
            'generated_by_name' => (string) ($user?->name ?? 'Unknown User'),
            'generated_by_email' => (string) ($user?->email ?? 'N/A'),
            'format' => strtoupper($format),
            'currency' => 'RWF',
            'confidentiality' => 'Confidential - Internal Use Only',
            'logo_data_uri' => (string) ($this->resolveLogoDataUri() ?? ''),
        ];
    }

    private function resolveLogoDataUri(): ?string
    {
        $candidates = [
            public_path('images/logo.png'),
            public_path('images/logo.jpg'),
            public_path('images/logo.svg'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path)) {
                continue;
            }

            $raw = @file_get_contents($path);
            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream',
            };

            return 'data:'.$mime.';base64,'.base64_encode($raw);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows
     * @param  array<string, string>  $meta
     */
    private function buildCsvContent(array $headers, array $rows, array $meta): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return "Message\n\"Unable to generate CSV output.\"\n";
        }

        $metaRows = [
            ['OFFICIAL FINANCIAL REPORT'],
            ['Company', $meta['company_name']],
            ['Company URL', $meta['company_url']],
            ['Company Logo URL', $meta['logo_url']],
            ['Report Title', $meta['report_title']],
            ['Document Number', $meta['document_no']],
            ['Report Period', $meta['report_period']],
            ['Generated At', $meta['generated_at']],
            ['Generated By', $meta['generated_by_name']],
            ['Generated By Email', $meta['generated_by_email']],
            ['Currency', $meta['currency']],
            ['Confidentiality', $meta['confidentiality']],
            ['Format', $meta['format']],
            ['Prepared By Signature', '____________________________'],
            ['Reviewed By Signature', '____________________________'],
            ['Approved By Signature', '____________________________'],
            ['Date', '____________________________'],
            [],
        ];

        foreach ($metaRows as $metaRow) {
            fputcsv($stream, $metaRow);
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
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, scalar|null>>  $rows
     * @param  array<string, string>  $meta
     */
    private function buildPdfHtml(array $headers, array $rows, array $meta): string
    {
        $logoHtml = $meta['logo_data_uri'] !== ''
            ? '<img src="'.e($meta['logo_data_uri']).'" style="height:52px; max-width:160px; object-fit:contain;" alt="Company Logo">'
            : '<div style="font-size:11px;color:#6b7280;">Logo</div>';

        $html = '<html><head><meta charset="utf-8"><style>
            body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
            .header { border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 14px; }
            .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
            .meta td { padding: 4px 6px; border: 1px solid #d1d5db; }
            .meta td:first-child { width: 30%; background: #f8fafc; font-weight: 600; }
            .title { font-size: 17px; font-weight: 700; margin: 0 0 4px 0; }
            .sub { color: #475569; margin: 0; }
            .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            .table th, .table td { border: 1px solid #cbd5e1; padding: 6px; font-size: 11px; }
            .table th { background: #e2e8f0; text-align: left; }
            .footer { margin-top: 22px; }
            .sign-grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .sign-grid td { width: 33%; padding: 10px; vertical-align: top; }
            .line { border-top: 1px solid #334155; margin-top: 28px; }
            .note { margin-top: 12px; font-size: 10px; color: #64748b; }
        </style></head><body>';

        $html .= '<div class="header"><table width="100%" cellpadding="0" cellspacing="0"><tr>';
        $html .= '<td width="26%">'.$logoHtml.'</td>';
        $html .= '<td width="74%">';
        $html .= '<p class="title">'.e($meta['company_name']).'</p>';
        $html .= '<p class="sub">Official Finance Document</p>';
        $html .= '<p class="sub">'.e($meta['confidentiality']).'</p>';
        $html .= '</td></tr></table></div>';

        $html .= '<table class="meta">';
        $html .= '<tr><td>Report Title</td><td>'.e($meta['report_title']).'</td></tr>';
        $html .= '<tr><td>Document Number</td><td>'.e($meta['document_no']).'</td></tr>';
        $html .= '<tr><td>Report Period</td><td>'.e($meta['report_period']).'</td></tr>';
        $html .= '<tr><td>Generated At</td><td>'.e($meta['generated_at']).'</td></tr>';
        $html .= '<tr><td>Generated By</td><td>'.e($meta['generated_by_name']).' ('.e($meta['generated_by_email']).')</td></tr>';
        $html .= '<tr><td>Currency</td><td>'.e($meta['currency']).'</td></tr>';
        $html .= '<tr><td>System URL</td><td>'.e($meta['company_url']).'</td></tr>';
        $html .= '</table>';

        $html .= '<table class="table"><thead><tr>';
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

        $html .= '<div class="footer">';
        $html .= '<table class="sign-grid"><tr>';
        $html .= '<td><div class="line"></div><div>Prepared By</div><div>Date: ____________</div></td>';
        $html .= '<td><div class="line"></div><div>Reviewed By</div><div>Date: ____________</div></td>';
        $html .= '<td><div class="line"></div><div>Approved By</div><div>Date: ____________</div></td>';
        $html .= '</tr></table>';
        $html .= '<div class="note">System generated document. Keep this file for accounting and audit records.</div>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * @param  array{filename:string,file_path:string,disk:string,download_url:string,records:int}  $result
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
                'disk' => $result['disk'],
                'status' => 'completed',
                'download_url' => $result['download_url'],
                'action_url' => $result['download_url'],
                'expires_at' => now()->addHours(24)->toIso8601String(),
            ],
            'is_read' => false,
        ]);
    }
}
