<?php

namespace App\Console\Commands;

use App\Services\DatabaseTableProtectionService;
use App\Services\MigrationSafetyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationSimulateDeployCommand extends Command
{
    protected $signature = 'migrate:simulate-deploy
                            {--save-report : Persist simulation report}';

    protected $description = 'Simulate a production Render deploy (migrate + protect) and verify no data loss patterns';

    public function handle(
        MigrationSafetyService $safety,
        DatabaseTableProtectionService $protection
    ): int {
        $this->info('Simulating production deployment safety checks...');

        $tablesBefore = $this->snapshotTableCounts();
        $destructivePending = $safety->pendingDestructiveUpMigrations();

        $this->line('Pending migrations: '.count($safety->analyzePendingMigrations()));
        $this->line('Pending destructive up(): '.count($destructivePending));

        if ($destructivePending !== []) {
            $reportPath = $safety->generateReport($destructivePending, [
                'action' => 'simulate_deploy_blocked',
                'approved' => false,
                'reason' => 'Production deploy would be blocked until destructive pending migrations are reviewed.',
            ]);

            $this->error('Deploy simulation FAILED: destructive pending migrations detected.');
            $this->line('Report: '.$reportPath);

            return self::FAILURE;
        }

        $this->info('Running additive migrate --force (production deploy path)...');

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->output->write(Artisan::output());

        if ($exitCode !== self::SUCCESS) {
            $this->error('Migration step failed during simulation.');

            return self::FAILURE;
        }

        $protection->lockPolicyTables('simulate-deploy policy lock');

        $tablesAfter = $this->snapshotTableCounts();
        $dataLoss = $this->detectDataLoss($tablesBefore, $tablesAfter);

        $reportPath = $safety->generateReport([], [
            'action' => 'simulate_deploy',
            'approved' => true,
            'reason' => 'Simulated Render deploy completed without destructive pending migrations.',
        ]);

        if ($dataLoss !== []) {
            $this->error('Deploy simulation detected row-count decreases:');
            $this->table(['Table', 'Before', 'After', 'Delta'], $dataLoss);

            if ($this->option('save-report')) {
                $this->line('Report: '.$reportPath);
            }

            return self::FAILURE;
        }

        $this->info('No row-count decreases detected across policy tables.');
        $this->info('Policy tables locked after simulation.');

        if ($this->option('save-report')) {
            $this->line('Report: '.$reportPath);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function snapshotTableCounts(): array
    {
        $counts = [];

        foreach (config('database_protection.policy_tables', []) as $table) {
            if (! is_string($table) || $table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            try {
                $counts[$table] = (int) DB::table($table)->count();
            } catch (\Throwable) {
                $counts[$table] = -1;
            }
        }

        return $counts;
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return list<array{0: string, 1: int, 2: int, 3: int}>
     */
    private function detectDataLoss(array $before, array $after): array
    {
        $loss = [];

        foreach ($before as $table => $countBefore) {
            if ($countBefore < 0) {
                continue;
            }

            $countAfter = $after[$table] ?? $countBefore;

            if ($countAfter < $countBefore) {
                $loss[] = [$table, $countBefore, $countAfter, $countAfter - $countBefore];
            }
        }

        return $loss;
    }
}
