<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;

trait UsesIdempotentSeeding
{
    protected function syncModelRole(int $userId, int $roleId, string $modelType = 'App\\Models\\User'): void
    {
        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id' => $roleId,
                'model_id' => $userId,
                'model_type' => $modelType,
            ],
            []
        );
    }
}
