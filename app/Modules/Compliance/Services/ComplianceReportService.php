<?php

namespace App\Modules\Compliance\Services;

use App\Models\Booking;
use App\Models\ComplianceReport;
use App\Models\DriverPayout;
use App\Models\FraudFlag;
use App\Models\Payment;
use App\Models\Ride;
use App\Modules\Compliance\Contracts\ComplianceReportRepositoryInterface;
use App\Modules\Compliance\DTOs\ComplianceReportDTO;
use App\Modules\Reporting\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ComplianceReportService
 *
 * Generates RURA-compliant regulatory reports in CSV, PDF, and JSON formats.
 * All generated reports are immutable (append-only in compliance_reports table).
 * Every generation is audit-logged.
 *
 * Report types:
 *  - daily_ride_summary
 *  - driver_earnings
 *  - commission_breakdown
 *  - tax_payable_summary
 *  - refund_report
 *  - fraud_incident_report
 */
class ComplianceReportService
{
    public function __construct(
        private readonly ComplianceReportRepositoryInterface $reportRepo,
        private readonly ReportingService                    $reportingService,
    ) {}

    /**
     * Request generation of a compliance report.
     * Returns immediately with a pending report record.
     * Use GenerateComplianceReportJob for async generation.
     */
    public function request(ComplianceReportDTO $dto): ComplianceReport
    {
        return $this->reportRepo->create($dto->toArray());
    }

    /**
     * Generate and persist a compliance report synchronously.
     * For large date ranges, prefer dispatching GenerateComplianceReportJob.
     */
    public function generate(int $reportId): ComplianceReport
    {
        $report = ComplianceReport::findOrFail($reportId);

        ComplianceReport::where('id', $reportId)->update(['status' => 'generating']);

        try {
            $data     = $this->fetchReportData($report);
            $filePath = $this->writeFile($report, $data);

            $this->reportRepo->markReady($reportId, $filePath, $this->buildSummary($data));

            Log::info('ComplianceReportService: report generated', [
                'report_id'   => $reportId,
                'report_type' => $report->report_type,
                'format'      => $report->format,
            ]);
        } catch (\Throwable $e) {
            $this->reportRepo->markFailed($reportId, $e->getMessage());
            Log::error('ComplianceReportService: generation failed', [
                'report_id' => $reportId,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }

        return $report->fresh();
    }

    public function downloadUrl(ComplianceReport $report): ?string
    {
        if (! $report->file_path) return null;

        return Storage::url($report->file_path);
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function fetchReportData(ComplianceReport $report): array
    {
        $from = $report->period_from;
        $to   = $report->period_to;

        return match ($report->report_type) {
            'daily_ride_summary'    => $this->dailyRideSummary($from, $to),
            'driver_earnings'       => $this->driverEarnings($from, $to),
            'commission_breakdown'  => $this->commissionBreakdown($from, $to),
            'tax_payable_summary'   => $this->taxPayableSummary($from, $to),
            'refund_report'         => $this->refundReport($from, $to),
            'fraud_incident_report' => $this->fraudIncidentReport($from, $to),
            default                 => throw new \InvalidArgumentException("Unknown report type: {$report->report_type}"),
        };
    }

    private function writeFile(ComplianceReport $report, array $data): string
    {
        $dir    = 'compliance/' . date('Y/m');
        $name   = "{$report->report_type}_{$report->period_from}_{$report->period_to}_{$report->id}.{$report->format}";
        $path   = "{$dir}/{$name}";

        $content = match ($report->format) {
            'csv'  => $this->toCsv($data),
            'pdf'  => $this->toPdf($report, $data),
            'json' => json_encode(['report' => $report->report_type, 'data' => $data], JSON_PRETTY_PRINT),
            default => throw new \InvalidArgumentException("Unknown format: {$report->format}"),
        };

        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function buildSummary(array $data): array
    {
        return [
            'row_count'    => count($data),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function toCsv(array $data): string
    {
        if (empty($data)) return '';

        $out     = fopen('php://temp', 'r+');
        $headers = array_keys($data[0]);

        fputcsv($out, $headers);
        foreach ($data as $row) {
            fputcsv($out, array_values($row));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return (string) $csv;
    }

    private function toPdf(ComplianceReport $report, array $data): string
    {
        $columns = ! empty($data) ? array_keys($data[0]) : [];

        $pdf = Pdf::loadView('compliance.report-pdf', [
            'reportId'    => $report->id,
            'reportType'  => $report->report_type,
            'reportTitle' => ComplianceReport::TYPES[$report->report_type] ?? $report->report_type,
            'periodFrom'  => $report->period_from,
            'periodTo'    => $report->period_to,
            'generatedAt' => now()->format('Y-m-d H:i:s') . ' UTC',
            'columns'     => $columns,
            'rows'        => $data,
            'summaryData' => [],
        ]);
        return $pdf->output();
    }

    // -----------------------------------------------------------------------
    // Report data builders
    // -----------------------------------------------------------------------

    private function dailyRideSummary(string $from, string $to): array
    {
        return Ride::where('status', 'completed')
            ->whereBetween('updated_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("DATE(updated_at) as ride_date, COUNT(*) as total_rides, SUM(fare_amount) as total_fare")
            ->groupByRaw('DATE(updated_at)')
            ->orderBy('ride_date')
            ->get()
            ->map(fn ($r) => [
                'date'        => $r->ride_date,
                'total_rides' => $r->total_rides,
                'total_fare'  => number_format((float) $r->total_fare, 2),
                'currency'    => 'RWF',
            ])
            ->all();
    }

    private function driverEarnings(string $from, string $to): array
    {
        return DriverPayout::whereBetween('payout_date', [$from, $to])
            ->with('driver.user')
            ->orderBy('payout_date')
            ->get()
            ->map(fn ($p) => [
                'payout_date'       => $p->payout_date,
                'driver_id'         => $p->driver_id,
                'driver_name'       => $p->driver->user->name ?? 'N/A',
                'total_income'      => $p->total_income,
                'commission_amount' => $p->commission_amount,
                'payout_amount'     => $p->payout_amount,
                'status'            => $p->status,
                'currency'          => 'RWF',
            ])
            ->all();
    }

    private function commissionBreakdown(string $from, string $to): array
    {
        return DriverPayout::whereBetween('payout_date', [$from, $to])
            ->where('status', 'processed')
            ->selectRaw('payout_date, SUM(total_income) as gross, SUM(commission_amount) as commission, SUM(payout_amount) as payout')
            ->groupBy('payout_date')
            ->orderBy('payout_date')
            ->get()
            ->map(fn ($r) => [
                'date'                => $r->payout_date,
                'gross_income_rwf'    => $r->gross,
                'commission_8pct_rwf' => $r->commission,
                'driver_payout_rwf'   => $r->payout,
                'currency'            => 'RWF',
            ])
            ->all();
    }

    private function taxPayableSummary(string $from, string $to): array
    {
        $taxAccount = \App\Models\LedgerAccount::where('name', 'Tax Payable')
            ->where('owner_type', 'platform')
            ->first();

        if (! $taxAccount) return [];

        return $taxAccount->entries()
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('transaction')
            ->get()
            ->map(fn ($e) => [
                'date'             => $e->created_at->toDateString(),
                'reference_type'   => $e->reference_type,
                'reference_id'     => $e->reference_id,
                'tax_amount_rwf'   => $e->credit,
                'description'      => $e->description,
                'currency'         => 'RWF',
            ])
            ->all();
    }

    private function refundReport(string $from, string $to): array
    {
        return Payment::where('status', 'refunded')
            ->whereBetween('updated_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('booking')
            ->get()
            ->map(fn ($p) => [
                'date'         => $p->updated_at->toDateString(),
                'payment_id'   => $p->id,
                'booking_id'   => $p->booking_id,
                'user_id'      => $p->user_id,
                'amount_rwf'   => $p->amount,
                'provider'     => $p->payment_provider,
                'currency'     => 'RWF',
            ])
            ->all();
    }

    private function fraudIncidentReport(string $from, string $to): array
    {
        return FraudFlag::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->orderBy('severity')
            ->get()
            ->map(fn ($f) => [
                'date'        => $f->created_at->toDateString(),
                'entity_type' => $f->entity_type,
                'entity_id'   => $f->entity_id,
                'severity'    => $f->severity,
                'reason'      => $f->reason,
                'resolved'    => $f->resolved ? 'Yes' : 'No',
                'resolved_at' => $f->resolved_at?->toDateString(),
            ])
            ->all();
    }
}
