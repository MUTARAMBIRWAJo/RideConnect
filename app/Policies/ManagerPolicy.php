<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Manager;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManagerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any managers.
     * Only Super Admin can view all managers.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine whether the user can view the manager.
     */
    public function view(User $user, Manager $model): bool
    {
        // Super Admin can view everyone
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Managers can view themselves
        return $user->manager_id === $model->id;
    }

    /**
     * Determine whether the user can create managers.
     * Only Super Admin can create managers.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine whether the user can update the manager.
     */
    public function update(User $user, Manager $model): bool
    {
        // Super Admin can update everyone
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Managers can update themselves
        return $user->manager_id === $model->id;
    }

    /**
     * Determine whether the user can delete the manager.
     * Only Super Admin can delete managers.
     */
    public function delete(User $user, Manager $model): bool
    {
        // Super Admin can delete managers, but not themselves
        return $user->role === UserRole::SUPER_ADMIN && $user->manager_id !== $model->id;
    }

    /**
     * Determine whether the user can restore the manager.
     * Only Super Admin can restore managers.
     */
    public function restore(User $user, Manager $model): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine whether the user can permanently delete the manager.
     * Only Super Admin can permanently delete managers.
     */
    public function forceDelete(User $user, Manager $model): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    public function viewAdminDashboard(User $user): bool
    {
        return in_array($user->role?->value ?? $user->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], true);
    }

    public function viewSystemLogs(User $user): bool
    {
        return ($user->role?->value ?? $user->role) === UserRole::SUPER_ADMIN->value;
    }

    public function exportFinance(User $user): bool
    {
        return in_array($user->role?->value ?? $user->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ACCOUNTANT->value,
        ], true);
    }
}
