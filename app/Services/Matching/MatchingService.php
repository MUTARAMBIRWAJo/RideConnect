<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    protected string $url;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->url = config('rideconnect.ml.service_url');
        $this->apiKey = config('rideconnect.ml.api_key');
        $this->timeout = config('rideconnect.ml.timeout', 10);
    }

    /**
     * Query the external ML service to get ranked drivers.
     * Does NOT mutate Supabase state directly.
     *
     * @param array $tripPayload
     * @param array $driversPayload
     * @return array
     */
    public function getRankedDrivers(array $tripPayload, array $driversPayload): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                ])
                ->post("{$this->url}/match", [
                    'trip' => $tripPayload,
                    'drivers' => $driversPayload,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[MatchingService] ML service returned error status', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[MatchingService] Exception contacting ML matching service', [
                'error' => $e->getMessage(),
            ]);
        }

        return [];
    }
}
