<?php

namespace App\Modules\Compliance\Repositories;

use App\Models\ComplianceReport;
use App\Modules\Compliance\Contracts\ComplianceReportRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ComplianceReportRepository implements ComplianceReportRepositoryInterface
{
    public function create(array $data): ComplianceReport
    {
        return ComplianceReport::create($data);
    }

    public function markReady(int $id, string $filePath, array $summaryData): void
    {
        ComplianceReport::where('id', $id)->update([
            'file_path'    => $filePath,
            'summary_data' => $summaryData,
            'status'       => 'ready',
            'generated_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        ComplianceReport::where('id', $id)->update([
            'status'        => 'failed',
            'error_message' => $error,
        ]);
    }

    public function getByType(string $reportType, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): Collection
    {
        return ComplianceReport::where('report_type', $reportType)
            ->when($from, fn ($q) => $q->whereDate('period_start', '>=', $from->format('Y-m-d')))
            ->when($to,   fn ($q) => $q->whereDate('period_end',   '<=', $to->format('Y-m-d')))
            ->orderBy('period_end', 'desc')
            ->get();
    }

    public function getLatest(string $reportType): ?ComplianceReport
    {
        return ComplianceReport::where('report_type', $reportType)
            ->where('status', 'ready')
            ->orderBy('period_end', 'desc')
            ->first();
    }
}
