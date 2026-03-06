<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\FraudFlag;
use App\Models\Ride;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    // -----------------------------------------------------------------------
    // Thresholds
    // -----------------------------------------------------------------------
    const DUPLICATE_RIDE_INTERVAL_MINUTES = 30;
    const SELF_BOOKING_THRESHOLD          = 3;   // bookings per 7 days
    const FARE_SPIKE_MULTIPLIER           = 3.0; // >3x average
    const MIDNIGHT_HOUR_START             = 0;
    const MIDNIGHT_HOUR_END               = 4;
    const ACCOUNTANT_RATE_LIMIT_PER_HOUR  = 50;

    // -----------------------------------------------------------------------
    // Detection methods
    // -----------------------------------------------------------------------

    /**
     * Flag duplicate rides: same driver, identical origin+destination, within the interval.
     */
    public function detectDuplicateRides(int $driverId): bool
    {
        $latest = Ride::query()
            ->where('driver_id', $driverId)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_RIDE_INTERVAL_MINUTES))
            ->orderByDesc('created_at')
            ->first();

        if (! $latest) {
            return false;
        }

        $isDuplicate = Ride::query()
            ->where('driver_id', $driverId)
            ->where('id', '!=', $latest->id)
            ->where('origin_address', $latest->origin_address)
            ->where('destination_address', $latest->destination_address)
            ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_RIDE_INTERVAL_MINUTES))
            ->exists();

        if ($isDuplicate) {
            $this->flag('driver', $driverId, 'Duplicate rides with identical route within 30 minutes', 'medium', [
                'latest_ride_id' => $latest->id,
                'detected_at'    => now()->toIso8601String(),
            ]);
        }

        return $isDuplicate;
    }

    /**
     * Flag self-booking patterns: same passenger repeatedly books same driver.
     */
    public function detectSelfBooking(int $driverId, int $passengerId): bool
    {
        $count = Booking::query()
            ->where('user_id', $passengerId)
            ->whereHas('ride', fn ($q) => $q->where('driver_id', $driverId))
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        if ($count >= self::SELF_BOOKING_THRESHOLD) {
            $meta = ['passenger_id' => $passengerId, 'driver_id' => $driverId, 'booking_count' => $count];
            $this->flag('driver',    $driverId,    "Repeated self-booking with passenger #{$passengerId} ({$count}x in 7 days)", 'medium', $meta);
            $this->flag('passenger', $passengerId, "Repeated self-booking with driver #{$driverId} ({$count}x in 7 days)", 'low',    $meta);

            return true;
        }

        return false;
    }

    /**
     * Flag abnormal fare spikes vs. 30-day driver average.
     */
    public function detectAbnormalFare(int $driverId, float $fareAmount): bool
    {
        $avg = (float) Booking::query()
            ->whereHas('ride', fn ($q) => $q->where('driver_id', $driverId))
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('total_price');

        if ($avg <= 0) {
            return false;
        }

        if ($fareAmount > $avg * self::FARE_SPIKE_MULTIPLIER) {
            $this->flag('driver', $driverId, sprintf(
                'Abnormal fare spike: RWF %.2f vs avg RWF %.2f (%.1fx above average)',
                $fareAmount,
                $avg,
                $fareAmount / $avg
            ), 'high', ['fare_amount' => $fareAmount, 'average_fare' => $avg]);

            return true;
        }

        return false;
    }

    /**
     * Flag suspicious activity during midnight hours (00:00–04:00).
     */
    public function detectSuspiciousMidnightActivity(string $entityType, int $entityId, string $activity): bool
    {
        $hour = (int) now()->format('H');

        if ($hour >= self::MIDNIGHT_HOUR_START && $hour < self::MIDNIGHT_HOUR_END) {
            $this->flag($entityType, $entityId, "Suspicious midnight activity: {$activity}", 'medium', [
                'hour'        => $hour,
                'activity'    => $activity,
                'detected_at' => now()->toIso8601String(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if accountant has exceeded the per-hour payout rate limit.
     * Returns true if rate-limited (i.e. should block).
     */
    public function rateLimitPayouts(int $accountantId): bool
    {
        $count = DriverPayout::query()
            ->where('processed_by', $accountantId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($count >= self::ACCOUNTANT_RATE_LIMIT_PER_HOUR) {
            Log::warning('Accountant payout rate limit exceeded', [
                'accountant_id'          => $accountantId,
                'payouts_in_last_hour'   => $count,
                'limit'                  => self::ACCOUNTANT_RATE_LIMIT_PER_HOUR,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Returns true if driver has at least one unresolved high-severity fraud flag.
     */
    public function isDriverFlagged(int $driverId): bool
    {
        return FraudFlag::query()
            ->where('entity_type', 'driver')
            ->where('entity_id', $driverId)
            ->where('severity', 'high')
            ->where('resolved', false)
            ->exists();
    }

    /**
     * Combined pre-payout eligibility check.
     * Returns ['eligible' => bool, 'blockers' => string[]].
     */
    public function checkPayoutEligibility(int $driverId, int $accountantId): array
    {
        $blockers = [];

        if ($this->isDriverFlagged($driverId)) {
            $blockers[] = "Driver #{$driverId} has active high-severity fraud flags. Payout blocked.";
        }

        if ($this->rateLimitPayouts($accountantId)) {
            $blockers[] = 'Accountant payout rate limit exceeded. Try again in an hour.';
        }

        return ['eligible' => empty($blockers), 'blockers' => $blockers];
    }

    // -----------------------------------------------------------------------
    // Flag management
    // -----------------------------------------------------------------------

    public function flag(
        string $entityType,
        int $entityId,
        string $reason,
        string $severity = 'medium',
        array $metadata = []
    ): FraudFlag {
        Log::warning('Fraud flag created', compact('entityType', 'entityId', 'reason', 'severity'));

        return FraudFlag::create([
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'reason'      => $reason,
            'severity'    => $severity,
            'resolved'    => false,
            'metadata'    => $metadata ?: null,
        ]);
    }

    public function resolve(FraudFlag $flag, int $resolvedBy): FraudFlag
    {
        $flag->resolved    = true;
        $flag->resolved_by = $resolvedBy;
        $flag->resolved_at = now();
        $flag->save();

        Log::info('Fraud flag resolved', ['flag_id' => $flag->id, 'resolved_by' => $resolvedBy]);

        return $flag;
    }
}
