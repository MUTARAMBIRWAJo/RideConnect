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

        if (self::isSlowMode()) {
            return config('dashboard.performance.slow_profile.polling.default', '240s');
        }

        return config('dashboard.realtime.polling_interval', '30s');
    }

    public static function pollingIntervalForWidget(string $widgetClass, ?string $fallback = null): ?string
    {
        if (!config('dashboard.realtime.enabled', true)) {
            return null;
        }

        if (self::isSlowMode()) {
            $slowWidgetIntervals = config('dashboard.performance.slow_profile.polling.widgets', []);
            $value = $slowWidgetIntervals[$widgetClass]
                ?? config('dashboard.performance.slow_profile.polling.default', $fallback ?? self::pollingInterval() ?? '240s');

            return is_string($value) && $value !== '' ? $value : ($fallback ?? self::pollingInterval());
        }

        $widgetIntervals = config('dashboard.performance.polling.widgets', []);

        $value = $widgetIntervals[$widgetClass]
            ?? config('dashboard.performance.polling.default', $fallback ?? self::pollingInterval() ?? '30s');

        return is_string($value) && $value !== '' ? $value : ($fallback ?? self::pollingInterval());
    }

    public static function sectionPollingInterval(string $section, ?string $fallback = null): ?string
    {
        if (!config('dashboard.realtime.enabled', true)) {
            return null;
        }

        if (self::isSlowMode()) {
            $slowSectionIntervals = config('dashboard.performance.slow_profile.polling.sections', []);
            $value = $slowSectionIntervals[$section]
                ?? config('dashboard.performance.slow_profile.polling.default', $fallback ?? self::pollingInterval() ?? '240s');

            return is_string($value) && $value !== '' ? $value : ($fallback ?? self::pollingInterval());
        }

        $sectionIntervals = config('dashboard.performance.polling.sections', []);

        $value = $sectionIntervals[$section]
            ?? config('dashboard.performance.polling.default', $fallback ?? self::pollingInterval() ?? '30s');

        return is_string($value) && $value !== '' ? $value : ($fallback ?? self::pollingInterval());
    }

    public static function isWidgetLazy(string $widgetClass, bool $fallback = true): bool
    {
        if (self::isSlowMode()) {
            $default = (bool) config('dashboard.performance.slow_profile.lazy.default', true);

            return (bool) config("dashboard.performance.slow_profile.lazy.widgets.{$widgetClass}", $default);
        }

        $default = (bool) config('dashboard.performance.lazy.default', $fallback);

        return (bool) (config("dashboard.performance.lazy.widgets.{$widgetClass}", $default));
    }

    private static function isSlowMode(): bool
    {
        return (bool) config('dashboard.performance.slow_mode', false);
    }
}
