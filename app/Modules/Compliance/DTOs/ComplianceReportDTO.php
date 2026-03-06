<?php

namespace App\Modules\Compliance\DTOs;

readonly class ComplianceReportDTO
{
    public function __construct(
        public string  $reportType,
        public string  $periodStart,
        public string  $periodEnd,
        public string  $format,        // 'csv' | 'pdf' | 'json'
        public ?int    $generatedBy,
        public array   $filters = [],
    ) {}

    public function toArray(): array
    {
        return [
            'report_type'  => $this->reportType,
            'period_start' => $this->periodStart,
            'period_end'   => $this->periodEnd,
            'format'       => $this->format,
            'generated_by' => $this->generatedBy,
            'metadata'     => ['filters' => $this->filters],
            'status'       => 'pending',
        ];
    }
}
