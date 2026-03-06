<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any users.
     * Super Admin can view all users.
     * Managers can view mobile users.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMIN || 
               ($user->role && $user->role->isManager());
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        // Super Admin can view everyone
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Managers can view mobile users
        if ($user->role && $user->role->isManager()) {
            return $model->role && $model->role->isMobileUser();
        }

        // Mobile Users can only view themselves
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create users.
     * Only managers can create users.
     */
    public function create(User $user): bool
    {
        return $user->role && $user->role->isManager();
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        // Super Admin can update everyone
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Managers can update mobile users
        if ($user->role && $user->role->isManager()) {
            return $model->role && $model->role->isMobileUser();
        }

        // Users can only update themselves
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the user.
     * Only Super Admin can delete users.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine whether the user can restore the user.
     * Only Super Admin can restore users.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine whether the user can permanently delete the user.
     * Only Super Admin can permanently delete users.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    public function updateRole(User $user, User $model): bool
    {
        $actorRole = $user->role?->value ?? $user->role;
        $targetRole = $model->role?->value ?? $model->role;

        if ($user->id === $model->id) {
            return false;
        }

        if ($actorRole === UserRole::SUPER_ADMIN->value) {
            return true;
        }

        if ($actorRole === UserRole::ADMIN->value) {
            return $targetRole !== UserRole::SUPER_ADMIN->value;
        }

        return false;
    }
}
