<?php

namespace App\Filament\Pages\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Throwable;

trait HandlesRoleDashboards
{
    protected static function resolveUserRoleValue(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        return $user->role instanceof UserRole ? $user->role->value : (is_string($user->role) ? $user->role : null);
    }

    protected static function userHasRole(?User $user, string $spatieRole, UserRole $enumRole): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->exists) {
            try {
                if ($user->hasRole($spatieRole)) {
                    return true;
                }

                // Some code paths may store roles as underscored/no-case variants.
                if ($user->hasRole(strtolower($spatieRole)) || $user->hasRole(strtoupper($spatieRole))) {
                    return true;
                }
            } catch (Throwable) {
                // Fall back to enum role when role-table lookup is unavailable.
            }
        }

        return static::resolveUserRoleValue($user) === $enumRole->value;
    }

    protected static function userHasAnyRole(?User $user, array $spatieRoles, array $enumRoles = []): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->exists) {
            foreach ($spatieRoles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }

                if ($user->hasRole(strtolower($role)) || $user->hasRole(strtoupper($role))) {
                    return true;
                }
            }
        }

        foreach ($enumRoles as $enumRole) {
            if (static::resolveUserRoleValue($user) === $enumRole->value) {
                return true;
            }
        }

        return false;
    }
}

