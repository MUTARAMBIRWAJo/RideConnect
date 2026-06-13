<?php

namespace App\Services\Health\Checks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseHealthCheck
{
    /**
     * @return array<string, mixed>
     */
    public function check(bool $extended = false): array
    {
        $timeoutMs = (int) config('health.timeouts.database_ms', 3000);

        return \App\Services\HealthCheckService::timed(function () use ($extended) {
            DB::connection()->getPdo();
            DB::select('SELECT 1 AS health_check');

            $details = [
                'driver' => DB::connection()->getDriverName(),
                'database' => DB::connection()->getDatabaseName(),
            ];

            if ($extended) {
                $details['migration_status'] = $this->migrationStatus();
            }

            return [
                'ok' => true,
                'status' => 'ok',
                'message' => 'PostgreSQL connection healthy',
                'details' => $details,
            ];
        }, $timeoutMs);
    }

    /**
     * @return array<string, mixed>
     */
    private function migrationStatus(): array
    {
        if (! Schema::hasTable('migrations')) {
            return [
                'table_exists' => false,
                'pending_count' => null,
                'up_to_date' => false,
            ];
        }

        $files = glob(database_path('migrations/*.php')) ?: [];
        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (! in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        return [
            'table_exists' => true,
            'applied_count' => count($ran),
            'pending_count' => count($pending),
            'pending' => array_slice($pending, 0, 10),
            'up_to_date' => $pending === [],
        ];
    }
}
