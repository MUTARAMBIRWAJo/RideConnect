<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\TracksAdminActivity;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserRoleUpdateResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserManagementController extends Controller
{
    use TracksAdminActivity;

    /**
     * Get all users.
     * GET /api/v1/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only super admins and admins can list all users
        if (!$user->isSuperAdmin() && $user->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view all users',
            ], 403);
        }

        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Filter by approval status
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        // Filter by verification status
        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->boolean('is_verified'));
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('created_from')) {
            $query->whereDate('created_at', '>=', $request->created_from);
        }
        if ($request->has('created_to')) {
            $query->whereDate('created_at', '<=', $request->created_to);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
                'is_approved' => $user->is_approved,
                'is_verified' => $user->is_verified,
                'approved_at' => $user->approved_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ]),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new user (manager).
     * POST /api/v1/admin/users
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only super admins can create managers
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can create manager accounts',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string|in:SUPER_ADMIN,ADMIN,ACCOUNTANT,OFFICER',
            'phone' => 'nullable|string|max:20',
        ]);

        // Prevent creating super admins unless it's the first one
        if ($validated['role'] === 'SUPER_ADMIN') {
            $existingSuperAdmin = User::where('role', 'SUPER_ADMIN')->exists();
            if ($existingSuperAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create additional super admin accounts',
                ], 403);
            }
        }

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::from($validated['role']),
            'phone' => $validated['phone'] ?? null,
            'is_approved' => true, // Managers are auto-approved
            'approved_at' => now(),
            'approved_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $newUser->role->value,
                'is_approved' => $newUser->is_approved,
                'created_at' => $newUser->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get user details.
     * GET /api/v1/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        $targetUser = User::findOrFail($id);

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
                'approved_by' => $targetUser->approver ? [
                    'id' => $targetUser->approver->id,
                    'name' => $targetUser->approver->name,
                ] : null,
                'created_at' => $targetUser->created_at->toIso8601String(),
                'updated_at' => $targetUser->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update a user.
     * PUT /api/v1/admin/users/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($id);

        // Prevent self-demotion
        if ($targetUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify your own account',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'is_approved' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
        ]);

        // Handle approval
        if (isset($validated['is_approved'])) {
            if ($validated['is_approved'] && !$targetUser->is_approved) {
                $validated['approved_at'] = now();
                $validated['approved_by'] = $user->id;
            } elseif (!$validated['is_approved']) {
                $validated['approved_at'] = null;
                $validated['approved_by'] = null;
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
                'role' => $targetUser->role->value,
                'is_approved' => $targetUser->is_approved,
                'is_verified' => $targetUser->is_verified,
                'updated_at' => $targetUser->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a user.
     * DELETE /api/v1/admin/users/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = request()->user();
        $targetUser = User::findOrFail($id);

        // Prevent self-deletion
        if ($targetUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 403);
        }

        // Prevent deleting super admins unless you're a super admin
        if ($targetUser->role === UserRole::SUPER_ADMIN && !$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin accounts',
            ], 403);
        }

        $targetUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Update user role.
     * PUT /api/v1/admin/users/{id}/role
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::findOrFail($id);

        Gate::forUser($user)->authorize('updateRole', $targetUser);

        $validated = Validator::make($request->all(), [
            'role' => 'required|string|in:SUPER_ADMIN,ADMIN,ACCOUNTANT,OFFICER,DRIVER,PASSENGER',
        ])->validate();

        // Prevent changing own role
        if ($targetUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change your own role',
            ], 403);
        }

        // Admin cannot change Super_admin role
        $actorIsAdmin = ($user->role?->value ?? $user->role) === UserRole::ADMIN->value;
        $targetIsSuperAdmin = ($targetUser->role?->value ?? $targetUser->role) === UserRole::SUPER_ADMIN->value
            || (method_exists($targetUser, 'hasRole') && $targetUser->hasRole('Super_admin'));

        if ($actorIsAdmin && $targetIsSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin cannot change Super_admin role',
            ], 403);
        }

        if ($actorIsAdmin && $validated['role'] === UserRole::SUPER_ADMIN->value) {
            return response()->json([
                'success' => false,
                'message' => 'Admin cannot assign Super_admin role',
            ], 403);
        }

        $targetUser->update([
            'role' => UserRole::from($validated['role']),
        ]);

        $roleMap = [
            UserRole::SUPER_ADMIN->value => 'Super_admin',
            UserRole::ADMIN->value => 'Admin',
            UserRole::ACCOUNTANT->value => 'Accountant',
            UserRole::OFFICER->value => 'Officer',
        ];

        $spatieRole = $roleMap[$validated['role']] ?? null;

        if ($spatieRole && method_exists($targetUser, 'syncRoles')) {
            $targetUser->syncRoles([$spatieRole]);
        }

        $this->trackAdminActivity(
            $user,
            'update_user_role',
            "Updated user #{$targetUser->id} role to {$validated['role']}"
        );

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => new UserRoleUpdateResource($targetUser->fresh()),
        ]);
    }
}
