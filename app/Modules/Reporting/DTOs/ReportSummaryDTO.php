<?php

namespace App\Modules\Reporting\DTOs;

readonly class ReportSummaryDTO
{
    public function __construct(
        public string $reportType,
        public string $period,
        public float  $totalRevenue,
        public float  $totalCommission,
        public float  $totalTax,
        public float  $totalPayouts,
        public int    $totalTransactions,
        public int    $totalRides,
        public array  $breakdown = [],
    ) {}

    public function toArray(): array
    {
        return [
            'report_type'        => $this->reportType,
            'period'             => $this->period,
            'total_revenue'      => $this->totalRevenue,
            'total_commission'   => $this->totalCommission,
            'total_tax'          => $this->totalTax,
            'total_payouts'      => $this->totalPayouts,
            'total_transactions' => $this->totalTransactions,
            'total_rides'        => $this->totalRides,
            'breakdown'          => $this->breakdown,
        ];
    }
}
