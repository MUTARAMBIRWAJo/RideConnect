<?php

namespace App\Console\Commands;

use App\Services\Identity\IdentityConsistencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IdentityConsistencyReportCommand extends Command
{
    protected $signature = 'identity:report
                            {--json : Output raw JSON to stdout}
                            {--save= : Save report to a file path}';

    protected $description = 'Generate identity consistency and orphan detection report';

    public function handle(IdentityConsistencyService $consistencyService): int
    {
        $report = $consistencyService->generateReport();

        if ($path = $this->option('save')) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Identity report saved to {$path}");
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $summary = $report['summary'];
        $this->info('RideConnect Identity Consistency Report');
        $this->line('Generated: '.$report['generated_at']);
        $this->newLine();
        $this->line('Production readiness score: '.$summary['production_readiness_score'].'/100');
        $this->line('Total issues: '.$summary['total_issues']);
        $this->newLine();

        foreach ($report['checks'] as $key => $check) {
            if (($check['count'] ?? 0) === 0) {
                continue;
            }

            $this->warn(sprintf(
                '[%s] %s — %d (sample: %s)',
                $key,
                $check['label'],
                $check['count'],
                implode(', ', $check['sample_ids'] ?? []) ?: 'none'
            ));
        }

        if ($summary['total_issues'] === 0) {
            $this->info('No identity orphans detected.');
        }

        return self::SUCCESS;
    }
}
