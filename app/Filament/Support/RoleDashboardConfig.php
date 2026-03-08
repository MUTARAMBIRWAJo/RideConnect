<?php

namespace App\Filament\Support;

class RoleDashboardConfig
{
    /**
     * @return array<class-string>
     */
    public static function widgetsForRole(?string $role): array
    {
        if (!$role) {
            return [];
        }

        $roles = config('dashboard.roles', []);

        return $roles[$role]['widgets'] ?? [];
    }

    /**
     * @return array<string, int>
     */
    public static function columnsForRole(?string $role): array
    {
        $default = config('dashboard.default_columns', ['default' => 1, 'md' => 2, 'xl' => 3]);

        if (!$role) {
            return $default;
        }

        $roles = config('dashboard.roles', []);

        return $roles[$role]['columns'] ?? $default;
    }

    public static function pollingInterval(): ?string
    {
        if (!config('dashboard.realtime.enabled', true)) {
            return null;
        }

        return config('dashboard.realtime.polling_interval', '30s');
    }
}
