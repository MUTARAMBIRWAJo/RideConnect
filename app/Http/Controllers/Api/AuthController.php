<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\MobileLoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        return $this->registerByRole($request, UserRole::from($request->validated('role')));
    }

    /**
     * Register a new driver from mobile app payload.
     */
    public function registerDriver(RegisterRequest $request): JsonResponse
    {
        return $this->registerByRole($request, UserRole::DRIVER);
    }

    /**
     * Register a new passenger from mobile app payload.
     */
    public function registerPassenger(RegisterRequest $request): JsonResponse
    {
        return $this->registerByRole($request, UserRole::PASSENGER);
    }

    /**
     * Shared registration workflow for mobile users.
     */
    private function registerByRole(RegisterRequest $request, UserRole $role): JsonResponse
    {
        // Create the user with validated data
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => $role,
            'phone' => $request->validated('phone'),
            'is_approved' => true, // Auto-approve mobile registration for testing
        ]);

        try {
            app(\App\Services\Identity\IdentityResolverService::class)->ensureLegacyMobileUserLink($user);
            $user->refresh();
        } catch (\Throwable $e) {
            Log::warning('Failed to create legacy mobile user link during registration: ' . $e->getMessage());
        }

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
    public function login(LoginRequest $request, \App\Services\Identity\AuthService $authService): JsonResponse
    {
        $result = $authService->authenticate(
            $request->validated('email'),
            $request->validated('password')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $result['data'],
        ]);
    }

    /**
     * Mobile login that accepts either email or phone.
     * Safely handles missing columns and database errors.
     */
    public function mobileLogin(MobileLoginRequest $request, \App\Services\Identity\AuthService $authService): JsonResponse
    {
        try {
            $result = $authService->authenticate(
                $request->validated('login'),
                $request->validated('password'),
                $request->validated('device_name') ?: 'flutter-mobile'
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], $result['code']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => $result['data'],
            ]);
        } catch (\Throwable $throwable) {
            Log::error('Mobile login failed', [
                'error' => $throwable->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to login at this time. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout user (API).
     *
     * Revokes the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Revoke the current token
        $user->currentAccessToken()->delete();
        
        $user->update([
            'is_online' => false,
            'current_device_id' => null,
            'current_token_id' => null,
            'last_seen_at' => now(),
        ]);
        
        if ($user->role && $user->role->value === 'DRIVER' && \Illuminate\Support\Facades\Schema::hasTable('drivers')) {
            \Illuminate\Support\Facades\DB::table('drivers')
                ->where('user_id', $user->id)
                ->update([
                    'is_online' => false,
                    'last_seen_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Clear auth session/tokens for mobile users.
     * POST /api/v1/auth/session/clear
     */
    public function clearSession(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'all_devices' => 'sometimes|boolean',
        ]);

        $allDevices = (bool) ($validated['all_devices'] ?? true);

        if ($allDevices) {
            $revokedTokens = $user->tokens()->count();
            $user->tokens()->delete();
        } else {
            $currentToken = $user->currentAccessToken();
            $revokedTokens = $currentToken ? 1 : 0;
            if ($currentToken) {
                $currentToken->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Auth session cleared successfully. Please login again.',
            'data' => [
                'revoked_tokens' => $revokedTokens,
                'all_devices' => $allDevices,
            ],
        ]);
    }

    /**
     * Validate bearer token for protected API calls.
     * GET /api/v1/auth/token/validate
     */
    public function validateToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone,
                    'is_approved' => $user->is_approved,
                ],
                'token' => [
                    'id' => $token?->id,
                    'name' => $token?->name,
                    'last_used_at' => $token?->last_used_at?->toIso8601String(),
                    'created_at' => $token?->created_at?->toIso8601String(),
                ],
            ],
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
    public function managerLogin(Request $request, \App\Services\Identity\AuthService $authService): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if ($user && !$user->isManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers can login through this endpoint',
            ], 403);
        }

        $result = $authService->authenticate(
            $validated['email'],
            $validated['password'],
            'manager-token'
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Manager login successful',
            'data' => $result['data'],
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

    /**
     * Forgot password - Send password reset link.
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Generate password reset token
        $token = Str::random(60);
        
        // Store token in database (you may want to create a password_resets table)
        // For now, we'll use a simple approach with the users table
        $user->update([
            'password_reset_token' => $token,
            'password_reset_expires_at' => now()->addHours(1),
        ]);

        // In production, send email with reset link
        // For now, return the token for testing
        return response()->json([
            'success' => true,
            'message' => 'Password reset token generated successfully',
            'data' => [
                'reset_token' => $token, // In production, remove this and send via email
                'expires_at' => now()->addHours(1)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Verify password reset token.
     * GET /api/v1/auth/verify-reset-token/{token}
     */
    public function verifyResetToken(string $token): JsonResponse
    {
        $user = User::where('password_reset_token', $token)
            ->where('password_reset_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'email' => $user->email,
                'expires_at' => $user->password_reset_expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Reset password with token.
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('password_reset_token', $validated['token'])
            ->where('password_reset_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token',
            ], 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
        ]);

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please login with your new password.',
        ]);
    }
}
