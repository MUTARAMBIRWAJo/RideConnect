<?php

namespace App\Jobs;

use App\Models\AiPredictionLog;
use App\Models\Driver;
use App\Models\DriverBehavior;
use App\Models\MlPrediction;
use App\Models\RideEvent;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use App\Models\TripStatusEvent;
use App\Models\UserNotification;
use App\Services\MobilePushService;
use App\Services\TfliteMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FindAndNotifyDriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 5;

    public function __construct(public readonly int $tripId) {}

    public function handle(MobilePushService $pushService): void
    {
        \Illuminate\Support\Facades\Log::info('MATCHING_JOB_START', ['trip_id' => $this->tripId]);
        $startMs = floor(microtime(true) * 1000);

        $trip = Trip::query()->with(['passenger', 'matchingSession'])->find($this->tripId);

        if (! $trip || $trip->status !== 'requested' || $trip->assignment_status !== 'unassigned') {
            return;
        }

        $candidates = $this->candidateDrivers($trip);
        \Illuminate\Support\Facades\Log::info('DRIVER_POOL_SIZE_AFTER_FILTER', ['trip_id' => $trip->id, 'count' => $candidates->count()]);

        if ($candidates->isEmpty()) {
            $this->cancelNoDriverTrip($trip, $pushService);

            return;
        }

        $mlResult = $this->rankDrivers($trip, $candidates);
        $ranking = $mlResult['ranked_drivers'] ?? [];
        $topDriver = $ranking[0] ?? null;

        if (! $topDriver) {
            $this->cancelNoDriverTrip($trip, $pushService);

            return;
        }

        $attempt = DB::transaction(function () use ($trip, $topDriver, $mlResult): ?TripAssignmentAttempt {
            $updated = Trip::query()
                ->where('id', $trip->id)
                ->where('assignment_status', 'unassigned')
                ->update([
                    'assignment_status' => 'assigning',
                    'status' => 'assigning',
                    'ranker_score' => $topDriver['score'],
                    'ranker_version' => $mlResult['model_version'] ?? 'unknown',
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                return null;
            }

            $attempt = TripAssignmentAttempt::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $topDriver['driver_id'],
                'score' => $topDriver['score'],
                'score_breakdown' => $topDriver['score_breakdown'] ?? null,
                'status' => 'pending',
                'expires_at' => now()->addSeconds(30),
            ]);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => 'system',
                'old_status' => 'requested',
                'new_status' => 'assigning',
                'metadata' => ['driver_id' => $topDriver['driver_id']],
                'created_at' => now(),
            ]);

            Trip::query()->where('id', $trip->id)->update([
                'current_assignment_attempt_id' => $attempt->id,
                'updated_at' => now(),
            ]);

            DB::table('matching_sessions')
                ->where('matching_session_id', $trip->matching_session_id)
                ->update([
                    'selected_driver_id' => $topDriver['driver_id'],
                    'status' => 'matched',
                    'updated_at' => now(),
                ]);

            Driver::query()->where('id', $topDriver['driver_id'])->update([
                'availability_status' => 'busy',
                'updated_at' => now(),
            ]);

            return $attempt;
        });

        if (! $attempt) {
            return;
        }

        $driver = Driver::query()->find($topDriver['driver_id']);
        if (! $driver) {
            return;
        }

        $payload = [
            'type' => 'trip_request',
            'trip_id' => $trip->id,
            'passenger_name' => trim($trip->passenger?->first_name.' '.$trip->passenger?->last_name),
            'pickup_location' => $trip->pickup_location,
            'dropoff_location' => $trip->dropoff_location,
            'pickup_lat' => (string) $trip->pickup_lat,
            'pickup_lng' => (string) $trip->pickup_lng,
            'dropoff_lat' => (string) $trip->dropoff_lat,
            'dropoff_lng' => (string) $trip->dropoff_lng,
            'fare' => (string) $trip->fare,
            'transport_type' => $trip->transport_type,
            'expires_at' => $attempt->expires_at?->toIso8601String(),
        ];

        $pushService->sendToMobileUser((int) $driver->user_id, 'New Ride Request', 'Passenger at '.$trip->pickup_location.' needs a '.$trip->transport_type, $payload);

        UserNotification::query()->create([
            'user_id' => (int) $driver->user_id,
            'type' => 'trip.new_request',
            'title' => 'New Ride Request',
            'message' => 'Passenger at '.$trip->pickup_location.' needs a '.$trip->transport_type,
            'data' => [
                'trip_id' => $trip->id,
                'fare' => $trip->fare,
                'pickup_location' => $trip->pickup_location,
            ],
            'is_read' => false,
        ]);

        RideEvent::query()->create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'passenger_id' => $trip->passenger_id,
            'event_type' => 'driver_notified',
            'event_time' => now(),
        ]);

        AttemptTimeoutJob::dispatch((int) $attempt->id)->delay(now()->addSeconds(30));

        $endMs = floor(microtime(true) * 1000);
        \Illuminate\Support\Facades\Log::info('MATCHING_DURATION_MS', ['trip_id' => $this->tripId, 'duration_ms' => $endMs - $startMs]);
    }

    private function candidateDrivers(Trip $trip)
    {
        $rejectedDriverIds = DB::table('trip_rejections')
            ->where('trip_id', $trip->id)
            ->pluck('driver_id')
            ->all();

        $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(dl.latitude)) * cos(radians(dl.longitude) - radians(?)) + sin(radians(?)) * sin(radians(dl.latitude))))';

        $query = DB::table('drivers as d')
            ->join('driver_locations as dl', 'dl.driver_id', '=', 'd.user_id')
            ->selectRaw('d.id, d.user_id, d.rating, d.total_rides, dl.latitude, dl.longitude, dl.speed_kmh, '.$distanceSql.' as distance_km', [
                $trip->pickup_lat,
                $trip->pickup_lng,
                $trip->pickup_lat,
            ])
            ->where('d.status', 'approved')
            ->whereIn('d.availability_status', ['online', 'available'])
            ->where('d.is_available', true)
            ->whereNull('d.current_trip_id')
            ->whereNull('d.deleted_at')
            ->where('dl.is_online', true)
            ->where('dl.last_activity_at', '>=', now()->subSeconds(60))
            ->when(! empty($rejectedDriverIds), fn ($query) => $query->whereNotIn('d.id', $rejectedDriverIds))
            ->havingRaw($distanceSql.' <= ?', [
                $trip->pickup_lat,
                $trip->pickup_lng,
                $trip->pickup_lat,
                5,
            ])
            ->orderBy('distance_km')
            ->limit(10);

        \Illuminate\Support\Facades\Log::info('DRIVER_POOL_SIZE_BEFORE', ['trip_id' => $trip->id, 'count' => DB::table('drivers')->where('status', 'approved')->where('is_available', true)->whereNull('current_trip_id')->count()]);

        return $query->get();
    }

    private function rankDrivers(Trip $trip, $candidates): array
    {
        $behaviors = DriverBehavior::query()
            ->whereIn('driver_id', $candidates->pluck('id'))
            ->orderByDesc('reviewed_at')
            ->get()
            ->unique('driver_id')
            ->keyBy('driver_id');

        $candidatePayload = $candidates->map(function ($driver) use ($behaviors): array {
            $behavior = $behaviors->get($driver->id);

            return [
                'driver_id' => (int) $driver->id,
                'distance_km' => round((float) $driver->distance_km, 4),
                'rating' => (float) $driver->rating,
                'total_rides' => (int) $driver->total_rides,
                'acceptance_rate' => $behavior ? (float) $behavior->acceptance_rate : 1.0,
                'cancellation_rate' => $behavior ? (float) $behavior->cancellation_rate : 0.0,
            ];
        })->values()->all();

        $requestPayload = [
            'trip_id' => $trip->id,
            'transport_type' => $trip->transport_type ?? 'car',
            'pickup_lat' => (float) $trip->pickup_lat,
            'pickup_lng' => (float) $trip->pickup_lng,
            'candidates' => $candidatePayload,
        ];

        $fallbackTriggered = false;
        try {
            $mlResult = app(TfliteMatchingService::class)->rankDrivers(
                tripId: (int) $trip->id,
                transportType: $trip->transport_type ?? 'car',
                pickupLat: (float) $trip->pickup_lat,
                pickupLng: (float) $trip->pickup_lng,
                candidates: $candidatePayload,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ML_MATCHING_FAILED', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage()
            ]);
            $fallbackTriggered = true;
            $mlResult = null;
        }

        if (!$mlResult) {
            $fallbackRanking = collect($candidatePayload)
                ->sortBy('distance_km')
                ->values()
                ->map(function ($driver, $index) {
                    return [
                        'driver_id' => $driver['driver_id'],
                        'score' => 100 - ($index * 5) - ($driver['distance_km'] * 2),
                        'score_breakdown' => ['distance' => $driver['distance_km']]
                    ];
                })
                ->all();

            $mlResult = [
                'ranked_drivers' => $fallbackRanking,
                'model_version' => 'distance_fallback',
                'latency_ms' => 0,
            ];
            \Illuminate\Support\Facades\Log::info('FALLBACK_TRIGGERED', ['trip_id' => $trip->id]);
        }

        \Illuminate\Support\Facades\Log::info('ML_RESPONSE', ['trip_id' => $trip->id, 'ml_result' => $mlResult]);

        $latencyMs = (int) ($mlResult['latency_ms'] ?? 0);
        $modelVersion = (string) ($mlResult['model_version'] ?? 'unknown');

        MlPrediction::query()->create([
            'trip_id' => $trip->id,
            'model_name' => 'tflite_driver_ranker',
            'model_version' => $modelVersion,
            'endpoint' => rtrim((string) config('services.tflite.endpoint'), '/').'/rank-drivers',
            'input_payload' => $requestPayload,
            'output_payload' => $mlResult,
            'latency_ms' => $latencyMs,
            'created_at' => now(),
        ]);

        AiPredictionLog::query()->create([
            'prediction_type' => 'driver_ranking',
            'trip_id' => $trip->id,
            'request_payload' => $requestPayload,
            'response_payload' => $mlResult,
            'response_time_ms' => min($latencyMs, 65535),
            'success' => $modelVersion !== 'distance_fallback',
            'requested_at' => now(),
        ]);

        DB::table('matching_sessions')
            ->where('matching_session_id', $trip->matching_session_id)
            ->update([
                'payload' => json_encode($mlResult),
                'updated_at' => now(),
            ]);

        $mlResult['ranked_drivers'] = collect($mlResult['ranked_drivers'] ?? [])
            ->map(fn ($driver) => [
                'driver_id' => (int) $driver['driver_id'],
                'score' => (float) ($driver['score'] ?? 0),
                'score_breakdown' => $driver['score_breakdown'] ?? null,
            ])
            ->sortByDesc('score')
            ->values()
            ->all();

        return $mlResult;
    }

    private function cancelNoDriverTrip(Trip $trip, MobilePushService $pushService): void
    {
        DB::transaction(function () use ($trip): void {
            Trip::query()->where('id', $trip->id)->update([
                'status' => 'cancelled',
                'assignment_status' => 'failed',
                'updated_at' => now(),
            ]);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => 'system',
                'old_status' => $trip->status,
                'new_status' => 'cancelled',
                'metadata' => ['reason' => 'no_driver_available'],
                'created_at' => now(),
            ]);

            RideEvent::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $trip->passenger_id,
                'event_type' => 'trip_cancelled',
                'metadata' => ['reason' => 'no_driver_available'],
                'event_time' => now(),
            ]);
        });

        UserNotification::query()->create([
            'user_id' => $trip->passenger_id,
            'type' => 'trip.cancelled',
            'title' => 'No driver available',
            'message' => 'No nearby driver is available for this trip.',
            'data' => ['trip_id' => $trip->id],
            'is_read' => false,
        ]);

        $pushService->sendToMobileUser((int) $trip->passenger_id, 'No driver available', 'No nearby driver is available for this trip.', [
            'type' => 'trip_cancelled',
            'trip_id' => $trip->id,
        ]);
    }
}
