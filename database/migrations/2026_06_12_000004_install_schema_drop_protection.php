<?php

use App\Services\DatabaseTableProtectionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        try {
            /** @var DatabaseTableProtectionService $protection */
            $protection = app(DatabaseTableProtectionService::class);

            $protection->lockAllTables('post-migration install_schema_drop_protection');
            $protection->installPostgresDropGuard();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Skipping schema drop protection: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Protection remains active after rollback attempts are blocked in production.
    }
};
