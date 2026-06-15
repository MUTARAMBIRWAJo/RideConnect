<?php

namespace App\Domain\Trip;

use App\Exceptions\DomainException;
use App\Models\Trip;

class TripStateMachine
{
    public const REQUESTED = 'REQUESTED';
    public const MATCHING = 'MATCHING';
    public const DRIVER_FOUND = 'DRIVER_FOUND';
    public const ASSIGNED = 'ASSIGNED';
    public const ACCEPTED = 'ACCEPTED';
    public const ARRIVED = 'ARRIVED';
    public const STARTED = 'STARTED';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';
    public const FAILED = 'FAILED';

    /**
     * Compatibility mapping from legacy statuses to canonical states.
     */
    private const LEGACY_MAP = [
        'PENDING' => self::REQUESTED,
        'REQUESTED' => self::REQUESTED,
        'ASSIGNING' => self::MATCHING,
        'MATCHING' => self::MATCHING,
        'DRIVER_FOUND' => self::DRIVER_FOUND,
        'ASSIGNED' => self::ASSIGNED,
        'ACCEPTED' => self::ACCEPTED,
        'ARRIVED' => self::ARRIVED,
        'STARTED' => self::STARTED,
        'COMPLETED' => self::COMPLETED,
        'CANCELLED' => self::CANCELLED,
        'FAILED' => self::FAILED,
        'IN_PROGRESS' => self::STARTED,
        'IN_TRANSIT' => self::STARTED,
        'MATCHED' => self::DRIVER_FOUND,
        'ENROUTE_TO_PICKUP' => self::ACCEPTED,
        'ARRIVED_AT_PICKUP' => self::ARRIVED,
        'PASSENGER_WAITING' => self::ARRIVED,
        'DRIVER_ASSIGNED' => self::ASSIGNED,
    ];

    private const ALLOWED = [
        self::REQUESTED => [self::MATCHING, self::DRIVER_FOUND, self::ASSIGNED, self::ACCEPTED, self::CANCELLED, self::FAILED],
        self::MATCHING => [self::DRIVER_FOUND, self::ASSIGNED, self::ACCEPTED, self::CANCELLED, self::FAILED],
        self::DRIVER_FOUND => [self::ASSIGNED, self::ACCEPTED, self::CANCELLED, self::FAILED],
        self::ASSIGNED => [self::ACCEPTED, self::CANCELLED, self::FAILED],
        self::ACCEPTED => [self::ARRIVED, self::STARTED, self::CANCELLED, self::FAILED],
        self::ARRIVED => [self::STARTED, self::CANCELLED, self::FAILED],
        self::STARTED => [self::COMPLETED, self::FAILED],
        self::COMPLETED => [],
        self::CANCELLED => [],
        self::FAILED => [],
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

