<?php
#app/Services/Identity/AuthService.php

namespace App\Services\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
{
    /**
     * Authenticate any user (Manager or Mobile user) and issue a Sanctum token.
     *
     * Supports login with email OR phone number.
     * Phone number lookup first checks users.phone; if not found there,
     * falls back to mobile_users.phone → users.mobile_user_id bridge.
     *
     * @param string $login Email or phone number.
     * @param string $password Clear-text password.
     * @param string|null $deviceName Name of the client device.
     * @return array
     */
    public function authenticate(string $login, string $password, ?string $deviceName = null): array
    {
        $user = $this->resolveUserByLogin($login);

        // Check if user exists and password is correct
        if (! $user || ! Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'code'    => 401,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
            ];
        }

        if (! $user->role) {
            return [
                'success' => false,
                'code'    => 403,
                'error_code' => 'UNAUTHORIZED_ROLE',
                'message' => 'Your role is not authorized to access this platform.',
            ];
        }

        // Check if user is approved (skip check for managers to prevent lockouts)
        $isManager = $user->role->isManager();

        if (! $user->is_approved && ! $isManager) {
            return [
                'success' => false,
                'code'    => 403,
                'error_code' => 'PENDING_APPROVAL',
                'message' => 'Your account is pending approval. Please contact administrator.',
            ];
        }

        // Create new token

        $tokenName = $deviceName ?: ($isManager ? 'manager-token' : 'auth-token');
        $tokenResult = $user->createToken($tokenName);
        
        $user->update([
            'last_seen_at' => now(),
            'is_online' => true,
            'current_device_id' => $deviceName,
            'current_token_id' => $tokenResult->accessToken->id,
        ]);
        
        if ($user->role && $user->role->value === 'DRIVER' && Schema::hasTable('drivers')) {
            DB::table('drivers')
                ->where('user_id', $user->id)
                ->update([
                    'last_seen_at' => now(),
                    'is_online' => true,
                    'last_online_at' => now(),
                ]);
        }

        $token = $tokenResult->plainTextToken;

        // Resolve phone: prefer users.phone, fall back to linked mobile_users.phone
        $phone = $user->phone;
        if (! $phone && $user->mobile_user_id && Schema::hasTable('mobile_users')) {
            $phone = DB::table('mobile_users')
                ->where('id', $user->mobile_user_id)
                ->value('phone');
        }

        // Resolve driver profile ID (drivers.id) for driver users
        $driverId = null;
        if ($user->role->value === 'DRIVER' && Schema::hasTable('drivers')) {
            $driverId = DB::table('drivers')
                ->where('user_id', $user->id)
                ->value('id');
        }

        return [
            'success' => true,
            'code'    => 200,
            'data'    => [
                'user' => [
                    'id'            => $user->id,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'role'          => $user->role->value,
                    'phone'         => $phone,
                    'profile_photo' => $user->profile_photo ?? null,
                    'is_approved'   => (bool) $user->is_approved,
                    'driver_id'     => $driverId,
                ],
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ];
    }

    /**
     * Resolve a User model by email or phone (with mobile_users fallback).
     *
     * Lookup priority:
     *  1. users.email = $login
     *  2. users.phone = $login  (direct match)
     *  3. mobile_users.phone = $login → users.mobile_user_id (legacy bridge)
     */
    private function resolveUserByLogin(string $login): ?User
    {
        // Strategy 1 & 2: email or direct phone in users table
        $query = User::query()->where('email', $login);

        if (Schema::hasColumn('users', 'phone')) {
            $query->orWhere('phone', $login);
        }

        $user = $query->first();
        if ($user) {
            return $user;
        }

        // Strategy 3: phone lookup via mobile_users table (legacy bridge)
        if (Schema::hasTable('mobile_users') && Schema::hasColumn('mobile_users', 'phone')) {
            $mobileUserId = DB::table('mobile_users')
                ->where('phone', $login)
                ->value('id');

            if ($mobileUserId) {
                return User::query()
                    ->where('mobile_user_id', $mobileUserId)
                    ->first();
            }
        }

        return null;
    }
}
