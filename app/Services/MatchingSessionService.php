<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\MatchingSession;
use Illuminate\Support\Str;

class MatchingSessionService
{
    public function createSession(array $payload): MatchingSession
    {
        /**
         * payload requires: passenger_id, transport_type, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng
         */
        $data = [
            'matching_session_id' => Str::uuid()->toString(),
            'passenger_id' => $payload['passenger_id'],
            'transport_type' => $payload['transport_type'],
            'pickup_lat' => $payload['pickup_lat'],
            'pickup_lng' => $payload['pickup_lng'],
            'dropoff_lat' => $payload['dropoff_lat'],
            'dropoff_lng' => $payload['dropoff_lng'],
            'payload' => $payload,
            'status' => 'pending',
            'expires_at' => now()->addSeconds(40),
        ];

        return MatchingSession::query()->create($data);
    }

    public function validateSession(int $passengerId, string $matchingSessionId): MatchingSession
    {
        $session = MatchingSession::query()
            ->where('matching_session_id', $matchingSessionId)
            ->where('passenger_id', $passengerId)
            ->first();

        if (! $session) {
            throw DomainException::make('Matching session not found', 'MATCHING_SESSION_NOT_FOUND');
        }

        if ($session->isExpired()) {
            $session->status = 'expired';
            $session->save();
            throw DomainException::make('Matching session expired', 'MATCHING_SESSION_EXPIRED');
        }

        if ($session->status !== 'pending') {
            throw DomainException::make('Matching session is no longer active', 'MATCHING_SESSION_INVALID');
        }

        return $session;
    }

    public function confirmSelectedDriver(MatchingSession $session, int $driverId): MatchingSession
    {
        if ($session->selected_driver_id && $session->selected_driver_id !== $driverId) {
            throw DomainException::make('Matching session does not match selected driver', 'MATCHING_SESSION_DRIVER_MISMATCH');
        }

        if ($session->isExpired()) {
            $session->status = 'expired';
            $session->save();
            throw DomainException::make('Matching session expired', 'MATCHING_SESSION_EXPIRED');
        }

        $session->selected_driver_id = $driverId;
        $session->save();

        return $session;
    }
}
