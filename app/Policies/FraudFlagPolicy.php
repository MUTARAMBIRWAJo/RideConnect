<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\FraudFlag;
use App\Models\User;

class FraudFlagPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT]);
    }

    public function view(User $user, FraudFlag $flag): bool
    {
        return in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT]);
    }

    public function create(User $user): bool
    {
        return false; // Created only through FraudDetectionService
    }

    public function update(User $user, FraudFlag $flag): bool
    {
        return false;
    }

    public function delete(User $user, FraudFlag $flag): bool
    {
        return false;
    }

    /** Only Super Admin can resolve flags (unblock payouts). */
    public function resolve(User $user, FraudFlag $flag): bool
    {
        return $user->role === UserRole::SUPER_ADMIN;
    }
}
