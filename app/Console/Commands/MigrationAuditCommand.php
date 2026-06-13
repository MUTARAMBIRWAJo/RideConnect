<?php

namespace App\Console\Commands;

use App\Services\MigrationSafetyService;
use Illuminate\Console\Command;

class MigrationAuditCommand extends Command
{
    protected $signature = 'migrate:audit
                            {--pending : Audit only pending migrations}
                            {--json : Output raw JSON summary to stdout}
                            {--save-report : Persist report under storage/migration-reports}';

    protected $description = 'Audit migration files for destructive patterns and generate a safety report';

    public function handle(MigrationSafetyService $safety): int
    {
        if ($this->option('pending')) {
            $analyses = $safety->analyzePendingMigrations();
            $summary = [
                'scope' => 'pending',
                'total_files' => count($analyses),
                'destructive_up' => count(array_filter($analyses, fn ($a) => $a['up']['is_destructive'] ?? false)),
                'destructive_down' => count(array_filter($analyses, fn ($a) => $a['down']['is_destructive'] ?? false)),
                'high_risk' => count(array_filter($analyses, fn ($a) => ($a['risk_level'] ?? '') === 'high')),
            ];
        } else {
            $audit = $safety->auditAllMigrations();
            $analyses = $audit['files'];
            $summary = $audit['summary'];
            $summary['scope'] = 'all';
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => $summary,
                'files' => $analyses,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Migration Safety Audit');
        $this->line('Environment: '.app()->environment());
        $this->line('Scope: '.$summary['scope']);
        $this->table(
            ['Metric', 'Count'],
            [
                ['Migration files', $summary['total_files']],
                ['Destructive up()', $summary['destructive_up']],
                ['Destructive down()', $summary['destructive_down']],
                ['High risk', $summary['high_risk']],
            ]
        );

        $rows = [];

        foreach ($analyses as $analysis) {
            if (($analysis['risk_level'] ?? 'low') === 'low' && ! ($analysis['up']['is_destructive'] ?? false)) {
                continue;
            }

            $rows[] = [
                $analysis['file'] ?? ($analysis['migration'] ?? '-'),
                $analysis['risk_level'] ?? '-',
                ($analysis['up']['is_destructive'] ?? false) ? 'yes' : 'no',
                ($analysis['down']['is_destructive'] ?? false) ? 'yes' : 'no',
                implode(', ', $analysis['affected_tables'] ?? []),
            ];
        }

        if ($rows !== []) {
            $this->newLine();
            $this->warn('Flagged migrations:');
            $this->table(['File', 'Risk', 'Up', 'Down', 'Tables'], $rows);
        } else {
            $this->info('No risky migration patterns detected in selected scope.');
        }

        if ($this->option('save-report') || $summary['destructive_up'] > 0 || $summary['high_risk'] > 0) {
            $reportPath = $safety->generateReport($analyses, [
                'action' => $this->option('pending') ? 'audit_pending' : 'audit_all',
                'approved' => false,
                'reason' => 'Automated migration audit via migrate:audit',
            ]);

            $this->info('Report saved: '.$reportPath);
        }

        return self::SUCCESS;
    }
}
