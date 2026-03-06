<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\MobileUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class MobileUserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any mobile users.
     * Super Admin and Managers can view all mobile users.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMIN || 
               ($user->role && $user->role->isManager());
    }

    /**
     * Determine whether the user can view the mobile user.
     */
    public function view(User $user, MobileUser $model): bool
    {
        // Super Admin can view everyone
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Managers can view mobile users
        if ($user->role && $user->role->isManager()) {
            return true;
        }

        // Drivers can view passenger info
        if ($user->role === UserRole::DRIVER) {
            return $model->role === UserRole::PASSENGER;
        }

        // Mobile Users can only view themselves
        return $user->mobile_user_id === $model->id;
    }

    /**
     * Determine whether the user can view driver info.
     * Passengers can only view limited driver info (name, vehicle plate, location).
     */
    public function viewDriver(User $user, MobileUser $model): bool
    {
        // Only drivers can be viewed
        if ($model->role !== UserRole::DRIVER) {
            return false;
        }

        // Super Admin and Managers can view full driver info
        if ($user->role === UserRole::SUPER_ADMIN || 
            ($user->role && $user->role->isManager())) {
            return true;
        }

        // Drivers can view other drivers
        if ($user->role === UserRole::DRIVER) {
            return true;
        }

        // Passengers can view limited driver info
        if ($user->role === UserRole::PASSENGER) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view passenger info.
     */
    public function viewPassenger(User $user, MobileUser $model): bool
    {
        // Only passengers can be viewed
        if ($model->role !== UserRole::PASSENGER) {
            return false;
        }

        // Super Admin and Managers can view full passenger info
        if ($user->role === UserRole::SUPER_ADMIN || 
            ($user->role && $user->role->isManager())) {
            return true;
        }

        // Drivers can view passenger info
        if ($user->role === UserRole::DRIVER) {
            return true;
        }

        // Passengers can only view themselves
        return $user->mobile_user_id === $model->id;
    }

    /**
     * Determine whether the user can create mobile users.
     * Only managers can create mobile users.
     */
    public function create(User $user): bool
    {
        return $user->role && $user->role->isManager();
    }

    /**
     * Determine whether the user can update the mobile user.
     */
    public function update(User $user, MobileUser $model): bool
    {
        // Super Admin can update everyone
        if ($user->role === UserRole::SUPER_ADMIN) {
            return true;
        }

        // Managers can update mobile users
        if ($user->role && $user->role->isManager()) {
            return true;
        }

        // Mobile Users can only update themselves
        return $user->mobile_user_id === $model->id;
    }

    /**
     * Determine whether the user can delete the mobile user.
     * Only Super Admin can delete mobile users.
     */
    public function delete(User $user, MobileUser $model): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Get visible fields for driver based on user role.
     */
    public function getVisibleDriverFields(User $user): array
    {
        // Full info for Super Admin, Managers, and Drivers
        if ($user->role === UserRole::SUPER_ADMIN || 
            ($user->role && $user->role->isManager()) ||
            $user->role === UserRole::DRIVER) {
            return ['*']; // All fields
        }

        // Limited info for Passengers
        if ($user->role === UserRole::PASSENGER) {
            return [
                'first_name',
                'last_name',
                'phone',
            ];
        }

        return [];
    }
}