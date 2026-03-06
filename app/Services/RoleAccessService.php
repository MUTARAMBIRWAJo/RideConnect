<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\MobileUser;
use App\Models\Manager;

/**
 * Service for handling role-based access control
 * 
 * Access Rules:
 * - SuperAdmin: Can view all data from the User table
 * - Admin/Accountant/Officer (Managers): Can see their own data AND Mobile Users data
 * - Mobile Users (Drivers/Passengers): Can only see their own data
 * - Passenger: Can view Driver name, Vehicle Plate, and Location
 * - Driver: Can view other Driver Info and Passenger Info
 */
class RoleAccessService
{
    /**
     * Check if the current user can view all users
     */
    public function canViewAllUsers(User $user): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Check if the current user can view mobile users
     */
    public function canViewMobileUsers(User $user): bool
    {
        return $user->role && $user->role->isManager();
    }

    /**
     * Check if the current user can view specific user data
     * 
     * @param User $currentUser The authenticated user
     * @param User $targetUser The user being viewed
     * @return bool
     */
    public function canViewUser(User $currentUser, User $targetUser): bool
    {
        // Super Admin can view everyone
        if ($this->canViewAllUsers($currentUser)) {
            return true;
        }

        // Managers can view mobile users
        if ($this->canViewMobileUsers($currentUser)) {
            return $targetUser->role && $targetUser->role->isMobileUser();
        }

        // Mobile Users can only view themselves
        return $currentUser->id === $targetUser->id;
    }

    /**
     * Check if the current user can view mobile user data
     * 
     * @param User $currentUser The authenticated user
     * @param MobileUser $targetMobileUser The mobile user being viewed
     * @return bool
     */
    public function canViewMobileUser(User $currentUser, MobileUser $targetMobileUser): bool
    {
        // Super Admin can view everyone
        if ($this->canViewAllUsers($currentUser)) {
            return true;
        }

        // Managers can view mobile users
        if ($this->canViewMobileUsers($currentUser)) {
            return true;
        }

        // Mobile Users can only view themselves
        return $currentUser->mobile_user_id === $targetMobileUser->id;
    }

    /**
     * Check if the current user can view driver info
     * 
     * Rules:
     * - SuperAdmin: Can view all driver info
     * - Managers: Can view all driver info
     * - Driver: Can view other driver info
     * - Passenger: Can view driver name, vehicle plate, and location only
     */
    public function canViewDriverInfo(User $currentUser, bool $isFullInfo = false): bool
    {
        // Super Admin and Managers can always view full driver info
        if ($this->canViewAllUsers($currentUser) || $this->canViewMobileUsers($currentUser)) {
            return true;
        }

        // Drivers can view other drivers
        if ($currentUser->role === UserRole::DRIVER) {
            return true;
        }

        // Passengers can only view limited info
        if ($currentUser->role === UserRole::PASSENGER && !$isFullInfo) {
            return true;
        }

        return false;
    }

    /**
     * Get visible driver fields based on user role
     * 
     * @param User $currentUser The authenticated user
     * @return array Fields that are visible
     */
    public function getVisibleDriverFields(User $currentUser): array
    {
        // Full info for Super Admin, Managers, and Drivers
        if ($this->canViewAllUsers($currentUser) || 
            $this->canViewMobileUsers($currentUser) || 
            $currentUser->role === UserRole::DRIVER) {
            return ['*']; // All fields
        }

        // Limited info for Passengers
        if ($currentUser->role === UserRole::PASSENGER) {
            return [
                'first_name',
                'last_name',
                'phone',
            ];
        }

        return [];
    }

    /**
     * Check if the current user can view passenger info
     * 
     * Rules:
     * - SuperAdmin: Can view all passenger info
     * - Managers: Can view all passenger info
     * - Driver: Can view passenger info
     * - Passenger: Can only view their own passenger info
     */
    public function canViewPassengerInfo(User $currentUser, ?int $targetMobileUserId = null): bool
    {
        // Super Admin and Managers can always view passenger info
        if ($this->canViewAllUsers($currentUser) || $this->canViewMobileUsers($currentUser)) {
            return true;
        }

        // Drivers can view passenger info
        if ($currentUser->role === UserRole::DRIVER) {
            return true;
        }

        // Passengers can only view their own info
        if ($currentUser->role === UserRole::PASSENGER) {
            return $currentUser->mobile_user_id === $targetMobileUserId;
        }

        return false;
    }

    /**
     * Check if the current user can manage (create, update, delete) a resource
     */
    public function canManage(User $currentUser): bool
    {
        return $currentUser->role && $currentUser->role->isManager();
    }

    /**
     * Get accessible user IDs for the current user
     * 
     * @param User $currentUser The authenticated user
     * @return array IDs that the user can access
     */
    public function getAccessibleUserIds(User $currentUser): array
    {
        // Super Admin can access everyone
        if ($this->canViewAllUsers($currentUser)) {
            return User::pluck('id')->toArray();
        }

        // Managers can access mobile users and themselves
        if ($this->canViewMobileUsers($currentUser)) {
            $mobileUserIds = MobileUser::pluck('id')->toArray();
            return array_merge([$currentUser->id], $mobileUserIds);
        }

        // Mobile Users can only access themselves
        return [$currentUser->id];
    }

    /**
     * Filter query based on user role
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $currentUser
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function filterUserQuery($query, User $currentUser)
    {
        // Super Admin can see all
        if ($this->canViewAllUsers($currentUser)) {
            return $query;
        }

        // Managers can see mobile users
        if ($this->canViewMobileUsers($currentUser)) {
            return $query->whereIn('role', UserRole::mobileUserRoles())
                        ->orWhere('id', $currentUser->id);
        }

        // Mobile Users can only see themselves
        return $query->where('id', $currentUser->id);
    }
}
