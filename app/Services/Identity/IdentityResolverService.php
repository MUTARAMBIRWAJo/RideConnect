<?php

namespace App\Services\Identity;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical identity resolution for RideConnect.
 *
 * All authenticated actors resolve to users.id. Driver operational references
 * use drivers.id but always map back to users.id via drivers.user_id.
 */
class IdentityResolverService
{
    /**
     * Canonical user identifier — always users.id.
     */
    public function canonicalUserId(User $user): int
    {
        return (int) $user->id;
    }

    /**
     * Passenger ownership reference for new records (users.id).
     */
    public function passengerOwnerId(User $user): int
    {
        return $this->canonicalUserId($user);
    }

    /**
     * All passenger_id values that may represent this user during migration.
     *
     * @return list<int>
     */
    public function passengerOwnerIdsForQuery(User $user): array
    {
        $ids = [(int) $user->id];

        if ($user->mobile_user_id) {
            $ids[] = (int) $user->mobile_user_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Whether the authenticated user owns a passenger reference value.
     */
    public function userOwnsPassengerReference(User $user, int $passengerReference): bool
    {
        return in_array($passengerReference, $this->passengerOwnerIdsForQuery($user), true);
    }

    /**
     * Resolve any passenger reference (users.id or legacy mobile_users.id) to users.id.
     */
    public function resolvePassengerUserId(int $passengerReference): ?int
    {
        if ($passengerReference <= 0) {
            return null;
        }

        $directUser = User::query()->find($passengerReference);
        if ($directUser) {
            return (int) $directUser->id;
        }

        return User::query()
            ->where('mobile_user_id', $passengerReference)
            ->value('id');
    }

    /**
     * Resolve drivers.id to the canonical users.id.
     */
    public function driverUserIdFromDriverId(int $driverId): ?int
    {
        if ($driverId <= 0) {
            return null;
        }

        return Driver::query()
            ->where('id', $driverId)
            ->value('user_id');
    }

    /**
     * Canonical user id for a driver account.
     */
    public function driverUserId(User $user): ?int
    {
        if (! $user->isDriver()) {
            return null;
        }

        return $this->canonicalUserId($user);
    }

    /**
     * Driver profile id (drivers.id) for the authenticated driver user.
     */
    public function driverProfileId(User $user): ?int
    {
        return $user->driver?->id ? (int) $user->driver->id : null;
    }

    /**
     * Legacy mobile_users.id linked to this user, if any.
     */
    public function legacyMobileUserId(User $user): ?int
    {
        return $user->mobile_user_id ? (int) $user->mobile_user_id : null;
    }

    /**
     * Ensure a legacy mobile_users row exists for backward-compatible integrations.
     * New domain records should use users.id directly.
     */
    public function ensureLegacyMobileUserLink(User $user): MobileUser
    {
        if ($user->mobile_user_id) {
            $existing = MobileUser::query()->find($user->mobile_user_id);
            if ($existing) {
                return $existing;
            }
        }

        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2) ?: ['User', 'Account'];
        $roleStr = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;

        $mobileUser = MobileUser::query()->where('email', $user->email)->first();

        if (!$mobileUser) {
            $mobileUser = new MobileUser();
            $mobileUser->id = $user->id;
            $mobileUser->email = $user->email;
            $mobileUser->first_name = $nameParts[0] ?: 'User';
            $mobileUser->last_name = $nameParts[1] ?? 'Account';
            $mobileUser->phone = $user->phone ?? '+250700000000';
            $mobileUser->password = $user->password ?? bcrypt(\Illuminate\Support\Str::random(32));
            $mobileUser->role = $roleStr;
            $mobileUser->is_verified = (bool) $user->is_verified;
            $mobileUser->save();
        }

        if ((int) $user->mobile_user_id !== (int) $mobileUser->id) {
            $user->forceFill(['mobile_user_id' => $mobileUser->id])->save();
        }

        return $mobileUser;
    }

    /**
     * Realtime channel recipient id — prefers canonical users.id.
     */
    public function realtimePassengerChannelId(User $user): int
    {
        return $this->canonicalUserId($user);
    }

    /**
     * Realtime channel recipient id for drivers — uses drivers.id (operational profile).
     */
    public function realtimeDriverChannelId(User $user): ?int
    {
        return $this->driverProfileId($user);
    }

    /**
     * Whether passenger_id column on trips is normalized to users.id.
     */
    public function tripsPassengerReferencesUsers(): bool
    {
        if (! Schema::hasTable('trips')) {
            return false;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return ! $this->hasPassengerIdDrift();
        }

        try {
            foreach (Schema::getConnection()->getSchemaBuilder()->getForeignKeys('trips') as $foreignKey) {
                if (in_array('passenger_id', $foreignKey['columns'] ?? [], true)) {
                    return ($foreignKey['foreign_table'] ?? '') === 'users';
                }
            }
        } catch (\Throwable) {
            return ! $this->hasPassengerIdDrift();
        }

        return ! $this->hasPassengerIdDrift();
    }

    private function hasPassengerIdDrift(): bool
    {
        if (! Schema::hasTable('trips') || ! Schema::hasColumn('users', 'mobile_user_id')) {
            return false;
        }

        return DB::table('trips as t')
            ->join('users as u', 'u.mobile_user_id', '=', 't.passenger_id')
            ->whereColumn('t.passenger_id', '!=', 'u.id')
            ->exists();
    }
}
