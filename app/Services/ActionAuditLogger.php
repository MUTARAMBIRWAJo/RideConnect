<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActionAuditLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(string $action, string $description, array $context = []): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        $managerId = $user->manager_id ?? null;

        if (!$managerId && Schema::hasTable('managers') && isset($user->email)) {
            $managerId = DB::table('managers')->where('email', $user->email)->value('id');
        }

        if (!$managerId) {
            return;
        }

        $payload = [];

        if (Schema::hasColumn('activity_logs', 'manager_id')) {
            $payload['manager_id'] = $managerId;
        }

        if (Schema::hasColumn('activity_logs', 'action')) {
            $payload['action'] = substr($action, 0, 255);
        }

        if (Schema::hasColumn('activity_logs', 'description')) {
            $encoded = $context === [] ? '' : ' | context='.json_encode($context);
            $payload['description'] = substr($description.$encoded, 0, 2000);
        }

        if (Schema::hasColumn('activity_logs', 'created_at')) {
            $payload['created_at'] = now();
        }

        if ($payload !== []) {
            DB::table('activity_logs')->insert($payload);
        }
    }
}
