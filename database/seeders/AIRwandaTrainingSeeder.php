<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIRwandaTrainingSeeder extends Seeder
{
    use WithoutModelEvents;

    private const TARGET_ROWS = 1000;

    /** @var array<string, array<int, string>> */
    private array $tableColumnsCache = [];

    /**
     * Public references used for Rwanda geospatial anchors:
     * - https://en.wikipedia.org/wiki/Kigali
     * - https://en.wikipedia.org/wiki/Kigali_International_Airport
     * - https://www.openstreetmap.org/search?query=Kigali%20Convention%20Centre
     */
    private const HOTSPOTS = [
        ['name' => 'Kigali CBD', 'district' => 'Nyarugenge', 'lat' => -1.9536, 'lng' => 30.0606, 'weight' => 16],
        ['name' => 'Kigali International Airport', 'district' => 'Kicukiro', 'lat' => -1.9686, 'lng' => 30.1394, 'weight' => 12],
        ['name' => 'Kigali Convention Centre', 'district' => 'Gasabo', 'lat' => -1.9554, 'lng' => 30.0937, 'weight' => 14],
        ['name' => 'Nyabugogo Bus Terminal', 'district' => 'Nyarugenge', 'lat' => -1.9456, 'lng' => 30.0444, 'weight' => 15],
        ['name' => 'Kimironko Market', 'district' => 'Gasabo', 'lat' => -1.9411, 'lng' => 30.1098, 'weight' => 11],
        ['name' => 'Remera', 'district' => 'Gasabo', 'lat' => -1.9578, 'lng' => 30.1063, 'weight' => 10],
        ['name' => 'Nyamirambo', 'district' => 'Nyarugenge', 'lat' => -1.9750, 'lng' => 30.0400, 'weight' => 9],
        ['name' => 'Kacyiru', 'district' => 'Gasabo', 'lat' => -1.9400, 'lng' => 30.0700, 'weight' => 8],
        ['name' => 'Huye Town', 'district' => 'Huye', 'lat' => -2.5969, 'lng' => 29.5944, 'weight' => 3],
        ['name' => 'Musanze Town', 'district' => 'Musanze', 'lat' => -1.4995, 'lng' => 29.6333, 'weight' => 2],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('mobile_users')) {
            return;
        }

        $passengerIds = DB::table('mobile_users')
            ->where('role', 'PASSENGER')
            ->pluck('id')
            ->all();

        $driverIds = DB::table('mobile_users')
            ->where('role', 'DRIVER')
            ->pluck('id')
            ->all();

        $driverProfileIds = Schema::hasTable('drivers')
            ? DB::table('drivers')->pluck('id')->all()
            : [];

        if (empty($passengerIds) || empty($driverIds)) {
            return;
        }

        $tripIds = Schema::hasTable('trips')
            ? DB::table('trips')->pluck('id')->all()
            : [];

        $rideRequests = [];
        $demandLogs = [];
        $trafficEvents = [];
        $rideEvents = [];
        $rideCancellations = [];
        $predictionLogs = [];
        $passengerLocations = [];
        $driverLocations = [];

        for ($i = 0; $i < self::TARGET_ROWS; $i++) {
            $requestTime = now()->subMinutes(random_int(15, 60 * 24 * 30));
            $pickup = $this->pickHotspot();
            $dropoff = $this->pickDifferentHotspot($pickup['name']);
            $zoneKey = strtoupper($pickup['district']).':'.$this->slugify($pickup['name']);
            $status = $this->pickStatus();

            $passengerId = $passengerIds[array_rand($passengerIds)];
            $driverId = $status === 'pending' ? null : $driverIds[array_rand($driverIds)];
            $matchedDriverId = $status === 'pending'
                ? null
                : (! empty($driverProfileIds)
                    ? $driverProfileIds[array_rand($driverProfileIds)]
                    : null);
            $tripId = empty($tripIds) ? null : $tripIds[array_rand($tripIds)];

            $pickupLat = $this->jitter($pickup['lat'], 0.0065);
            $pickupLng = $this->jitter($pickup['lng'], 0.0065);
            $dropoffLat = $this->jitter($dropoff['lat'], 0.0065);
            $dropoffLng = $this->jitter($dropoff['lng'], 0.0065);

            $isPeak = $this->isPeakHour((int) $requestTime->format('G'));
            $isRainy = $this->isRainySeason((int) $requestTime->format('n'));
            $trafficLevel = $this->trafficLevel($isPeak, $isRainy);

            $rideRequests[] = [
                'trip_id' => $tripId,
                'passenger_id' => $passengerId,
                'driver_id' => $matchedDriverId,
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
                'dropoff_lat' => $dropoffLat,
                'dropoff_lng' => $dropoffLng,
                'request_time' => $requestTime,
                'status' => $status,
                'created_at' => $requestTime,
                'updated_at' => $requestTime,
            ];

            $demandLogs[] = [
                'trip_id' => $tripId,
                'zone_key' => $zoneKey,
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
                'request_time' => $requestTime,
                'created_at' => $requestTime,
                'updated_at' => $requestTime,
            ];

            $trafficEvents[] = [
                'latitude' => $pickupLat,
                'longitude' => $pickupLng,
                'traffic_level' => $trafficLevel,
                'event_type' => $isPeak ? 'peak_hour_flow' : 'normal_flow',
                'weather_factor' => $isRainy ? 1.25 : 0.95,
                'event_time' => $requestTime,
                'created_at' => $requestTime,
                'updated_at' => $requestTime,
            ];

            $rideEvents[] = [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'passenger_id' => $passengerId,
                'event_type' => 'ride_requested',
                'metadata' => json_encode(['zone' => $zoneKey, 'source' => 'rwanda_seed_v1']),
                'event_time' => $requestTime,
                'created_at' => $requestTime,
                'updated_at' => $requestTime,
            ];

            if ($driverId !== null) {
                $driverAssignedAt = (clone $requestTime)->addMinutes(random_int(1, 7));
                $rideEvents[] = [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'passenger_id' => $passengerId,
                    'event_type' => 'driver_assigned',
                    'metadata' => json_encode(['eta_minutes' => random_int(3, 15)]),
                    'event_time' => $driverAssignedAt,
                    'created_at' => $driverAssignedAt,
                    'updated_at' => $driverAssignedAt,
                ];
            }

            if ($status === 'started' || $status === 'completed') {
                $startedAt = (clone $requestTime)->addMinutes(random_int(5, 18));
                $rideEvents[] = [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'passenger_id' => $passengerId,
                    'event_type' => 'ride_started',
                    'metadata' => json_encode(['traffic_level' => $trafficLevel]),
                    'event_time' => $startedAt,
                    'created_at' => $startedAt,
                    'updated_at' => $startedAt,
                ];
            }

            if ($status === 'completed') {
                $completedAt = (clone $requestTime)->addMinutes(random_int(18, 72));
                $rideEvents[] = [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'passenger_id' => $passengerId,
                    'event_type' => 'ride_completed',
                    'metadata' => json_encode([
                        'distance_km' => round(random_int(15, 240) / 10, 1),
                        'duration_minutes' => random_int(12, 75),
                    ]),
                    'event_time' => $completedAt,
                    'created_at' => $completedAt,
                    'updated_at' => $completedAt,
                ];
            }

            if ($status === 'cancelled') {
                $cancelledAt = (clone $requestTime)->addMinutes(random_int(2, 10));
                $rideCancellations[] = [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'passenger_id' => $passengerId,
                    'cancelled_by_user_id' => random_int(0, 1) === 0 ? $passengerId : $driverId,
                    'reason' => random_int(0, 1) === 0 ? 'driver_delayed' : 'passenger_changed_plan',
                    'cancelled_at' => $cancelledAt,
                    'created_at' => $cancelledAt,
                    'updated_at' => $cancelledAt,
                ];

                $rideEvents[] = [
                    'trip_id' => $tripId,
                    'driver_id' => $driverId,
                    'passenger_id' => $passengerId,
                    'event_type' => 'ride_cancelled',
                    'metadata' => json_encode(['reason' => 'schedule_conflict']),
                    'event_time' => $cancelledAt,
                    'created_at' => $cancelledAt,
                    'updated_at' => $cancelledAt,
                ];
            }

            $predictionLogs[] = [
                'prediction_type' => ['demand', 'eta', 'matching', 'pricing'][array_rand([0, 1, 2, 3])],
                'trip_id' => $tripId,
                'request_payload' => json_encode([
                    'pickup_lat' => $pickupLat,
                    'pickup_lng' => $pickupLng,
                    'request_hour' => (int) $requestTime->format('G'),
                    'zone_key' => $zoneKey,
                ]),
                'response_payload' => json_encode([
                    'confidence' => round(random_int(72, 97) / 100, 2),
                    'value' => round(random_int(12, 180) / 10, 1),
                ]),
                'response_time_ms' => random_int(35, 210),
                'success' => random_int(1, 100) > 4,
                'requested_at' => $requestTime,
                'created_at' => $requestTime,
                'updated_at' => $requestTime,
            ];

            $passengerLocations[$passengerId] = [
                'passenger_id' => $passengerId,
                'latitude' => $pickupLat,
                'longitude' => $pickupLng,
                'updated_at' => $requestTime,
            ];

            if ($matchedDriverId !== null) {
                $driverLocations[$driverId] = [
                    'driver_id' => $matchedDriverId,
                    'latitude' => $this->jitter($pickupLat, 0.0035),
                    'longitude' => $this->jitter($pickupLng, 0.0035),
                    'updated_at' => $requestTime,
                ];
            }
        }

        $this->chunkInsert('ride_requests', $rideRequests);
        $this->chunkInsert('demand_logs', $demandLogs);
        $this->chunkInsert('traffic_events', $trafficEvents);
        $this->chunkInsert('ride_events', $rideEvents);
        $this->chunkInsert('ride_cancellations', $rideCancellations);
        $this->chunkInsert('ai_prediction_logs', $predictionLogs);

        if (Schema::hasTable('passenger_locations')) {
            foreach ($passengerLocations as $location) {
                DB::table('passenger_locations')->updateOrInsert(
                    ['passenger_id' => $location['passenger_id']],
                    [
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'updated_at' => $location['updated_at'],
                    ]
                );
            }
        }

        if (Schema::hasTable('driver_locations')) {
            foreach ($driverLocations as $location) {
                DB::table('driver_locations')->updateOrInsert(
                    ['driver_id' => $location['driver_id']],
                    [
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude'],
                        'updated_at' => $location['updated_at'],
                    ]
                );
            }
        }

        if (Schema::hasTable('ai_model_metrics')) {
            DB::table('ai_model_metrics')->insert([
                ['model_name' => 'demand_model', 'metric_name' => 'mae', 'metric_value' => 0.184, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'demand_model', 'metric_name' => 'r2', 'metric_value' => 0.841, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'eta_model', 'metric_name' => 'mae_minutes', 'metric_value' => 2.7, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'eta_model', 'metric_name' => 'mape', 'metric_value' => 0.121, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'matching_model', 'metric_name' => 'precision', 'metric_value' => 0.891, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'matching_model', 'metric_name' => 'recall', 'metric_value' => 0.864, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'pricing_model', 'metric_name' => 'rmse', 'metric_value' => 0.227, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['model_name' => 'pricing_model', 'metric_name' => 'mape', 'metric_value' => 0.109, 'evaluated_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    private function pickHotspot(): array
    {
        $totalWeight = array_sum(array_column(self::HOTSPOTS, 'weight'));
        $roll = random_int(1, $totalWeight);
        $cursor = 0;

        foreach (self::HOTSPOTS as $hotspot) {
            $cursor += $hotspot['weight'];
            if ($roll <= $cursor) {
                return $hotspot;
            }
        }

        return self::HOTSPOTS[0];
    }

    private function pickDifferentHotspot(string $pickupName): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $this->pickHotspot();
            if ($candidate['name'] !== $pickupName) {
                return $candidate;
            }
        }

        return self::HOTSPOTS[1];
    }

    private function pickStatus(): string
    {
        $roll = random_int(1, 100);

        if ($roll <= 62) {
            return 'completed';
        }

        if ($roll <= 76) {
            return 'accepted';
        }

        if ($roll <= 88) {
            return 'started';
        }

        if ($roll <= 96) {
            return 'cancelled';
        }

        return 'pending';
    }

    private function trafficLevel(bool $isPeak, bool $isRainy): int
    {
        $level = 1;

        if ($isPeak) {
            $level += 1;
        }

        if ($isRainy) {
            $level += 1;
        }

        if (random_int(1, 100) > 82) {
            $level += 1;
        }

        return (int) min(5, $level);
    }

    private function isPeakHour(int $hour): bool
    {
        return in_array($hour, [7, 8, 9, 17, 18, 19], true);
    }

    private function isRainySeason(int $month): bool
    {
        return in_array($month, [2, 3, 4, 5, 9, 10, 11], true);
    }

    private function jitter(float $base, float $maxOffset): float
    {
        $offset = (random_int(-1000, 1000) / 1000) * $maxOffset;

        return round($base + $offset, 7);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);

        return trim((string) $slug, '_');
    }

    private function chunkInsert(string $table, array $rows, int $chunkSize = 250): void
    {
        if (empty($rows) || ! Schema::hasTable($table)) {
            return;
        }

        $this->syncPgsqlSequence($table);
        $rows = $this->normalizeRowsForTable($table, $rows);

        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function normalizeRowsForTable(string $table, array $rows): array
    {
        $columns = array_flip($this->getTableColumns($table));

        return array_values(array_filter(array_map(function (array $row) use ($table, $columns): array {
            if ($table === 'ride_requests') {
                if (! isset($columns['request_time']) && isset($columns['requested_at']) && array_key_exists('request_time', $row)) {
                    $row['requested_at'] = $row['request_time'];
                    unset($row['request_time']);
                }

                if (! isset($columns['driver_id']) && isset($columns['matched_driver_id']) && array_key_exists('driver_id', $row)) {
                    $row['matched_driver_id'] = $row['driver_id'];
                    unset($row['driver_id']);
                }
            }

            if ($table === 'driver_locations' && ! isset($columns['updated_at']) && isset($columns['recorded_at']) && array_key_exists('updated_at', $row)) {
                $row['recorded_at'] = $row['updated_at'];
            }

            return array_intersect_key($row, $columns);
        }, $rows), static fn (array $row): bool => ! empty($row)));
    }

    /**
     * Keep serial sequences aligned when re-running seeders against PostgreSQL.
     */
    private function syncPgsqlSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $sequence = DB::selectOne("SELECT pg_get_serial_sequence('".$table."', 'id') AS seq");
        $sequenceName = $sequence?->seq ?? null;

        if (! is_string($sequenceName) || $sequenceName === '') {
            return;
        }

        DB::statement("SELECT setval('".$sequenceName."', COALESCE((SELECT MAX(id) FROM \"".$table.'"), 0) + 1, false)');
    }

    private function getTableColumns(string $table): array
    {
        if (! array_key_exists($table, $this->tableColumnsCache)) {
            $this->tableColumnsCache[$table] = Schema::getColumnListing($table);
        }

        return $this->tableColumnsCache[$table];
    }
}
