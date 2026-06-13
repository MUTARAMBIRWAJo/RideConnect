<?php

namespace App\Domain\Trip;

use App\Exceptions\DomainException;
use App\Models\Trip;

class TripStateMachine
{
    public const REQUESTED = 'REQUESTED';

    public const MATCHED = 'MATCHED';

    public const ACCEPTED = 'ACCEPTED';

    public const STARTED = 'STARTED';

    public const COMPLETED = 'COMPLETED';

    public const CANCELLED = 'CANCELLED';

    /**
     * Compatibility mapping from legacy statuses to canonical states.
     */
    private const LEGACY_MAP = [
        'pending' => self::REQUESTED,
        'requested' => self::REQUESTED,
        'assigning' => self::REQUESTED,
        'PENDING' => self::REQUESTED,
    ];

    private const ALLOWED = [
        self::REQUESTED => [self::MATCHED, self::CANCELLED, self::ACCEPTED],
        self::MATCHED => [self::ACCEPTED],
        self::ACCEPTED => [self::STARTED, self::CANCELLED],
        self::STARTED => [self::COMPLETED],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    public static function normalize(string $status): string
    {
        $status = strtoupper(trim($status));

        return self::LEGACY_MAP[$status] ?? $status;
    }

    public static function canTransition(string $from, string $to): bool
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw DomainException::make(
                sprintf('Invalid trip state transition: %s -> %s', self::normalize($from), self::normalize($to)),
                'INVALID_TRIP_STATE_TRANSITION'
            );
        }
    }

    public static function assertTransitionForTrip(Trip $trip, string $to): void
    {
        self::assertTransition((string) $trip->status, $to);
    }
}
