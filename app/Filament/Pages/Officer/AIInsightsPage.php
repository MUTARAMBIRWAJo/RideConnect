<?php

namespace App\Filament\Pages\Officer;

use App\Enums\UserRole;
use App\Services\DemandPredictionService;
use App\Services\MlService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AIInsightsPage extends Page
{
    protected static ?string $navigationGroup = 'Live Operations';

    protected static string $view = 'filament.pages.officer.ai-insights';

    // Demand metrics
    public array $demandByArea = [];

    public array $peakHours = [];

    public array $trendData = [];

    public ?float $avgWaitTime = null;

    public ?float $acceptanceRate = null;

    // ML Service demand prediction
    public array $mlDemandPrediction = [];

    public string $demandPredictionStatus = 'loading';

    public string $mlServiceUrl = '';

    public array $mlDemandPayload = [];

    public array $mlServiceHealth = [];

    public static function getNavigationLabel(): string
    {
        return 'AI Insights';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-light-bulb';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ($user->role === UserRole::OFFICER)
            || $user->hasAnyRole(['Officer', 'officer', 'OFFICER']);
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
        $this->mlServiceUrl = $this->resolveMlServiceUrl();

        // Load real ML service demand predictions
        $this->loadMLServiceDemandPredictions();

        $this->demandByArea = $this->loadDemandByArea();
        $this->peakHours = $this->loadPeakHours();
        $this->trendData = $this->loadTrendData();
        $this->avgWaitTime = $this->loadAverageWaitTime();
        $this->acceptanceRate = $this->loadAcceptanceRate();
    }

    private function loadDemandByArea(): array
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'origin_address')) {
            return [];
        }

        return DB::table('rides')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('origin_address')
            ->selectRaw('origin_address as area, COUNT(*) as demand')
            ->groupBy('origin_address')
            ->orderByDesc('demand')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'area' => (string) $row->area,
                'demand' => (int) $row->demand,
                'available_drivers' => null,
            ])
            ->all();
    }

    private function loadPeakHours(): array
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'created_at') || ! Schema::hasColumn('rides', 'status')) {
            return [];
        }

        return DB::table('rides')
            ->where('created_at', '>=', now()->subDay())
            ->whereIn('status', ['completed', 'COMPLETED'])
            ->selectRaw('EXTRACT(HOUR FROM created_at) as hour_bucket, COUNT(*) as demand')
            ->groupBy('hour_bucket')
            ->orderByDesc('demand')
            ->limit(4)
            ->get()
            ->map(function ($row): array {
                $hour = (int) $row->hour_bucket;
                $demand = (int) $row->demand;

                return [
                    'hour' => sprintf('%02d:00 - %02d:59', $hour, $hour),
                    'demand' => $demand >= 20 ? 'Very High' : ($demand >= 10 ? 'High' : ($demand >= 5 ? 'Medium' : 'Low')),
                    'color' => $demand >= 20 ? 'red' : ($demand >= 10 ? 'orange' : 'yellow'),
                ];
            })
            ->all();
    }

    private function loadTrendData(): array
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'created_at')) {
            return [];
        }

        $days = collect(range(6, 0))->map(fn (int $offset) => now()->subDays($offset)->startOfDay())->values();

        return $days->map(function ($date): array {
            $rides = (int) DB::table('rides')
                ->whereDate('created_at', $date->toDateString())
                ->whereIn('status', ['completed', 'COMPLETED'])
                ->count();

            $revenue = 0.0;
            if (Schema::hasColumn('rides', 'total_price')) {
                $revenue = (float) DB::table('rides')
                    ->whereDate('created_at', $date->toDateString())
                    ->whereIn('status', ['completed', 'COMPLETED'])
                    ->sum('total_price');
            }

            return [
                'date' => $date->format('D'),
                'rides' => $rides,
                'revenue' => $revenue,
            ];
        })->filter(fn (array $day): bool => $day['rides'] > 0 || $day['revenue'] > 0)->values()->all();
    }

    private function loadAverageWaitTime(): ?float
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'created_at') || ! Schema::hasColumn('rides', 'started_at')) {
            return null;
        }

        $avgWaitSeconds = DB::table('rides')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('started_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (started_at - created_at))) as avg_wait')
            ->value('avg_wait');

        return $avgWaitSeconds !== null ? round(((float) $avgWaitSeconds) / 60, 2) : null;
    }

    private function loadAcceptanceRate(): ?float
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'created_at') || ! Schema::hasColumn('rides', 'status')) {
            return null;
        }

        $total = (int) DB::table('rides')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($total === 0) {
            return null;
        }

        $accepted = (int) DB::table('rides')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereIn('status', ['accepted', 'ACCEPTED', 'started', 'STARTED', 'completed', 'COMPLETED'])
            ->count();

        return round(($accepted / $total) * 100, 1);
    }

    /**
     * Load demand predictions from the ML service
     */
    private function loadMLServiceDemandPredictions(): void
    {
        try {
            $mlService = app(MlService::class);
            $this->mlServiceHealth = $mlService->health();
            $this->mlDemandPayload = $this->buildDemandPayload();

            Log::info('Fetching demand prediction from ML service', [
                'base_url' => $this->mlServiceUrl,
                'payload' => $this->mlDemandPayload,
            ]);

            $response = $mlService->predictDemand($this->mlDemandPayload);
            $forecastValue = (float) ($response['data']['demand_level'] ?? 0);

            if (($response['success'] ?? false) && $forecastValue > 0) {
                $this->mlDemandPrediction = [
                    'source' => 'ml-service',
                    'predicted_demand' => $forecastValue,
                    'predicted_demand_raw' => $response['data'],
                    'input_payload' => $this->mlDemandPayload,
                    'timestamp' => now()->toIso8601String(),
                ];
                $this->demandPredictionStatus = 'success';

                Log::info('Demand prediction loaded successfully', $this->mlDemandPrediction);
            } else {
                Log::warning('Invalid demand prediction response', $response);

                $fallback = app(DemandPredictionService::class)->predict();
                $fallbackIntensity = (float) ($fallback->max('intensity') ?? 0.0);

                $this->mlDemandPrediction = [
                    'source' => 'local-fallback',
                    'predicted_demand' => $fallbackIntensity,
                    'predicted_demand_raw' => $fallback->values()->all(),
                    'input_payload' => $this->mlDemandPayload,
                    'remote_error' => $response['error'] ?? 'Unknown error',
                    'timestamp' => now()->toIso8601String(),
                ];
                $this->demandPredictionStatus = 'fallback';
            }
        } catch (\Exception $e) {
            Log::error('Failed to load demand prediction', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $fallback = app(DemandPredictionService::class)->predict();
            $fallbackIntensity = (float) ($fallback->max('intensity') ?? 0.0);

            $this->demandPredictionStatus = 'fallback';
            $this->mlDemandPrediction = [
                'source' => 'local-fallback',
                'predicted_demand' => $fallbackIntensity,
                'predicted_demand_raw' => $fallback->values()->all(),
                'input_payload' => $this->mlDemandPayload,
                'error' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ];
        }
    }

    private function buildDemandPayload(): array
    {
        $now = now('Africa/Kigali');
        $anchor = $this->resolveDemandAnchor();

        return [
            'latitude' => $anchor[0],
            'longitude' => $anchor[1],
            'hour' => $now->hour,
            'day_of_week' => $now->dayOfWeek,
        ];
    }

    private function resolveDemandAnchor(): array
    {
        if (Schema::hasTable('rides') && Schema::hasColumn('rides', 'origin_lat') && Schema::hasColumn('rides', 'origin_lng')) {
            $row = DB::table('rides')
                ->whereNotNull('origin_lat')
                ->whereNotNull('origin_lng')
                ->latest('created_at')
                ->first(['origin_lat', 'origin_lng']);

            if ($row) {
                return [(float) $row->origin_lat, (float) $row->origin_lng];
            }
        }

        return [-1.9579, 30.1127];
    }

    private function resolveMlServiceUrl(): string
    {
        $configuredUrl = config('services.ml_service.url') ?: config('services.ai_service.url', 'https://ml-service-j72g.onrender.com');

        if (empty($configuredUrl) || ! str_contains($configuredUrl, 'ml-service-j72g.onrender.com')) {
            return 'https://ml-service-j72g.onrender.com';
        }

        return $configuredUrl;
    }
}
