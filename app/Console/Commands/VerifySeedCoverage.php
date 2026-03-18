<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySeedCoverage extends Command
{
    protected $signature = 'seed:verify-coverage {--target=50 : Minimum expected rows per table} {--include-system : Include framework/system tables}';

    protected $description = 'Verify seeded row coverage across database tables';

    public function handle(): int
    {
        $target = max(1, (int) $this->option('target'));
        $includeSystem = (bool) $this->option('include-system');

        $tables = $this->listTables();
        if (empty($tables)) {
            $this->warn('No tables discovered for active database connection.');

            return self::FAILURE;
        }

        if (!$includeSystem) {
            $tables = array_values(array_filter($tables, function (string $table): bool {
                return !in_array($table, $this->systemTables(), true);
            }));
        }

        sort($tables);

        $rows = [];
        $below = [];
        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
            } catch (\Throwable $e) {
                $count = null;
            }

            $status = $count === null
                ? 'error'
                : ($count >= $target ? 'ok' : 'low');

            $rows[] = [
                'table' => $table,
                'rows' => $count === null ? 'n/a' : number_format($count),
                'status' => $status,
            ];

            if ($status === 'low') {
                $below[] = $table;
            }
        }

        $this->table(['Table', 'Rows', 'Status'], $rows);

        if (!empty($below)) {
            $this->warn(sprintf('%d table(s) below target=%d.', count($below), $target));

            return self::FAILURE;
        }

        $this->info(sprintf('All checked tables are at or above target=%d rows.', $target));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function listTables(): array
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'pgsql' => array_map(
                static fn ($row): string => $row->tablename,
                DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
            ),
            'mysql' => array_map(
                static fn ($row): string => array_values((array) $row)[0],
                DB::select('SHOW TABLES')
            ),
            'sqlite' => array_map(
                static fn ($row): string => $row->name,
                DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ),
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function systemTables(): array
    {
        return [
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
            'sessions',
            'personal_access_tokens',
            'imports',
            'exports',
            'failed_import_rows',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
            'permissions',
            'roles',
        ];
    }
}
