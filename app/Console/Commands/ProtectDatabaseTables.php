<?php

namespace App\Console\Commands;

use App\Services\DatabaseTableProtectionService;
use Illuminate\Console\Command;

class ProtectDatabaseTables extends Command
{
    protected $signature = 'db:protect-tables
                            {--all : Lock every table in the connected database schema}
                            {--policy-only : Lock only policy-protected core tables}
                            {--list : List currently locked tables}';

    protected $description = 'Register database tables as locked to block DROP/TRUNCATE operations';

    public function handle(DatabaseTableProtectionService $protection): int
    {
        if ($this->option('list')) {
            $locks = $protection->listLocks();

            if ($locks === []) {
                $this->warn('No table locks recorded yet. Run db:protect-tables --all first.');

                return self::SUCCESS;
            }

            $this->table(
                ['Schema', 'Table', 'Locked', 'Locked At', 'Reason'],
                array_map(static fn (array $lock) => [
                    $lock['schema'],
                    $lock['table'],
                    $lock['is_locked'] ? 'yes' : 'no',
                    $lock['locked_at'] ?? '-',
                    $lock['locked_reason'] ?? '-',
                ], $locks)
            );

            $this->info('Total locked tables: '.count($locks));

            return self::SUCCESS;
        }

        if ($this->option('policy-only')) {
            $result = $protection->lockPolicyTables('policy-only lock');
            $this->info("Locked {$result['locked']} policy tables.");

            return self::SUCCESS;
        }

        $result = $protection->lockAllTables('db:protect-tables --all');
        $this->info("Locked {$result['locked']} tables across the connected database.");
        $this->comment('Laravel command/SQL guards are active when DB_TABLE_PROTECTION=true.');

        return self::SUCCESS;
    }
}
