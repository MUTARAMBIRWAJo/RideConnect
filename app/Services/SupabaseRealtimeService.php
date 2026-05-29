<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseRealtimeService
{
    public function broadcast(string $channel, string $event, array $payload): void
    {
        $url = rtrim((string) config('services.supabase.url'), '/');
        $serviceKey = (string) config('services.supabase.service_key');

        if ($url === '' || $serviceKey === '') {
            return;
        }

        try {
            Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Content-Type' => 'application/json',
            ])->post($url.'/realtime/v1/api/broadcast', [
                'messages' => [[
                    'topic' => $channel,
                    'event' => $event,
                    'payload' => $payload,
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Supabase realtime broadcast failed', [
                'channel' => $channel,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
