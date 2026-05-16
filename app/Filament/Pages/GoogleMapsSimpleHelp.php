<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class GoogleMapsSimpleHelp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Map Help & Guide';

    protected static ?string $navigationGroup = 'Information Hub';

    protected static ?int $navigationSort = 203;

    protected static ?string $title = 'Map Help for Staff';

    protected static string $view = 'filament.pages.google-maps-simple-help';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function getViewData(): array
    {
        $googleKey = trim((string) config('services.google_maps.key'));
        $laramapsKey = trim((string) config('laramaps.api_key'));
        $resolvedKey = $googleKey !== '' ? $googleKey : $laramapsKey;

        return [
            'simpleMapHelp' => [
                'has_key' => $resolvedKey !== '',
                'preflight_url' => route('admin.maps.health.preflight'),
                'live_map_url' => route('api.map.live-data'),
            ],
        ];
    }
}
