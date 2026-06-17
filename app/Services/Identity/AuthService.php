<?php
#app/Services/Identity/AuthService.php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
{
    /**
     * Authenticate any user (Manager or Mobile user) and issue a Sanctum token.
     *
     * @param string $login Email or phone number.
     * @param string $password Clear-text password.
     * @param string|null $deviceName Name of the client device.
     * @return array
     */
    public function authenticate(string $login, string $password, ?string $deviceName = null): array
    {
        $query = User::query()->where('email', $login);

        if (Schema::hasColumn('users', 'phone')) {
            $query->orWhere('phone', $login);
        }

        $user = $query->first();

        // Check if user exists and password is correct
        if (! $user || ! Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'code' => 401,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
            ];
        }

        if (! $user->role) {
            return [
                'success' => false,
                'code' => 403,
                'error_code' => 'UNAUTHORIZED_ROLE',
                'message' => 'Your role is not authorized to access this platform.',
            ];
        }

        // Check if user is approved (skip check for managers to prevent lockouts)
        $isManager = $user->role->isManager();

        if (! $user->is_approved && ! $isManager) {
            return [
                'success' => false,
                'code' => 403,
                'error_code' => 'PENDING_APPROVAL',
                'message' => 'Your account is pending approval. Please contact administrator.',
            ];
        }

        // Revoke all existing tokens (single session enforcement)
        $user->tokens()->delete();

        // Create new token
        $tokenName = $deviceName ?: ($isManager ? 'manager-token' : 'auth-token');
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'success' => true,
            'code' => 200,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'phone' => $user->phone ?? null,
                    'is_approved' => (bool) $user->is_approved,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ];
    }
}
