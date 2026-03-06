<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Enum;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     * 
     * - SuperAdmin: Can see all users
     * - Admin/Manager: Can see mobile users (drivers, passengers)
     * - Mobile users: Can only see their own profile
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = User::query();
        
        // Role-based filtering
        if ($user->role->isSuperAdmin()) {
            // SuperAdmin can see all users
        } elseif ($user->role->isManager()) {
            // Managers can only see mobile users
            $query->whereIn('role', [UserRole::DRIVER, UserRole::PASSENGER]);
        } else {
            // Mobile users can only see their own profile
            $query->where('id', $user->id);
        }
        
        $users = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $users->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->role->value,
                'is_approved' => $u->is_approved,
                'is_verified' => $u->is_verified,
                'created_at' => $u->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($id);
        
        // Check permissions
        if (!$currentUser->role->isSuperAdmin() && !$currentUser->role->isManager()) {
            if ($currentUser->id !== $targetUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this user',
                ], 403);
            }
        }
        
        // Managers can only view mobile users
        if ($currentUser->role->isManager() && !$targetUser->role->isMobileUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this user',
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'phone' => $targetUser->phone,
                'role' => $targetUser->role->value,
                'is_approved' => $targetUser->is_approved,
                'is_verified' => $targetUser->is_verified,
                'approved_at' => $targetUser->approved_at?->toIso8601String(),
                'created_at' => $targetUser->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($id);
        
        // Check permissions
        if (!$currentUser->role->isSuperAdmin() && !$currentUser->role->isManager()) {
            if ($currentUser->id !== $targetUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this user',
                ], 403);
            }
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
        ]);
        
        // Users can only update their own name and phone
        if (!$currentUser->role->isSuperAdmin() && !$currentUser->role->isManager()) {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
            ]);
        }
        
        // SuperAdmin can update more fields
        if ($currentUser->role->isSuperAdmin()) {
            $adminValidated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'role' => ['sometimes', new Enum(UserRole::class)],
                'is_verified' => 'sometimes|boolean',
                'is_approved' => 'sometimes|boolean',
            ]);
            $validated = array_merge($validated, $adminValidated);
            
            if (isset($adminValidated['role'])) {
                $validated['role'] = UserRole::from($adminValidated['role']);
            }
        }
        
        $targetUser->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'phone' => $targetUser->phone,
                'role' => $targetUser->role->value,
                'is_approved' => $targetUser->is_approved,
                'is_verified' => $targetUser->is_verified,
            ],
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $targetUser = User::findOrFail($id);
        
        // Only SuperAdmin can delete users
        if (!$currentUser->role->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only SuperAdmin can delete users',
            ], 403);
        }
        
        // Cannot delete yourself
        if ($currentUser->id === $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 400);
        }
        
        $targetUser->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get current user's profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'is_approved' => $user->is_approved,
                'is_verified' => $user->is_verified,
                'approved_at' => $user->approved_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update current user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        
        $user = $request->user();
        
        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }
        
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }
}
