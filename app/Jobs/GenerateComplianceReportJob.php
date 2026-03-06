<?php

namespace App\Jobs;

use App\Modules\Compliance\Services\ComplianceReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateComplianceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(public readonly int $reportId)
    {
        $this->onQueue('reporting');
    }

    public function handle(ComplianceReportService $service): void
    {
        $service->generate($this->reportId);
    }
}
