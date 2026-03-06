<?php

namespace App\Modules\Compliance\Contracts;

use App\Models\ComplianceReport;
use Illuminate\Database\Eloquent\Collection;

interface ComplianceReportRepositoryInterface
{
    public function create(array $data): ComplianceReport;

    public function markReady(int $id, string $filePath, array $summaryData): void;

    public function markFailed(int $id, string $error): void;

    public function getByType(string $reportType, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): Collection;

    public function getLatest(string $reportType): ?ComplianceReport;
}
