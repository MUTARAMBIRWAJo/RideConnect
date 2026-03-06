<?php

namespace App\Modules\Reporting\Contracts;

interface ReportingRepositoryInterface
{
    public function getDailyRevenueSummary(string $date): array;

    public function getDriverEarningsSummary(string $from, string $to): array;

    public function getCommissionBreakdown(string $from, string $to): array;

    public function getTaxPayableSummary(string $from, string $to): array;

    public function getRefundReport(string $from, string $to): array;

    public function getFraudIncidentReport(string $from, string $to): array;
}
