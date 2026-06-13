<?php

namespace App\Services;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** @property-read MigrationSafetyService $migrationSafety */

class DatabaseTableProtectionService
{
    private const LOCKS_TABLE = 'schema_table_locks';

    private const PG_FUNCTION = 'rideconnect_block_locked_table_drop';

    private const PG_TRIGGER = 'rideconnect_protect_locked_tables_on_drop';

    private bool $queryGuardRegistered = false;

    private bool $eventListenersRegistered = false;

    public function __construct(
        private readonly MigrationSafetyService $migrationSafety,
    ) {
    }

    public function isEnabled(): bool
    {
        if (! config('database_protection.enabled', true)) {
            return false;
        }

        if (app()->runningUnitTests() && ! config('database_protection.enable_during_tests', false)) {
            return false;
        }

        return true;
    }

    public function register(): void
    {
        if ($this->eventListenersRegistered) {
            return;
        }

        if (! $this->isEnabled()) {
            return;
        }

        if ($this->migrationSafety->isProductionGuardActive()) {
            DB::prohibitDestructiveCommands(app()->environment('production'));
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $this->assertCommandAllowed($event->command, $event->input);
        });

        $this->eventListenersRegistered = true;

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if (
                $event->exitCode === 0
                && $event->command === 'migrate'
                && config('database_protection.auto_lock_after_migrate', true)
            ) {
                try {
                    $this->lockAllTables('auto-lock after migrate');
                } catch (\Throwable $e) {
                    Log::warning('Auto-lock after migrate failed: '.$e->getMessage());
                }
            }
        });

        if (config('database_protection.auto_lock_after_migrate', true)) {
            Event::listen(MigrationsEnded::class, function (): void {
                try {
                    $this->lockAllTables('auto-lock after migrate');
                } catch (\Throwable $e) {
                    Log::warning('Auto-lock after migrate failed: '.$e->getMessage());
                }
            });
        }

        $this->registerQueryGuard();
    }

    public function ensureLocksTableExists(): void
    {
        if (Schema::hasTable(self::LOCKS_TABLE)) {
            return;
        }

        Schema::create(self::LOCKS_TABLE, function ($table): void {
            $table->id();
            $table->string('schema_name', 64)->default('public');
            $table->string('table_name', 128);
            $table->string('qualified_name', 196);
            $table->boolean('is_locked')->default(true);
            $table->string('locked_reason')->nullable();
            $table->timestampTz('locked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['schema_name', 'table_name']);
            $table->index('is_locked');
        });
    }

    /**
     * @return array{locked: int, total: int}
     */
    public function lockAllTables(string $reason = 'manual lock'): array
    {
        $this->ensureLocksTableExists();

        $tables = $this->discoverTables();
        $locked = 0;

        foreach ($tables as $table) {
            $this->lockTable($table['schema_name'], $table['table_name'], $reason);
            $locked++;
        }

        $this->lockPolicyTables($reason);

        if (config('database_protection.postgres_event_trigger', true)) {
            $this->installPostgresDropGuard();
        }

        return [
            'locked' => $locked,
            'total' => count($tables),
        ];
    }

    /**
     * @return array{locked: int}
     */
    public function lockPolicyTables(string $reason = 'policy lock'): array
    {
        $this->ensureLocksTableExists();

        $locked = 0;

        foreach (config('database_protection.policy_tables', []) as $tableName) {
            if (! is_string($tableName) || $tableName === '') {
                continue;
            }

            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $this->lockTable('public', $tableName, $reason);
            $locked++;
        }

        return ['locked' => $locked];
    }

    public function lockTable(string $schemaName, string $tableName, string $reason = 'locked'): void
    {
        $this->ensureLocksTableExists();

        $qualifiedName = $schemaName.'.'.$tableName;

        DB::table(self::LOCKS_TABLE)->updateOrInsert(
            [
                'schema_name' => $schemaName,
                'table_name' => $tableName,
            ],
            [
                'qualified_name' => $qualifiedName,
                'is_locked' => true,
                'locked_reason' => $reason,
                'locked_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function isTableLocked(string $tableName, string $schemaName = 'public'): bool
    {
        if (! Schema::hasTable(self::LOCKS_TABLE)) {
            return $this->isPolicyTable($tableName);
        }

        return DB::table(self::LOCKS_TABLE)
            ->where('schema_name', $schemaName)
            ->where('table_name', $tableName)
            ->where('is_locked', true)
            ->exists();
    }

    public function assertSqlAllowed(string $sql): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $normalized = strtolower($sql);

        if (! preg_match('/\b(drop\s+table|truncate\s+table)\b/', $normalized)) {
            return;
        }

        foreach ($this->extractTableNamesFromDestructiveSql($sql) as $tableName) {
            if ($this->isTableLocked($tableName) || $this->isPolicyTable($tableName)) {
                throw new RuntimeException(sprintf(
                    'Blocked destructive SQL against locked table "%s". Owner approval required.',
                    $tableName
                ));
            }
        }
    }

    public function installPostgresDropGuard(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $this->ensureLocksTableExists();

        try {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION rideconnect_block_locked_table_drop()
RETURNS event_trigger
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $$
DECLARE
    obj record;
    rel_name text;
    rel_schema text;
BEGIN
    FOR obj IN
        SELECT *
        FROM pg_event_trigger_ddl_commands()
        WHERE command_tag = 'DROP TABLE'
    LOOP
        rel_schema := COALESCE(obj.schema_name, 'public');
        rel_name := COALESCE(obj.object_name, split_part(obj.object_identity, '.', 2));

        IF EXISTS (
            SELECT 1
            FROM schema_table_locks
            WHERE is_locked = true
              AND table_name = rel_name
              AND schema_name = rel_schema
        ) THEN
            RAISE EXCEPTION 'RideConnect: DROP TABLE blocked for locked table %.%', rel_schema, rel_name;
        END IF;
    END LOOP;
END;
$$;
SQL);

            DB::unprepared('DROP EVENT TRIGGER IF EXISTS rideconnect_protect_locked_tables_on_drop;');

            DB::unprepared(<<<'SQL'
CREATE EVENT TRIGGER rideconnect_protect_locked_tables_on_drop
    ON ddl_command_end
    WHEN TAG IN ('DROP TABLE')
    EXECUTE FUNCTION rideconnect_block_locked_table_drop();
SQL);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Postgres DROP guard unavailable: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return list<array{schema_name: string, table_name: string}>
     */
    public function discoverTables(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $rows = DB::select(<<<'SQL'
SELECT schemaname AS schema_name, tablename AS table_name
FROM pg_tables
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY schemaname, tablename
SQL);

            return array_map(static fn ($row) => [
                'schema_name' => (string) $row->schema_name,
                'table_name' => (string) $row->table_name,
            ], $rows);
        }

        $tables = [];

        foreach (Schema::getTableListing() as $qualifiedName) {
            if (! str_contains($qualifiedName, '.')) {
                $tables[] = ['schema_name' => 'public', 'table_name' => $qualifiedName];

                continue;
            }

            [$schema, $table] = explode('.', $qualifiedName, 2);
            $tables[] = ['schema_name' => $schema, 'table_name' => $table];
        }

        return $tables;
    }

    /**
     * @return list<array{schema: string, table: string, qualified_name: string, is_locked: bool, locked_at: ?string, locked_reason: ?string}>
     */
    public function listLocks(): array
    {
        if (! Schema::hasTable(self::LOCKS_TABLE)) {
            return [];
        }

        return DB::table(self::LOCKS_TABLE)
            ->orderBy('schema_name')
            ->orderBy('table_name')
            ->get()
            ->map(static fn ($row) => [
                'schema' => (string) $row->schema_name,
                'table' => (string) $row->table_name,
                'qualified_name' => (string) $row->qualified_name,
                'is_locked' => (bool) $row->is_locked,
                'locked_at' => $row->locked_at ? (string) $row->locked_at : null,
                'locked_reason' => $row->locked_reason ? (string) $row->locked_reason : null,
            ])
            ->all();
    }

    private function registerQueryGuard(): void
    {
        if ($this->queryGuardRegistered) {
            return;
        }

        DB::listen(function ($query): void {
            try {
                $this->assertSqlAllowed($query->sql);
            } catch (RuntimeException $e) {
                throw $e;
            } catch (\Throwable) {
                // Ignore guard parsing issues for non-destructive queries.
            }
        });

        $this->queryGuardRegistered = true;
    }

    private function assertCommandAllowed(?string $command, mixed $input): void
    {
        if ($command === null) {
            return;
        }

        if (! $this->isEnabled()) {
            return;
        }

        $approved = $this->migrationSafety->hasDestructiveApproval($input);
        $guardActive = $this->migrationSafety->isProductionGuardActive();

        $alwaysBlocked = config('database_protection.always_blocked_in_production', []);

        if ($guardActive && in_array($command, $alwaysBlocked, true)) {
            $reportPath = $this->migrationSafety->generateReport([], [
                'action' => 'blocked_command',
                'approved' => false,
                'reason' => sprintf('Command "%s" is permanently blocked in guarded environments.', $command),
            ]);

            throw new RuntimeException(sprintf(
                'Blocked unsafe database command "%s" in %s. Report: %s. Data wipes are never allowed automatically.',
                $command,
                app()->environment(),
                $reportPath
            ));
        }

        $blocked = config('database_protection.blocked_commands', []);

        if (in_array($command, $blocked, true)) {
            $permanentlyBlocked = config('database_protection.always_blocked_in_production', []);

            if (in_array($command, $permanentlyBlocked, true)) {
                $reportPath = $this->migrationSafety->generateReport([], [
                    'action' => 'blocked_command',
                    'approved' => false,
                    'reason' => sprintf('Command "%s" is permanently blocked and cannot be approved.', $command),
                ]);

                throw new RuntimeException(sprintf(
                    'Blocked unsafe database command "%s". Report: %s',
                    $command,
                    $reportPath
                ));
            }

            if ($approved) {
                $this->migrationSafety->generateReport([], [
                    'action' => $command,
                    'approved' => true,
                    'reason' => 'Operator explicitly approved destructive artisan command.',
                ]);

                return;
            }

            $reportPath = $this->migrationSafety->generateReport([], [
                'action' => 'blocked_command',
                'approved' => false,
                'reason' => sprintf('Command "%s" requires %s before execution.', $command, config('database_protection.approval_flag')),
            ]);

            throw new RuntimeException(sprintf(
                'Blocked unsafe database command "%s". Re-run with %s after reviewing report: %s',
                $command,
                config('database_protection.approval_flag'),
                $reportPath
            ));
        }

        if (
            $command === 'schema:dump'
            && is_object($input)
            && method_exists($input, 'hasParameterOption')
            && $input->hasParameterOption('--prune', true)
        ) {
            throw new RuntimeException('Blocked unsafe schema dump prune option.');
        }

        if (
            $guardActive
            && config('database_protection.block_destructive_pending_migrations', true)
            && $command === 'migrate'
            && ! $approved
        ) {
            $destructivePending = $this->migrationSafety->pendingDestructiveUpMigrations();

            if ($destructivePending !== []) {
                $reportPath = $this->migrationSafety->generateReport($destructivePending, [
                    'action' => 'blocked_pending_migrations',
                    'approved' => false,
                    'reason' => 'Pending migrations contain destructive up() operations.',
                ]);

                throw new RuntimeException(sprintf(
                    'Blocked migrate: pending migrations include destructive up() changes. Review report %s and re-run with %s if intentional.',
                    $reportPath,
                    config('database_protection.approval_flag')
                ));
            }
        }

        if ($command === 'db:seed' && $guardActive && ! $approved) {
            $this->assertSeederSafety();
        }
    }

    private function assertSeederSafety(): void
    {
        if (filter_var(env('DB_SEED_ALLOW_DESTRUCTIVE', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $seedersPath = database_path('seeders');

        foreach (glob($seedersPath.'/*.php') ?: [] as $file) {
            $contents = file_get_contents($file) ?: '';

            if (preg_match('/\btruncate\s*\(/i', $contents) || preg_match('/DB::table\([^)]+\)->delete\(\)/', $contents)) {
                if (! preg_match('/model_has_roles/', $contents)) {
                    throw new RuntimeException(sprintf(
                        'Blocked db:seed in guarded environment: seeder %s contains truncate/delete-all patterns. Use updateOrCreate/firstOrCreate.',
                        basename($file)
                    ));
                }
            }
        }
    }

    private function isPolicyTable(string $tableName): bool
    {
        return in_array($tableName, config('database_protection.policy_tables', []), true);
    }

    /**
     * @return list<string>
     */
    private function extractTableNamesFromDestructiveSql(string $sql): array
    {
        $patterns = [
            '/\bdrop\s+table\s+(?:if\s+exists\s+)?(?:"?([\w]+)"?\.)?"?([\w]+)"?/i',
            '/\btruncate\s+table\s+(?:"?([\w]+)"?\.)?"?([\w]+)"?/i',
        ];

        $tables = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $tables[] = $match[2] !== '' ? $match[2] : $match[1];
                }
            }
        }

        return array_values(array_unique(array_filter($tables)));
    }
}
