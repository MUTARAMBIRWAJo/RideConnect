<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class SystemNewsAndUpdates extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'News & Updates';

    protected static ?string $navigationGroup = 'Information Hub';

    protected static ?int $navigationSort = 200;

    protected static ?string $title = 'Platform News & Updates';

    protected static string $view = 'filament.pages.system-news-and-updates';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return true; // All authenticated users can access
    }

    public function getTitle(): string
    {
        return 'Platform News & Updates';
    }

    public static function getNavigationLabel(): string
    {
        return 'News & Updates';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-newspaper';
    }

    /** @return array<int, array<string, mixed>> */
    public function getNewsArticles(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'New Real-Time Tracking Feature Launched',
                'category' => 'Feature Release',
                'excerpt' => 'Real-time vehicle tracking is now available across all regions with improved accuracy and lower latency.',
                'published_at' => '2026-04-08 10:30:00',
                'icon' => 'heroicon-o-map-pin',
                'color' => 'success',
            ],
            [
                'id' => 2,
                'title' => 'Scheduled Maintenance on April 10th',
                'category' => 'Maintenance',
                'excerpt' => 'Services will be temporarily unavailable for 2 hours on April 10th from 2:00 AM to 4:00 AM UTC for system upgrades.',
                'published_at' => '2026-04-07 14:00:00',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => 'warning',
            ],
            [
                'id' => 3,
                'title' => 'Driver Earnings Dashboard Improvements',
                'category' => 'Update',
                'excerpt' => 'Enhanced earnings analytics with trip-based aggregation for better accuracy and real-time data visibility.',
                'published_at' => '2026-04-06 09:15:00',
                'icon' => 'heroicon-o-trending-up',
                'color' => 'primary',
            ],
            [
                'id' => 4,
                'title' => 'New Safety Feature: Emergency Contact Alerts',
                'category' => 'Safety',
                'excerpt' => 'Drivers and passengers can now set emergency contacts who will receive automatic alerts during incidents.',
                'published_at' => '2026-04-05 11:00:00',
                'icon' => 'heroicon-o-bell-alert',
                'color' => 'danger',
            ],
            [
                'id' => 5,
                'title' => 'Q1 2026 Performance Report Released',
                'category' => 'Report',
                'excerpt' => 'Review our quarterly performance metrics, including growth, safety improvements, and operational efficiency gains.',
                'published_at' => '2026-04-01 08:00:00',
                'icon' => 'heroicon-o-chart-bar',
                'color' => 'info',
            ],
        ];
    }
}
