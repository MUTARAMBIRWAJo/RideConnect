<?php

namespace App\Services\Ml;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MlPredictionLogger
{
    public function log(
        string $modelName,
        ?string $modelVersion,
        string $endpoint,
        array $inputPayload,
        mixed $outputPayload,
        ?int $latencyMs,
        ?int $tripId = null,
    ): void {
        if (! Schema::hasTable('ml_predictions')) {
            return;
        }

        try {
            DB::table('ml_predictions')->insert([
                'trip_id' => $tripId,
                'model_name' => $modelName,
                'model_version' => $modelVersion ?: $this->extractModelVersion($outputPayload),
                'endpoint' => $endpoint,
                'input_payload' => json_encode($inputPayload, JSON_THROW_ON_ERROR),
                'output_payload' => json_encode($outputPayload, JSON_THROW_ON_ERROR),
                'latency_ms' => $latencyMs,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::debug('ML prediction log write failed', [
                'model_name' => $modelName,
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function extractModelVersion(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $version = Arr::get($payload, 'data.model_version')
            ?? Arr::get($payload, 'model_version')
            ?? Arr::get($payload, 'data.ranker_version')
            ?? Arr::get($payload, 'ranker_version');

        return is_scalar($version) ? (string) $version : null;
    }

    public function latencyMs(float $startedAt): int
    {
        return (int) max(1, round((microtime(true) - $startedAt) * 1000));
    }
}
