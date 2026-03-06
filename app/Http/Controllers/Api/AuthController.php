<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user (API).
     * 
     * Only passenger and rider roles are allowed via public API.
     * Managers (admin, officer, superadmin) are NOT allowed via public API.
     * New registrations require approval before login is allowed.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // Create the user with validated data
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => UserRole::from($request->validated('role')),
            'phone' => $request->validated('phone'),
            'is_approved' => false, // Require approval before login
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your account is pending approval.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone,
                    'is_approved' => $user->is_approved,
                ],
            ],
        ], 201);
    }

    /**
     * Login user (API).
     * 
     * Returns 401 for invalid credentials.
     * Returns 403 if account is not approved.
     * Returns token + user role on success.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check if user is approved
        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending approval. Please contact administrator.',
            ], 403);
        }

        // Revoke all existing tokens (optional - each login generates new token)
        // Comment out if you want multiple tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout user (API).
     * 
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Get current user profile (API).
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'created_at' => $user->created_at->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Get current user's approval status (API).
     * 
     * Users can check their approval status even before being approved.
     */
    public function approvalStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'is_approved' => $user->is_approved,
                'approved_at' => $user->approved_at?->toIso8601String(),
                'approved_by' => $user->approved_by ? [
                    'id' => $user->approver->id,
                    'name' => $user->approver->name,
                ] : null,
                'can_login' => $user->is_approved,
            ],
            'message' => $user->is_approved 
                ? 'Your account is approved.'
                : 'Your account is pending approval. Please contact administrator.',
        ]);
    }

    /**
     * Update current user profile.
     * PUT /api/v1/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->value,
            ],
        ]);
    }

    /**
     * Manager login.
     * POST /api/v1/manager/login
     */
    public function managerLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check if user is a manager
        if (!$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers can login through this endpoint',
            ], 403);
        }

        // Check if user is approved
        if (!$user->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending approval.',
            ], 403);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('manager-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Manager login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Manager logout.
     * POST /api/v1/manager/logout
     */
    public function managerLogout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Manager logout successful',
        ]);
    }

    /**
     * Get manager profile.
     * GET /api/v1/manager/profile
     */
    public function managerProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone,
                    'is_approved' => $user->is_approved,
                    'approved_at' => $user->approved_at?->toIso8601String(),
                    'created_at' => $user->created_at->toIso8601String(),
                ],
            ],
        ]);
    }
}
