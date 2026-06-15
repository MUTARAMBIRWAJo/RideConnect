<?php

namespace App\Filament\Widgets\SuperAdmin;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SystemHealthWidget extends Widget
{
    protected static string $view = 'filament.widgets.super-admin.system-health-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'checks' => Cache::remember('dashboard.system.health', 60, function (): array {
                return [
                    'api' => $this->checkApi(),
                    'ai' => $this->checkAiService(),
                    'database' => $this->checkDatabase(),
                    'queue' => $this->checkQueue(),
                    'firebase' => $this->checkFirebase(),
                ];
            }),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkApi(): array
    {
        return ['status' => 'ok', 'message' => 'Laravel API operational'];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkAiService(): array
    {
        try {
            $url = rtrim((string) config('services.ai_service.url', 'https://rideconnect-ai.onrender.com'), '/');
            $response = Http::timeout((int) config('services.ai_service.timeout', 8))->get($url.'/health');

            if ($response->successful()) {
                return ['status' => 'ok', 'message' => 'AI service reachable'];
            }

            return ['status' => 'warn', 'message' => 'AI health check returned '.$response->status()];
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'down', 'message' => 'AI service unavailable'];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            $engine = DB::connection()->getDriverName();
            $isPostgres = str_contains($engine, 'pgsql');

            return [
                'status' => 'ok',
                'message' => $isPostgres ? 'Supabase PostgreSQL connected' : 'Database connected',
            ];
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'down', 'message' => 'Database connection failed'];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkQueue(): array
    {
        try {
            if (! Schema::hasTable('jobs')) {
                return ['status' => 'warn', 'message' => 'Jobs table not found'];
            }

            $pending = (int) DB::table('jobs')->count();

            if ($pending > 1000) {
                return ['status' => 'warn', 'message' => "High queue backlog ({$pending})"];
            }

            return ['status' => 'ok', 'message' => "Queue healthy ({$pending} pending)"];
        } catch (\Throwable $e) {
            report($e);

            return ['status' => 'down', 'message' => 'Queue status unavailable'];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkFirebase(): array
    {
        try {
            $healthService = app(\App\Services\Firebase\FirebaseHealthService::class);
            if (!$healthService->isEnabled()) {
                return ['status' => 'warn', 'message' => 'Firebase integrations disabled'];
            }

            if (!$healthService->canConnectFirestore() || !$healthService->canConnectMessaging()) {
                return ['status' => 'down', 'message' => 'Firebase connections failing'];
            }

            return ['status' => 'ok', 'message' => 'Firebase Firestore & FCM operational'];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'message' => 'Firebase status check failed: ' . $e->getMessage()];
        }
    }
}
