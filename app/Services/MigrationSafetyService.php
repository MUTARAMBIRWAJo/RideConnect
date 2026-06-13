<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrationSafetyService
{
    /** @var list<string> */
    private const DESTRUCTIVE_PATTERNS = [
        'drop_if_exists' => '/Schema::dropIfExists\s*\(\s*[\'"]([\w]+)[\'"]\s*\)/i',
        'schema_drop' => '/Schema::drop\s*\(\s*[\'"]([\w]+)[\'"]\s*\)/i',
        'drop_column' => '/->dropColumn\s*\(\s*(?:\[([\s\S]*?)\]|\'([\w]+)\'|"([\w]+)")/i',
        'drop_foreign' => '/->dropForeign\s*\(\s*(?:\[([\s\S]*?)\]|\'([\w]+)\'|"([\w]+)")/i',
        'drop_index' => '/->drop(?:Unique|Index)\s*\(\s*(?:\[([\s\S]*?)\]|\'([\w]+)\'|"([\w]+)")/i',
        'truncate' => '/\btruncate\s*\(\s*[\'"]([\w]+)[\'"]/i',
        'truncate_table' => '/DB::table\s*\(\s*[\'"]([\w]+)[\'"]\s*\)->truncate\s*\(\s*\)/i',
        'delete_all' => '/DB::table\s*\(\s*[\'"]([\w]+)[\'"]\s*\)->delete\s*\(\s*\)/i',
    ];

    /**
     * @return list<string>
     */
    public function migrationPaths(): array
    {
        $paths = array_merge(
            glob(database_path('migrations/*.php')) ?: [],
            glob(database_path('migrations/*/*.php')) ?: []
        );

        sort($paths);

        return $paths;
    }

    /**
     * @return array{
     *     summary: array{total_files: int, destructive_up: int, destructive_down: int, high_risk: int},
     *     files: list<array<string, mixed>>
     * }
     */
    public function auditAllMigrations(): array
    {
        $files = [];
        $destructiveUp = 0;
        $destructiveDown = 0;
        $highRisk = 0;

        foreach ($this->migrationPaths() as $path) {
            $analysis = $this->analyzeMigrationFile($path);
            $files[] = $analysis;

            if ($analysis['up']['is_destructive']) {
                $destructiveUp++;
            }

            if ($analysis['down']['is_destructive']) {
                $destructiveDown++;
            }

            if ($analysis['risk_level'] === 'high') {
                $highRisk++;
            }
        }

        return [
            'summary' => [
                'total_files' => count($files),
                'destructive_up' => $destructiveUp,
                'destructive_down' => $destructiveDown,
                'high_risk' => $highRisk,
            ],
            'files' => $files,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyzeMigrationFile(string $path): array
    {
        $contents = File::get($path);
        $basename = basename($path);

        $upBody = $this->extractMethodBody($contents, 'up');
        $downBody = $this->extractMethodBody($contents, 'down');

        $upFindings = $this->scanForDestructiveOperations($upBody);
        $downFindings = $this->scanForDestructiveOperations($downBody);

        $policyTables = config('database_protection.policy_tables', []);
        $affectedTables = array_values(array_unique(array_merge(
            $upFindings['tables'],
            $downFindings['tables']
        )));

        $touchesPolicyTable = ! empty(array_intersect($affectedTables, $policyTables));

        return [
            'file' => $basename,
            'path' => $path,
            'up' => [
                'is_destructive' => $upFindings['operations'] !== [],
                'operations' => $upFindings['operations'],
                'tables' => $upFindings['tables'],
                'columns' => $upFindings['columns'],
            ],
            'down' => [
                'is_destructive' => $downFindings['operations'] !== [],
                'operations' => $downFindings['operations'],
                'tables' => $downFindings['tables'],
                'columns' => $downFindings['columns'],
            ],
            'affected_tables' => $affectedTables,
            'touches_policy_table' => $touchesPolicyTable,
            'risk_level' => $this->resolveRiskLevel($upFindings, $downFindings, $touchesPolicyTable),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function analyzePendingMigrations(): array
    {
        $pending = [];

        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
        } catch (\Throwable) {
            return [];
        }

        foreach ($this->migrationPaths() as $path) {
            $name = Str::before(basename($path), '.php');

            if (in_array($name, $ran, true)) {
                continue;
            }

            $analysis = $this->analyzeMigrationFile($path);
            $analysis['migration'] = $name;
            $analysis['pending'] = true;
            $pending[] = $analysis;
        }

        return $pending;
    }

    /**
     * @param  list<array<string, mixed>>  $analyses
     * @param  array<string, mixed>  $context
     */
    public function generateReport(array $analyses, array $context = []): string
    {
        $directory = (string) config('database_protection.reports_path', storage_path('migration-reports'));

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $slug = Str::slug((string) ($context['action'] ?? 'audit'));
        $filename = "{$timestamp}_{$slug}.json";
        $markdownFilename = "{$timestamp}_{$slug}.md";
        $reportPath = $directory.'/'.$filename;
        $markdownPath = $directory.'/'.$markdownFilename;

        $affectedTables = [];
        $affectedColumns = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis['affected_tables'] ?? [] as $table) {
                $affectedTables[$table] = true;
            }

            foreach ($analysis['up']['columns'] ?? [] as $column) {
                $affectedColumns[] = $column;
            }
        }

        $tables = array_keys($affectedTables);
        $recordEstimates = $this->estimateRecordCounts($tables);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'database' => config('database.default'),
            'action' => $context['action'] ?? 'audit',
            'approved' => (bool) ($context['approved'] ?? false),
            'reason' => $context['reason'] ?? null,
            'affected_tables' => $tables,
            'affected_columns' => array_values(array_unique($affectedColumns)),
            'estimated_records' => $recordEstimates,
            'rollback_plan' => $this->buildRollbackPlan($analyses),
            'migrations' => $analyses,
            'totals' => [
                'migration_count' => count($analyses),
                'estimated_rows_at_risk' => array_sum(array_map(
                    static fn (array $row) => $row['count'] ?? 0,
                    $recordEstimates
                )),
            ],
        ];

        File::put($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        File::put($markdownPath, $this->renderMarkdownReport($payload));

        return $reportPath;
    }

    public function isProductionGuardActive(): bool
    {
        if (! config('database_protection.enabled', true)) {
            return false;
        }

        if (app()->runningUnitTests() && ! config('database_protection.enable_during_tests', false)) {
            return false;
        }

        $environments = config('database_protection.guard_environments', ['production']);

        return app()->environment($environments);
    }

    public function hasDestructiveApproval(mixed $input): bool
    {
        if (is_object($input) && method_exists($input, 'hasParameterOption')) {
            if ($input->hasParameterOption('--approve-destructive')) {
                return true;
            }
        }

        if (filter_var(env('MIGRATION_APPROVE_DESTRUCTIVE', false), FILTER_VALIDATE_BOOL)) {
            return true;
        }

        return in_array('--approve-destructive', $_SERVER['argv'] ?? [], true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingDestructiveUpMigrations(): array
    {
        return array_values(array_filter(
            $this->analyzePendingMigrations(),
            static fn (array $analysis) => (bool) ($analysis['up']['is_destructive'] ?? false)
        ));
    }

    private function extractMethodBody(string $contents, string $method): string
    {
        $pattern = '/function\s+'.$method.'\s*\([^)]*\)\s*(?::\s*\w+\s*)?\{/i';

        if (! preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $length = strlen($contents);

        for ($i = $start; $i < $length; $i++) {
            $char = $contents[$i];

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($contents, $start, $i - $start);
                }
            }
        }

        return '';
    }

    /**
     * @return array{operations: list<array<string, string>>, tables: list<string>, columns: list<string>}
     */
    private function scanForDestructiveOperations(string $body): array
    {
        $operations = [];
        $tables = [];
        $columns = [];

        foreach (self::DESTRUCTIVE_PATTERNS as $type => $pattern) {
            if (! preg_match_all($pattern, $body, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                if (in_array($type, ['drop_if_exists', 'schema_drop', 'truncate'], true)) {
                    $identifier = $match[1] ?? '';
                    if ($identifier !== '') {
                        $tables[] = $identifier;
                    }

                    $operations[] = [
                        'type' => $type,
                        'snippet' => Str::limit(trim($match[0]), 120),
                        'targets' => array_filter([$identifier]),
                    ];

                    continue;
                }

                $raw = $match[1] ?? ($match[2] ?? ($match[3] ?? ''));
                $extractedTables = $this->extractIdentifiers($raw);

                foreach ($extractedTables as $identifier) {
                    if (str_contains($type, 'column') || str_contains($type, 'foreign') || str_contains($type, 'index')) {
                        $columns[] = $identifier;
                    } else {
                        $tables[] = $identifier;
                    }
                }

                $operations[] = [
                    'type' => $type,
                    'snippet' => Str::limit(trim($match[0]), 120),
                    'targets' => $extractedTables,
                ];
            }
        }

        return [
            'operations' => $operations,
            'tables' => array_values(array_unique(array_filter($tables))),
            'columns' => array_values(array_unique(array_filter($columns))),
        ];
    }

    /**
     * @return list<string>
     */
    private function extractIdentifiers(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        preg_match_all('/[\'"]([\w]+)[\'"]/', $raw, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param  array{operations: list<array<string, string>>, tables: list<string>, columns: list<string>}  $upFindings
     * @param  array{operations: list<array<string, string>>, tables: list<string>, columns: list<string>}  $downFindings
     */
    private function resolveRiskLevel(array $upFindings, array $downFindings, bool $touchesPolicyTable): string
    {
        $upTypes = array_column($upFindings['operations'], 'type');

        if ($touchesPolicyTable && (
            in_array('drop_if_exists', $upTypes, true)
            || in_array('schema_drop', $upTypes, true)
            || in_array('truncate', $upTypes, true)
            || in_array('truncate_table', $upTypes, true)
            || in_array('delete_all', $upTypes, true)
        )) {
            return 'high';
        }

        if ($upFindings['operations'] !== []) {
            return $touchesPolicyTable ? 'high' : 'medium';
        }

        if ($downFindings['operations'] !== [] && $touchesPolicyTable) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  list<string>  $tables
     * @return list<array{table: string, count: int|null, exists: bool}>
     */
    private function estimateRecordCounts(array $tables): array
    {
        $estimates = [];

        foreach ($tables as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $estimates[] = [
                        'table' => $table,
                        'count' => 0,
                        'exists' => false,
                    ];

                    continue;
                }

                $count = (int) DB::table($table)->count();
            } catch (\Throwable) {
                $estimates[] = [
                    'table' => $table,
                    'count' => null,
                    'exists' => null,
                ];

                continue;
            }

            $estimates[] = [
                'table' => $table,
                'count' => $count,
                'exists' => true,
            ];
        }

        return $estimates;
    }

    /**
     * @param  list<array<string, mixed>>  $analyses
     * @return list<string>
     */
    private function buildRollbackPlan(array $analyses): array
    {
        $steps = [
            'Take a Supabase point-in-time backup before any approved destructive change.',
            'Run the inverse migration only in a staging clone first.',
            'Re-run `php artisan migrate:audit` and attach the report to the change ticket.',
        ];

        foreach ($analyses as $analysis) {
            if (! ($analysis['down']['is_destructive'] ?? false)) {
                continue;
            }

            $steps[] = sprintf(
                'Rollback file %s via `php artisan migrate:rollback --step=1 --approve-destructive` after staging validation.',
                $analysis['file'] ?? 'unknown'
            );
        }

        return array_values(array_unique($steps));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderMarkdownReport(array $payload): string
    {
        $lines = [
            '# Migration Safety Report',
            '',
            '- **Generated:** '.($payload['generated_at'] ?? now()->toIso8601String()),
            '- **Environment:** '.($payload['environment'] ?? 'unknown'),
            '- **Action:** '.($payload['action'] ?? 'audit'),
            '- **Approved:** '.(($payload['approved'] ?? false) ? 'yes' : 'no'),
            '',
            '## Affected Tables',
            '',
        ];

        foreach ($payload['estimated_records'] ?? [] as $row) {
            $count = $row['count'] ?? 'unknown';
            $exists = ($row['exists'] ?? false) ? 'yes' : 'no';
            $lines[] = sprintf('- `%s` — records: %s — exists: %s', $row['table'], $count, $exists);
        }

        $lines[] = '';
        $lines[] = '## Affected Columns';
        $lines[] = '';

        foreach ($payload['affected_columns'] ?? [] as $column) {
            $lines[] = '- `'.$column.'`';
        }

        if (($payload['affected_columns'] ?? []) === []) {
            $lines[] = '- *(none detected)*';
        }

        $lines[] = '';
        $lines[] = '## Reason';
        $lines[] = '';
        $lines[] = (string) ($payload['reason'] ?? 'Automated migration safety audit.');
        $lines[] = '';
        $lines[] = '## Rollback Plan';
        $lines[] = '';

        foreach ($payload['rollback_plan'] ?? [] as $step) {
            $lines[] = '- '.$step;
        }

        $lines[] = '';
        $lines[] = '## Migrations';
        $lines[] = '';

        foreach ($payload['migrations'] ?? [] as $migration) {
            $lines[] = '### '.($migration['file'] ?? $migration['migration'] ?? 'unknown');
            $lines[] = '- Risk: **'.($migration['risk_level'] ?? 'unknown').'**';
            $lines[] = '- Destructive `up()`: **'.(($migration['up']['is_destructive'] ?? false) ? 'yes' : 'no').'**';
            $lines[] = '- Destructive `down()`: **'.(($migration['down']['is_destructive'] ?? false) ? 'yes' : 'no').'**';
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }
}
