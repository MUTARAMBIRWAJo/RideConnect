<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchingSession;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Trip;
use App\Models\TripStatusEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripSyncController extends Controller
{
    public function status(Request $request, Trip $trip): JsonResponse
    {
        if (! $this->canAccessTrip($request, $trip)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'trip_status' => $trip->status,
                'assignment_status' => $trip->assignment_status,
                'payment_status' => $trip->payment_status,
                'driver_id' => $trip->driver_id,
                'matching_session_id' => $trip->matching_session_id,
                'driver_location' => $trip->driver_id ? $trip->driver?->fresh()?->only(['current_latitude', 'current_longitude']) : null,
                'timeline' => $this->timeline($trip),
                'last_status_event' => $trip->statusEvents()->latest('created_at')->first(),
                'updated_at' => $trip->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function matchingSession(Request $request, Trip $trip): JsonResponse
    {
        if (! $this->canAccessTrip($request, $trip)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = null;
        if ($trip->matching_session_id) {
            $session = MatchingSession::query()
                ->where('matching_session_id', $trip->matching_session_id)
                ->first();
        }

        if (! $session) {
            $session = MatchingSession::query()
                ->where('passenger_id', $trip->passenger_id)
                ->where('transport_type', $trip->transport_type)
                ->latest()
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $session ? [
                'matching_session_id' => $session->matching_session_id,
                'status' => $session->status,
                'transport_type' => $session->transport_type,
                'selected_driver_id' => $session->selected_driver_id,
                'payload' => $session->payload,
                'expires_at' => $session->expires_at?->toIso8601String(),
                'created_at' => $session->created_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function acknowledgeTrip(Request $request, Trip $trip): JsonResponse
    {
        if (! $this->canAccessTrip($request, $trip)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'acknowledgement_type' => 'required|string|max:80',
            'source' => 'nullable|string|max:80',
            'metadata' => 'nullable|array',
        ]);

        $event = TripStatusEvent::query()->create([
            'trip_id' => $trip->id,
            'actor_type' => $request->user()?->isDriver() ? 'driver' : 'passenger',
            'actor_id' => $request->user()?->id,
            'old_status' => $trip->status,
            'new_status' => $trip->status,
            'metadata' => [
                'acknowledgement_type' => $validated['acknowledgement_type'],
                'source' => $validated['source'] ?? 'mobile_app',
                'metadata' => $validated['metadata'] ?? [],
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'acknowledgement_type' => $validated['acknowledgement_type'],
                'trip_status' => $trip->status,
                'event_id' => $event->id,
                'acknowledged_at' => $event->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function acknowledgeNotification(Request $request, int $id): JsonResponse
    {
        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'channel' => 'nullable|string|max:32',
            'source' => 'nullable|string|max:80',
            'device_id' => 'nullable|string|max:120',
            'metadata' => 'nullable|array',
        ]);

        $notification->forceFill([
            'is_read' => true,
            'read_at' => $notification->read_at ?: now(),
        ])->save();

        $delivery = NotificationDelivery::query()->updateOrCreate(
            ['notification_id' => $notification->id, 'user_id' => $notification->user_id],
            [
                'channel' => $validated['channel'] ?? 'push',
                'status' => 'acknowledged',
                'delivered_at' => now(),
                'acknowledged_at' => now(),
                'payload' => [
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                ],
                'metadata' => [
                    'source' => $validated['source'] ?? 'mobile_app',
                    'device_id' => $validated['device_id'] ?? null,
                    'metadata' => $validated['metadata'] ?? [],
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'notification_id' => $notification->id,
                'delivery_id' => $delivery->id,
                'status' => $delivery->status,
                'acknowledged_at' => $delivery->acknowledged_at?->toIso8601String(),
            ],
        ]);
    }

    private function canAccessTrip(Request $request, Trip $trip): bool
    {
        $user = $request->user();

        if ($user->role?->isManager() || $user->role?->isSuperAdmin()) {
            return true;
        }

        if ($user->driver?->id && (int) $trip->driver_id === (int) $user->driver->id) {
            return true;
        }

        return (int) $trip->passenger_id === (int) $user->mobile_user_id;
    }

    private function timeline(Trip $trip): array
    {
        return [
            ['label' => 'Requested', 'checked' => $trip->requested_at !== null || $trip->exists],
            ['label' => 'AI Matching', 'checked' => $trip->matching_session_id !== null || $trip->assignment_status !== null],
            ['label' => 'Driver Selected', 'checked' => $trip->driver_id !== null],
            ['label' => 'Driver Accepted', 'checked' => in_array($trip->status, ['ACCEPTED', 'STARTED', 'COMPLETED'], true)],
            ['label' => 'Driver Arriving', 'checked' => in_array($trip->status, ['ACCEPTED', 'STARTED', 'COMPLETED'], true)],
            ['label' => 'Picked Up', 'checked' => $trip->pickup_verified_at !== null || in_array($trip->status, ['STARTED', 'COMPLETED'], true)],
            ['label' => 'In Progress', 'checked' => in_array($trip->status, ['STARTED', 'COMPLETED'], true)],
            ['label' => 'Completed', 'checked' => $trip->status === 'COMPLETED'],
        ];
    }
}
