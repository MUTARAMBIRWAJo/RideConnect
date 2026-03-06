<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserApprovalController extends Controller
{
    /**
     * Show pending users page.
     */
    public function pendingView(): \Illuminate\View\View
    {
        $currentUser = Auth::guard('admin')->user();
        
        $query = User::where('is_approved', false)
            ->orderBy('created_at', 'desc');

        // If not superadmin, only show mobile app users (passengers and drivers)
        if (!$currentUser->role || !$currentUser->role->isSuperAdmin()) {
            $query->whereIn('role', [
                UserRole::PASSENGER,
                UserRole::DRIVER,
            ]);
        }

        $users = $query->get();

        return view('admin.pending-users', compact('users'));
    }

    /**
     * Show all users page.
     */
    public function indexView(): \Illuminate\View\View
    {
        $currentUser = Auth::guard('admin')->user();
        
        $query = User::orderBy('created_at', 'desc');

        // If not superadmin, only show mobile app users
        if (!$currentUser->role || !$currentUser->role->isSuperAdmin()) {
            $query->whereIn('role', [
                UserRole::PASSENGER,
                UserRole::DRIVER,
            ]);
        }

        $users = $query->get();

        return view('admin.users', compact('users'));
    }

    /**
     * Get pending users for approval.
     * 
     * - SuperAdmin can see all pending users (managers, passengers, drivers)
     * - Admin can only see mobile app users (passengers and drivers)
     */
    public function pending(Request $request): JsonResponse
    {
        $currentUser = Auth::guard('admin')->user();
        
        $query = User::where('is_approved', false)
            ->orderBy('created_at', 'desc');

        // If not superadmin, only show mobile app users (passengers and drivers)
        if (!$currentUser->role || !$currentUser->role->isSuperAdmin()) {
            $query->whereIn('role', [
                UserRole::PASSENGER->value,
                UserRole::DRIVER->value,
            ]);
        }

        $users = $query->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role?->value,
                    'created_at' => $user->created_at,
                ];
            }),
        ]);
    }

    /**
     * Approve a user.
     * 
     * - SuperAdmin can approve all users
     * - Admin can only approve mobile app users (passengers and drivers)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        $currentUser = Auth::guard('admin')->user();
        $user = User::findOrFail($id);

        // Check permissions
        if (!$currentUser->role || !$currentUser->role->isSuperAdmin()) {
            // Admin can only approve mobile app users
            if (!in_array($user->role?->value, [
                UserRole::PASSENGER->value,
                UserRole::DRIVER->value,
            ])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only approve mobile app users.',
                ], 403);
            }
        }

        // Update user approval status
        $user->update([
            'is_approved' => true,
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User approved successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'is_approved' => $user->is_approved,
                'approved_by' => $currentUser->name,
                'approved_at' => $user->approved_at,
            ],
        ]);
    }

    /**
     * Reject a user (delete or mark as rejected).
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $currentUser = Auth::guard('admin')->user();
        $user = User::findOrFail($id);

        // Check permissions
        if (!$currentUser->role || !$currentUser->role->isSuperAdmin()) {
            // Admin can only reject mobile app users
            if (!in_array($user->role?->value, [
                UserRole::PASSENGER->value,
                UserRole::DRIVER->value,
            ])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You can only reject mobile app users.',
                ], 403);
            }
        }

        // Delete the user (or you could mark as rejected)
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User rejected and removed.',
        ]);
    }

    /**
     * Get all approved users.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::guard('admin')->user();
        
        $query = User::where('is_approved', true)
            ->orderBy('approved_at', 'desc');

        // If not superadmin, only show mobile app users
        if (!$currentUser->role || !$currentUser->role->isSuperAdmin()) {
            $query->whereIn('role', [
                UserRole::PASSENGER->value,
                UserRole::DRIVER->value,
            ]);
        }

        $users = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role?->value,
                    'approved_at' => $user->approved_at,
                ];
            }),
        ]);
    }
}
