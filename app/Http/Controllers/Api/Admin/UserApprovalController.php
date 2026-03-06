<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserApprovalController extends Controller
{
    /**
     * Get pending riders.
     * GET /api/v1/admin/riders/pending
     */
    public function pendingRiders(): JsonResponse
    {
        $pendingRiders = User::where('role', 'DRIVER')
            ->where('is_approved', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pendingRiders->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'is_approved' => $user->is_approved,
                'created_at' => $user->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Approve a rider.
     * PUT /api/v1/admin/riders/{id}/approve
     */
    public function approveRider(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $targetUser = User::where('role', 'DRIVER')->findOrFail($id);

        if ($targetUser->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'User is already approved',
            ], 400);
        }

        $targetUser->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rider approved successfully',
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'is_approved' => $targetUser->is_approved,
                'approved_at' => $targetUser->approved_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reject a rider.
     * PUT /api/v1/admin/riders/{id}/reject
     */
    public function rejectRider(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $targetUser = User::where('role', 'DRIVER')->findOrFail($id);

        // Optionally, you can delete the user or mark them as rejected
        // For now, we'll just delete them as they can re-register
        $targetUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rider rejected and removed',
            'data' => [
                'id' => $id,
                'reason' => $validated['reason'],
            ],
        ]);
    }
}
