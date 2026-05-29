<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MobileNotificationController extends Controller
{
    /**
     * GET /api/v2/notifications
     * Returns paginated notifications for the authenticated mobile user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $mobileUserId = $this->mobileUserId($request);
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $notifications = Notification::query()
            ->where('user_id', $mobileUserId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'code'    => 200,
            'message' => 'Notifications retrieved',
            'data'    => $notifications->items(),
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
            ->where('user_id', $this->mobileUserId($request))
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
     * PUT /api/v2/notifications/{id}/read
     * Marks a single notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $mobileUserId = $this->mobileUserId($request);

        $notification = Notification::query()
            ->where('id', $id)
            ->where('user_id', $mobileUserId)
            ->firstOrFail();

        if (! $notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'code'    => 200,
            'message' => 'Notification marked as read',
            'data'    => $notification,
        ]);
    }

    /**
     * PUT /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $this->mobileUserId($request))
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
            ->where('user_id', $this->mobileUserId($request))
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
            ->where('user_id', $this->mobileUserId($request))
            ->get(['id', 'type', 'data']);

        $actionedIds = $notifications
            ->filter(fn (Notification $notification): bool => $this->isActionedNotification($notification))
            ->pluck('id')
            ->values();

        $deletedCount = 0;
        if ($actionedIds->isNotEmpty()) {
            $deletedCount = Notification::query()
                ->where('user_id', $this->mobileUserId($request))
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
            if (! empty($data[$key])) {
                return true;
            }
        }

        return false;
    }

    private function mobileUserId(Request $request): int
    {
        $user = $request->user();
        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        return (int) (MobileUser::query()->where('email', $user->email)->value('id') ?? $user->id);
    }
}
