<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Pages\Page;

class GoogleMapsHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Google Maps Health';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 90;
    protected static ?string $title = 'Google Maps Health Check';
    protected static string $view = 'filament.pages.google-maps-health';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $enumRole = $user->role?->value ?? (string) $user->role;
        if (in_array($enumRole, UserRole::managerRoles(), true)) {
            return true;
        }

        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['Super_admin', 'Admin', 'Officer', 'Accountant'])
            : false;
    }

    protected function getViewData(): array
    {
        $googleKey = trim((string) config('services.google_maps.key'));
        $laramapsKey = trim((string) config('laramaps.api_key'));
        $resolvedKey = $googleKey !== '' ? $googleKey : $laramapsKey;

        $configSource = 'missing';
        if ($googleKey !== '') {
            $configSource = 'services.google_maps.key';
        } elseif ($laramapsKey !== '') {
            $configSource = 'laramaps.api_key';
        }

        return [
            'googleMapsHealth' => [
                'has_key' => $resolvedKey !== '',
                'config_source' => $configSource,
                'api_key' => $resolvedKey,
                'preflight_url' => route('admin.maps.health.preflight'),
                'live_map_url' => route('api.map.live-data'),
            ],
        ];
    }
}
