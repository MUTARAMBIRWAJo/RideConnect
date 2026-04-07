<?php

namespace App\Filament\Pages\Officer;

use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class AIInsightsPage extends Page
{
    protected static ?string $navigationGroup = 'Live Operations';

    protected static string $view = 'filament.pages.officer.ai-insights';

    // Demand metrics
    public array $demandByArea = [];

    public array $peakHours = [];

    public array $trendData = [];

    public float $avgWaitTime = 0;

    public float $acceptanceRate = 0;

    public static function getNavigationLabel(): string
    {
        return 'AI Insights';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-light-bulb';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('officer') || auth()->user()->hasRole('OFFICER'));
    }

    public function getTitle(): string
    {
        return 'AI Analytics & Insights';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadAnalytics();
    }

    private function loadAnalytics(): void
    {
        // Load demand by area from database or cache
        $this->demandByArea = [
            ['area' => 'Downtown', 'demand' => 450, 'available_drivers' => 23],
            ['area' => 'Suburbia', 'demand' => 280, 'available_drivers' => 15],
            ['area' => 'Airport', 'demand' => 180, 'available_drivers' => 8],
            ['area' => 'Industrial', 'demand' => 120, 'available_drivers' => 5],
            ['area' => 'Residential', 'demand' => 200, 'available_drivers' => 12],
        ];

        // Peak hours data
        $this->peakHours = [
            ['hour' => '06:00 - 09:00', 'demand' => 'Very High', 'color' => 'red'],
            ['hour' => '12:00 - 14:00', 'demand' => 'High', 'color' => 'orange'],
            ['hour' => '18:00 - 21:00', 'demand' => 'Very High', 'color' => 'red'],
            ['hour' => '10:00 - 12:00', 'demand' => 'Medium', 'color' => 'yellow'],
        ];

        // Trend data
        $this->trendData = [
            ['date' => 'Mon', 'rides' => 340, 'revenue' => 4200],
            ['date' => 'Tue', 'rides' => 380, 'revenue' => 4600],
            ['date' => 'Wed', 'rides' => 420, 'revenue' => 5100],
            ['date' => 'Thu', 'rides' => 390, 'revenue' => 4800],
            ['date' => 'Fri', 'rides' => 450, 'revenue' => 5500],
            ['date' => 'Sat', 'rides' => 380, 'revenue' => 4900],
            ['date' => 'Sun', 'rides' => 310, 'revenue' => 3800],
        ];

        $this->avgWaitTime = 3.45;
        $this->acceptanceRate = 92.5;
    }
}
