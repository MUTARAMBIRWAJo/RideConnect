<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects identity orphans and inconsistent foreign-key references.
 */
class IdentityConsistencyService
{
    public function __construct(
        private readonly IdentityResolverService $identityResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateReport(): array
    {
        $checks = [
            'orphan_users_without_role' => $this->orphanUsersWithoutRole(),
            'orphan_users_with_invalid_mobile_user_id' => $this->orphanUsersWithInvalidMobileUserId(),
            'orphan_mobile_users_without_user' => $this->orphanMobileUsersWithoutUser(),
            'orphan_drivers_without_user' => $this->orphanDriversWithoutUser(),
            'orphan_drivers_with_invalid_user' => $this->orphanDriversWithInvalidUser(),
            'orphan_trips_passenger' => $this->orphanTripsPassenger(),
            'orphan_trips_driver' => $this->orphanTripsDriver(),
            'orphan_motorcycle_trips_passenger' => $this->orphanMotorcycleTripsPassenger(),
            'orphan_motorcycle_trips_driver' => $this->orphanMotorcycleTripsDriver(),
            'orphan_payments_user' => $this->orphanPaymentsUser(),
            'orphan_reviews_user' => $this->orphanReviewsUser(),
            'orphan_reviews_driver' => $this->orphanReviewsDriver(),
            'orphan_notifications_user' => $this->orphanNotificationsUser(),
            'orphan_matching_sessions_passenger' => $this->orphanMatchingSessionsPassenger(),
            'orphan_device_tokens_user' => $this->orphanDeviceTokensUser(),
            'dual_passenger_id_drift' => $this->dualPassengerIdDrift(),
        ];

        $totalIssues = collect($checks)->sum(fn (array $check) => (int) ($check['count'] ?? 0));

        return [
            'generated_at' => now()->toIso8601String(),
            'canonical_model' => [
                'identity_table' => 'users',
                'identity_column' => 'id',
                'driver_profile_table' => 'drivers',
                'driver_profile_fk' => 'user_id',
                'legacy_mobile_table' => 'mobile_users',
                'trips_passenger_normalized' => $this->identityResolver->tripsPassengerReferencesUsers(),
            ],
            'summary' => [
                'total_issues' => $totalIssues,
                'checks_run' => count($checks),
                'production_readiness_score' => $this->productionReadinessScore($totalIssues, $checks),
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $checks
     */
    private function productionReadinessScore(int $totalIssues, array $checks): int
    {
        $score = 100;

        $weights = [
            'orphan_trips_passenger' => 12,
            'orphan_trips_driver' => 10,
            'orphan_payments_user' => 10,
            'orphan_reviews_user' => 6,
            'orphan_reviews_driver' => 6,
            'dual_passenger_id_drift' => 15,
            'orphan_drivers_without_user' => 8,
            'orphan_users_with_invalid_mobile_user_id' => 5,
        ];

        foreach ($weights as $key => $weight) {
            $count = (int) ($checks[$key]['count'] ?? 0);
            if ($count > 0) {
                $score -= min($weight, $weight * min($count, 5));
            }
        }

        if (! $this->identityResolver->tripsPassengerReferencesUsers()) {
            $score -= 10;
        }

        return max(0, min(100, $score));
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanUsersWithoutRole(): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'role')) {
            return $this->emptyCheck('users.role column missing');
        }

        $ids = DB::table('users')->whereNull('role')->pluck('id')->all();

        return $this->result('Users without role', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanUsersWithInvalidMobileUserId(): array
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'mobile_user_id')) {
            return $this->emptyCheck('users.mobile_user_id column missing');
        }

        $ids = DB::table('users as u')
            ->leftJoin('mobile_users as m', 'm.id', '=', 'u.mobile_user_id')
            ->whereNotNull('u.mobile_user_id')
            ->whereNull('m.id')
            ->pluck('u.id')
            ->all();

        return $this->result('Users with mobile_user_id pointing to missing mobile_users row', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanMobileUsersWithoutUser(): array
    {
        if (! Schema::hasTable('mobile_users')) {
            return $this->emptyCheck('mobile_users table missing');
        }

        $ids = DB::table('mobile_users as m')
            ->leftJoin('users as u', 'u.mobile_user_id', '=', 'm.id')
            ->whereNull('u.id')
            ->pluck('m.id')
            ->all();

        return $this->result('Legacy mobile_users without linked users row', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanDriversWithoutUser(): array
    {
        if (! Schema::hasTable('drivers')) {
            return $this->emptyCheck('drivers table missing');
        }

        $ids = DB::table('drivers')->whereNull('user_id')->pluck('id')->all();

        return $this->result('Driver profiles without user_id', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanDriversWithInvalidUser(): array
    {
        if (! Schema::hasTable('drivers')) {
            return $this->emptyCheck('drivers table missing');
        }

        $ids = DB::table('drivers as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
            ->whereNotNull('d.user_id')
            ->whereNull('u.id')
            ->pluck('d.id')
            ->all();

        return $this->result('Driver profiles with user_id not in users', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanTripsPassenger(): array
    {
        if (! Schema::hasTable('trips') || ! Schema::hasColumn('trips', 'passenger_id')) {
            return $this->emptyCheck('trips.passenger_id missing');
        }

        if ($this->identityResolver->tripsPassengerReferencesUsers()) {
            $ids = DB::table('trips as t')
                ->leftJoin('users as u', 'u.id', '=', 't.passenger_id')
                ->whereNull('u.id')
                ->pluck('t.id')
                ->all();

            return $this->result('Trips with passenger_id not in users', $ids);
        }

        $ids = DB::table('trips as t')
            ->leftJoin('mobile_users as m', 'm.id', '=', 't.passenger_id')
            ->leftJoin('users as u', 'u.id', '=', 't.passenger_id')
            ->whereNull('m.id')
            ->whereNull('u.id')
            ->pluck('t.id')
            ->all();

        return $this->result('Trips with passenger_id not resolving to users or mobile_users', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanTripsDriver(): array
    {
        if (! Schema::hasTable('trips') || ! Schema::hasColumn('trips', 'driver_id')) {
            return $this->emptyCheck('trips.driver_id missing');
        }

        $ids = DB::table('trips as t')
            ->leftJoin('drivers as d', 'd.id', '=', 't.driver_id')
            ->whereNotNull('t.driver_id')
            ->whereNull('d.id')
            ->pluck('t.id')
            ->all();

        return $this->result('Trips with driver_id not in drivers', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanMotorcycleTripsPassenger(): array
    {
        if (! Schema::hasTable('motorcycle_trips')) {
            return $this->emptyCheck('motorcycle_trips table missing');
        }

        $ids = DB::table('motorcycle_trips as t')
            ->leftJoin('users as u', 'u.id', '=', 't.passenger_id')
            ->whereNull('u.id')
            ->pluck('t.id')
            ->all();

        return $this->result('Motorcycle trips with passenger_id not in users', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanMotorcycleTripsDriver(): array
    {
        if (! Schema::hasTable('motorcycle_trips')) {
            return $this->emptyCheck('motorcycle_trips table missing');
        }

        $ids = DB::table('motorcycle_trips as t')
            ->leftJoin('drivers as d', 'd.id', '=', 't.driver_id')
            ->whereNotNull('t.driver_id')
            ->whereNull('d.id')
            ->pluck('t.id')
            ->all();

        return $this->result('Motorcycle trips with driver_id not in drivers', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanPaymentsUser(): array
    {
        if (! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'user_id')) {
            return $this->emptyCheck('payments.user_id missing');
        }

        $ids = DB::table('payments as p')
            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
            ->whereNull('u.id')
            ->pluck('p.id')
            ->all();

        return $this->result('Payments with user_id not in users', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanReviewsUser(): array
    {
        if (! Schema::hasTable('reviews') || ! Schema::hasColumn('reviews', 'user_id')) {
            return $this->emptyCheck('reviews.user_id missing');
        }

        $ids = DB::table('reviews as r')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->whereNull('u.id')
            ->pluck('r.id')
            ->all();

        return $this->result('Reviews with user_id not in users', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanReviewsDriver(): array
    {
        if (! Schema::hasTable('reviews') || ! Schema::hasColumn('reviews', 'driver_id')) {
            return $this->emptyCheck('reviews.driver_id missing');
        }

        $ids = DB::table('reviews as r')
            ->leftJoin('drivers as d', 'd.id', '=', 'r.driver_id')
            ->whereNull('d.id')
            ->pluck('r.id')
            ->all();

        return $this->result('Reviews with driver_id not in drivers', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanNotificationsUser(): array
    {
        if (! Schema::hasTable('user_notifications')) {
            return $this->emptyCheck('user_notifications table missing');
        }

        $ids = DB::table('user_notifications as n')
            ->leftJoin('users as u', 'u.id', '=', 'n.user_id')
            ->whereNull('u.id')
            ->pluck('n.id')
            ->all();

        return $this->result('Notifications with user_id not in users', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanMatchingSessionsPassenger(): array
    {
        if (! Schema::hasTable('matching_sessions')) {
            return $this->emptyCheck('matching_sessions table missing');
        }

        if ($this->identityResolver->tripsPassengerReferencesUsers()) {
            $ids = DB::table('matching_sessions as ms')
                ->leftJoin('users as u', 'u.id', '=', 'ms.passenger_id')
                ->whereNull('u.id')
                ->pluck('ms.id')
                ->all();

            return $this->result('Matching sessions with passenger_id not in users', $ids);
        }

        $ids = DB::table('matching_sessions as ms')
            ->leftJoin('mobile_users as m', 'm.id', '=', 'ms.passenger_id')
            ->leftJoin('users as u', 'u.id', '=', 'ms.passenger_id')
            ->whereNull('m.id')
            ->whereNull('u.id')
            ->pluck('ms.id')
            ->all();

        return $this->result('Matching sessions with passenger_id not resolving', $ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function orphanDeviceTokensUser(): array
    {
        if (! Schema::hasTable('mobile_device_tokens')) {
            return $this->emptyCheck('mobile_device_tokens table missing');
        }

        $ids = DB::table('mobile_device_tokens as t')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('u.id')
            ->pluck('t.id')
            ->all();

        return $this->result('FCM device tokens with user_id not in users', $ids);
    }

    /**
     * Trips still storing mobile_users.id while a linked users.id exists.
     *
     * @return array<string, mixed>
     */
    private function dualPassengerIdDrift(): array
    {
        if (! Schema::hasTable('trips') || ! Schema::hasColumn('users', 'mobile_user_id')) {
            return $this->emptyCheck('Cannot evaluate passenger id drift');
        }

        $ids = DB::table('trips as t')
            ->join('users as u', 'u.mobile_user_id', '=', 't.passenger_id')
            ->whereColumn('t.passenger_id', '!=', 'u.id')
            ->pluck('t.id')
            ->all();

        return $this->result('Trips storing legacy mobile_users.id instead of users.id', $ids);
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<string, mixed>
     */
    private function result(string $label, array $ids): array
    {
        return [
            'label' => $label,
            'count' => count($ids),
            'sample_ids' => array_slice($ids, 0, 25),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCheck(string $reason): array
    {
        return [
            'label' => $reason,
            'count' => 0,
            'sample_ids' => [],
            'skipped' => true,
        ];
    }
}
