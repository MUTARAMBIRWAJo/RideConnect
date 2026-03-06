<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait TracksAdminActivity
{
    protected function trackAdminActivity(User $user, string $action, string $description): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        if (!$user->manager_id) {
            return;
        }

        DB::table('activity_logs')->insert([
            'manager_id' => $user->manager_id,
            'action' => $action,
            'description' => $description,
            'created_at' => now(),
        ]);
    }
}
