<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MobileNotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $currentPage = max(1, (int) $request->integer('page', 1));
        $onlyClearable = $request->boolean('only_clearable');
        $onlyActionRequired = $request->boolean('only_action_required');

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        if ($onlyClearable || $onlyActionRequired) {
            $all = $query->get();

            $filtered = $all->filter(function (Notification $notification) use ($onlyClearable, $onlyActionRequired): bool {
                $isActioned = $this->isActionedNotification($notification);

                if ($onlyClearable && !$isActioned) {
                    return false;
                }

                if ($onlyActionRequired && $isActioned) {
                    return false;
                }

                return true;
            })->values();

            $slice = $filtered->forPage($currentPage, $perPage)->values();
            $notifications = new LengthAwarePaginator(
                $slice,
                $filtered->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $notifications = $query->paginate($perPage);
        }

        return response()->json([
            'success' => true,
            'data' => Collection::make($notifications->items())
                ->map(fn (Notification $notification) => $this->formatNotification($notification))
                ->values(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'is_read' => (bool) $notification->is_read,
            'can_be_cleared' => $this->isActionedNotification($notification),
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    /**
     * GET /api/v1/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * PUT /api/v1/notifications/{id}/read
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * PUT /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * Delete one notification only when it is actioned.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = Notification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (! $this->isActionedNotification($notification)) {
            return response()->json([
                'success' => false,
                'message' => 'Notification is not actioned yet and cannot be deleted.',
                'error_code' => 'notification_not_actioned',
            ], 422);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    /**
     * DELETE /api/v1/notifications/clear-actioned
     * Clear all actioned notifications, keep pending/request notifications.
     */
    public function clearActioned(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->get(['id', 'type', 'data']);

        $actionedIds = $notifications
            ->filter(fn (Notification $notification): bool => $this->isActionedNotification($notification))
            ->pluck('id')
            ->values();

        $deletedCount = 0;
        if ($actionedIds->isNotEmpty()) {
            $deletedCount = Notification::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('id', $actionedIds->all())
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Actioned notifications cleared',
            'data' => [
                'deleted_count' => (int) $deletedCount,
                'kept_count' => (int) max(0, $notifications->count() - $deletedCount),
            ],
        ]);
    }

    private function isActionedNotification(Notification $notification): bool
    {
        $type = strtolower((string) $notification->type);
        $data = is_array($notification->data) ? $notification->data : [];
        $status = strtolower((string) ($data['status'] ?? ''));

        $pendingTypes = [
            'ride_request_received',
            'booking_request_received',
        ];

        if (in_array($type, $pendingTypes, true)) {
            return false;
        }

        $actionedKeywords = [
            'accepted',
            'rejected',
            'cancelled',
            'completed',
            'confirmed',
            'started',
        ];

        foreach ($actionedKeywords as $keyword) {
            if (str_contains($type, $keyword)) {
                return true;
            }
        }

        $actionedStatuses = [
            'accepted',
            'rejected',
            'cancelled',
            'completed',
            'confirmed',
            'started',
        ];

        if ($status !== '' && in_array($status, $actionedStatuses, true)) {
            return true;
        }

        $actionedDataKeys = [
            'accepted_at',
            'rejected_at',
            'cancelled_at',
            'completed_at',
            'confirmed_at',
            'started_at',
        ];

        foreach ($actionedDataKeys as $key) {
            if (!empty($data[$key])) {
                return true;
            }
        }

        return false;
    }
}
